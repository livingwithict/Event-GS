<?php

namespace App\Http\Controllers;

use App\Http\Requests\PublicRegistrationRequest;
use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\CommunicationService;
use App\Services\Payment\ConnectIPSService;

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
            return view('register.closed', [
                'event' => $event,
                'reason' => 'This event has reached its maximum capacity.',
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

        if (! $event->settingEnabled('enable_self_registration') || ! $event->isRegistrationOpen() || $event->isAtCapacity()) {
            return redirect()->route('register.show', $slug)
                ->with('error', 'Registration is no longer available.');
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

        $requiresPayment = $category?->is_paid && $event->settingEnabled('enable_payment');
        $paymentStatus = $requiresPayment ? 'pending' : null;

        $reg = Registration::create([
            'event_id' => $event->id,
            'category_id' => $categoryId ?: null,
            'registration_source' => 'self',
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
            'consented_at' => now(),
            'payment_status' => $paymentStatus,
        ]);

        if ($requiresPayment && $category->price) {
            return $this->initiatePayment($reg, $event, $category);
        }

        $this->sendConfirmation($reg, $event);

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
            $result = $ipsService->validatePayment($payment);

            if (($result['status'] ?? '') === 'SUCCESS') {
                $payment->markAsSuccess(
                    $result['gateway_txn_id'] ?? $txnId,
                    $result
                );

                $this->sendConfirmation($payment->registration, $event, $payment);

                return redirect()->route('register.success', ['slug' => $slug])
                    ->with('guest_number', $payment->registration->guest_number)
                    ->with('qr_hash', $payment->registration->qr_hash);
            }

            $payment->markAsFailed($result);

            return view('register.payment-failed', [
                'event' => $event,
                'payment' => $payment,
                'reason' => $result['statusDesc'] ?? 'Payment verification failed.',
            ]);
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

        return $this->initiatePayment($reg, $event, $category);
    }

    private function initiatePayment(Registration $reg, Event $event, ParticipantCategory $category)
    {
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'category_id' => $category->id,
            'amount_paisa' => (int) round($category->price * 100),
            'currency' => $category->currency ?? 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'payment_status' => 'pending',
        ]);

        $ipsService = new ConnectIPSService;
        $html = $ipsService->initiatePayment($payment);

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
}
