<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\Registration;
use App\Services\CommunicationService;
use App\Services\Payment\ConnectIPSService;
use App\Services\Payment\PaymentRedirector;
use Illuminate\Support\Str;

class PublicRegistrationController extends Controller
{
    public function show(string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $event->load(['categories' => fn ($q) => $q->active()->ordered()]);

        if (! $event->settingEnabled('enable_self_registration')) {
            return view('register.closed', [
                'event' => $event,
                'reason' => 'Self-registration is not available for this event.',
            ]);
        }

        if (! $event->isRegistrationOpen()) {
            return view('register.closed', [
                'event' => $event,
                'reason' => 'Registration is currently closed.',
            ]);
        }

        if ($event->isAtCapacity()) {
            $waitlist = $event->settingEnabled('enable_waitlist');

            return view('register.closed', [
                'event' => $event,
                'reason' => $waitlist
                    ? 'This event is at capacity. You may join the waitlist below.'
                    : 'This event has reached its maximum capacity.',
                'waitlist' => $waitlist,
            ]);
        }

        return view('register.form', [
            'event' => $event,
            'categories' => $event->categories,
        ]);
    }

    public function store(PublicRegistrationRequest $request, string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();

        if (! $event->settingEnabled('enable_self_registration') || ! $event->isRegistrationOpen()) {
            return redirect()->route('register.show', $slug)
                ->with('error', 'Registration is no longer available.');
        }

        $atCapacity = $event->isAtCapacity();
        $waitlistEnabled = $event->settingEnabled('enable_waitlist');

        if ($atCapacity && ! $waitlistEnabled) {
            return redirect()->route('register.show', $slug)
                ->with('error', 'This event has reached its maximum capacity.');
        }

        $email = trim($request->email ?? '');
        $phone = trim($request->phone ?? '');

        if ($this->isDuplicate($event->id, $email, $phone)) {
            return back()->withInput()->withErrors(['email' => 'A registration with this email or phone already exists.']);
        }

        $categoryId = $request->category_id;
        $category = null;
        if ($categoryId) {
            $category = ParticipantCategory::where('id', $categoryId)
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->first();

            if (! $category) {
                return back()->withInput()->withErrors(['category_id' => 'Invalid category selected.']);
            }
        }

        $promoCode = null;
        $discountAmount = 0;
        $promoCodeInput = trim($request->promo_code ?? '');

        if (! empty($promoCodeInput)) {
            $promoCode = PromoCode::where('code', $promoCodeInput)
                ->where('event_id', $event->id)
                ->first();

            if (! $promoCode) {
                return back()->withInput()->withErrors(['promo_code' => 'Invalid promo code.']);
            }

            if (! $promoCode->isValid()) {
                return back()->withInput()->withErrors(['promo_code' => 'This promo code has expired or reached its usage limit.']);
            }

            if ($category && $category->is_paid && $category->price) {
                $effectivePrice = $this->getEffectivePrice($category);
                $discountAmount = $promoCode->calculateDiscount($effectivePrice);
            }
        }

        $effectivePrice = $category?->is_paid ? $this->getEffectivePrice($category) : ($category?->price ?? 0);

        $requiresPayment = $category?->is_paid && $event->settingEnabled('enable_payment');
        $paymentStatus = $requiresPayment ? 'pending' : null;

        if ($category && $category->requires_approval) {
            $approvalStatus = 'pending';
        } elseif ($atCapacity && $waitlistEnabled) {
            $approvalStatus = 'waitlisted';
        } else {
            $approvalStatus = 'approved';
        }

        $reg = Registration::create([
            'event_id' => $event->id,
            'category_id' => $categoryId ?: null,
            'promo_code_id' => $promoCode?->id,
            'registration_source' => 'self',
            'approval_status' => $approvalStatus,
            'name' => trim($request->name),
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'designation' => trim($request->designation ?? '') ?: null,
            'organization' => trim($request->organization ?? '') ?: null,
            'address' => trim($request->address ?? '') ?: null,
            'gender' => $request->gender,
            'pan_vat' => trim($request->pan_vat ?? '') ?: null,
            'notes' => trim($request->notes ?? '') ?: null,
            'meal_preference' => trim($request->meal_preference ?? '') ?: null,
            'special_assistance' => trim($request->special_assistance ?? '') ?: null,
            'photo_path' => $request->hasFile('photo')
                ? $request->file('photo')->store('registrations/photos', 'public')
                : null,
            'consented_at' => now(),
            'payment_status' => $paymentStatus,
        ]);

        $companionCount = (int) ($request->companion_count ?? 0);
        if ($companionCount > 0) {
            $groupId = (string) Str::uuid();
            $reg->update([
                'group_id' => $groupId,
                'companion_count' => $companionCount,
            ]);

            for ($i = 1; $i <= $companionCount; $i++) {
                Registration::create([
                    'event_id' => $event->id,
                    'category_id' => $categoryId ?: null,
                    'promo_code_id' => $promoCode?->id,
                    'registration_source' => 'self',
                    'approval_status' => $approvalStatus,
                    'name' => trim($request->name).' (Companion '.$i.')',
                    'group_id' => $groupId,
                    'companion_count' => 0,
                    'consented_at' => now(),
                    'payment_status' => $paymentStatus,
                ]);
            }
        }

        if ($requiresPayment && $category->price && $approvalStatus !== 'waitlisted') {
            $totalEffectivePrice = $effectivePrice * (1 + $companionCount);
            $totalDiscount = $discountAmount * (1 + $companionCount);

            return $this->initiatePaymentRedirect($reg, $event, $category, $totalDiscount, $promoCode, $totalEffectivePrice);
        }

        if ($approvalStatus === 'approved') {
            $this->sendConfirmation($reg, $event);
        }

        return redirect()->route('register.success', ['slug' => $slug])
            ->with('guest_number', $reg->guest_number)
            ->with('qr_hash', $reg->qr_hash);
    }

    public function success(string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $guestNumber = session('guest_number');

        if (! $guestNumber) {
            return redirect()->route('register.show', $slug);
        }

        return view('register.success', [
            'event' => $event,
            'guestNumber' => $guestNumber,
            'qrHash' => session('qr_hash'),
        ]);
    }

    public function paymentSuccess(string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $txnId = request('TXNID');

        if (! $txnId) {
            return redirect()->route('register.show', $slug);
        }

        $payment = Payment::where('transaction_id', $txnId)->first();

        if (! $payment || $payment->event_id !== $event->id) {
            return redirect()->route('register.show', $slug);
        }

        if ($payment->isSuccessful()) {
            return redirect()->route('register.success', ['slug' => $slug])
                ->with('guest_number', $payment->registration->guest_number)
                ->with('qr_hash', $payment->registration->qr_hash);
        }

        try {
            $ipsService = new ConnectIPSService;
            $validateResult = $ipsService->validatePayment($payment);
            $interpreted = $ipsService->interpretValidationResult($payment, $validateResult);

            switch ($interpreted['outcome']) {
                case 'success':
                    $detailResult = [];
                    try {
                        $detailResult = $ipsService->getTransactionDetail($payment);
                    } catch (\Throwable $e) {
                        logger()->warning('ConnectIPS getTransactionDetail failed after successful validate: '.$e->getMessage());
                    }

                    $gatewayTxnId = $interpreted['gateway_txn_id'] ?? $txnId;
                    $payment->markAsSuccess($gatewayTxnId, $validateResult);

                    if (! empty($detailResult)) {
                        $payment->recordReconciliationDetails($detailResult);
                    }

                    if (! $payment->isMerchantCreditSuccess()) {
                        $payment->update([
                            'payment_status' => 'failed',
                            'gateway_response' => array_merge($validateResult, [
                                'reconciliation' => $detailResult,
                                'note' => 'creditStatus not in 000/999/DEFER',
                            ]),
                        ]);

                        return view('register.payment-failed', [
                            'event' => $event,
                            'payment' => $payment,
                            'reason' => 'Merchant credit status is not confirmed by gateway.',
                        ]);
                    }

                    $this->sendConfirmation($payment->registration, $event, $payment);

                    return redirect()->route('register.success', ['slug' => $slug])
                        ->with('guest_number', $payment->registration->guest_number)
                        ->with('qr_hash', $payment->registration->qr_hash);

                case 'pending':
                    logger()->info('ConnectIPS: payment incomplete, leaving initiated', [
                        'payment_id' => $payment->id,
                        'txn_id' => $txnId,
                    ]);

                    return view('register.payment-pending', [
                        'event' => $event,
                        'payment' => $payment,
                    ]);

                case 'failed':
                default:
                    $payment->markAsFailed($validateResult);

                    return view('register.payment-failed', [
                        'event' => $event,
                        'payment' => $payment,
                        'reason' => $interpreted['status_desc'] ?? 'Payment verification failed.',
                    ]);
            }
        } catch (\Throwable $e) {
            logger()->error('Payment validation failed: '.$e->getMessage());

            return view('register.payment-pending', [
                'event' => $event,
                'payment' => $payment,
            ]);
        }
    }

    public function paymentFailure(string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $txnId = request('TXNID');

        if (! $txnId) {
            return redirect()->route('register.show', $slug);
        }

        $payment = Payment::where('transaction_id', $txnId)->first();

        if (! $payment || $payment->event_id !== $event->id) {
            return redirect()->route('register.show', $slug);
        }

        $payment->update(['payment_status' => 'cancelled']);

        return view('register.payment-failed', [
            'event' => $event,
            'payment' => $payment,
            'reason' => 'Payment was cancelled or failed.',
        ]);
    }

    public function paymentRetry(string $slug, string $txnId)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        $oldPayment = Payment::where('transaction_id', $txnId)->firstOrFail();

        if ($oldPayment->event_id !== $event->id) {
            abort(403);
        }

        $reg = $oldPayment->registration;
        $category = $reg->category;

        if (! $category || ! $category->is_paid) {
            return redirect()->route('register.show', $slug);
        }

        $discountAmount = 0;
        $promoCode = $reg->promoCode;
        if ($promoCode && $promoCode->isValid()) {
            $effPrice = $this->getEffectivePrice($category);
            $discountAmount = $promoCode->calculateDiscount($effPrice);
        }

        return $this->initiatePayment($reg, $event, $category, $discountAmount, $promoCode);
    }

    private function initiatePaymentRedirect(Registration $reg, Event $event, ParticipantCategory $category, float $discountAmount = 0, ?PromoCode $promoCode = null, ?float $totalPrice = null)
    {
        $redirector = app(PaymentRedirector::class);
        $html = $redirector->initiate($reg, $event, $category, $discountAmount, $promoCode, $totalPrice);

        return response($html);
    }

    private function isDuplicate(int $eventId, string $email, string $phone): bool
    {
        $query = Registration::where('event_id', $eventId);

        if (! empty($email) && ! empty($phone)) {
            return $query->where(fn ($q) => $q->where('email', $email)->orWhere('phone', $phone))->exists();
        }

        if (! empty($email)) {
            return $query->where('email', $email)->exists();
        }

        if (! empty($phone)) {
            return $query->where('phone', $phone)->exists();
        }

        return false;
    }

    private function sendConfirmation(Registration $reg, Event $event, ?Payment $payment = null): void
    {
        try {
            $commService = new CommunicationService;

            if ($payment && $payment->isSuccessful()) {
                $commService->sendPaymentSuccess($reg, $event, $payment);
            } else {
                $commService->sendRegistrationConfirmation($reg, $event);
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send registration confirmation: '.$e->getMessage());
        }
    }

    private function getEffectivePrice(ParticipantCategory $category): float
    {
        if ($category->early_bird_price && $category->early_bird_until && now()->lt($category->early_bird_until)) {
            return (float) $category->early_bird_price;
        }

        return (float) $category->price;
    }
}
