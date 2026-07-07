# ConnectIPS Integration

This project integrates with [ConnectIPS Gateway](https://doc.connectips.com/docs_gw/intro) (NCHL) to accept online payments for event registrations. ConnectIPS lets attendees pay directly from their bank accounts at any NCHL-participating bank.

The integration code lives in `app/Services/Payment/ConnectIPSService.php` and `app/Services/Payment/PaymentRedirector.php`.

## 1. Enrollment flow

Before any code can talk to ConnectIPS, the merchant must be enrolled with NCHL through their **bank** (NCHL does not enroll merchants directly).

1. ICT Foundation approaches its bank (e.g. NCHL member bank) requesting ConnectIPS Gateway enrollment for the event-management application.
2. Bank performs KYC, then submits the merchant details to NCHL for technical enrollment.
3. NCHL activates the creditor/merchant and emails the designated contact with:
   - **Merchant ID** (integer)
   - **App ID** (string, 15 chars max)
   - **App Password** (basic auth password for the API)
   - **App Name** (string, 30 chars max — display only)
   - Private key in **`.pfx`** (PKCS#12) or **`.pem`** format
   - Optionally: a UAT test user
4. ICT Foundation provides NCHL with two static redirect URLs:
   - **Success URL:** `https://{APP_URL}/event/{slug}/payment/success`
   - **Failure URL:** `https://{APP_URL}/event/{slug}/payment/failure`
   - NCHL only appends `?TXNID=…` to whichever URL — no other params are added.
5. Production vs UAT are separate enrollments. UAT uses `https://uat.connectips.com:7443`; production uses `https://login.connectips.com`.

## 2. Environment variables

Configure in `.env` (per env file in `AGENTS.md` hierarchy):

```dotenv
CONNECTIPS_BASE_URL=https://uat.connectips.com:7443
CONNECTIPS_MERCHANT_ID=550
CONNECTIPS_APP_ID=MER-550-APP-1
CONNECTIPS_APP_NAME=Test Merchant
CONNECTIPS_APP_PASSWORD=********
CONNECTIPS_PRIVATE_KEY_PATH=/secure/path/CREDITOR.pem
CONNECTIPS_PRIVATE_KEY_PASSPHRASE=
CONNECTIPS_PRIVATE_KEY_FORMAT=pem    # or "pfx" / "p12"
CONNECTIPS_SUCCESS_URL=https://app.test/event/{slug}/payment/success
CONNECTIPS_FAILURE_URL=https://app.test/event/{slug}/payment/failure
```

| Variable | Notes |
|---|---|
| `CONNECTIPS_BASE_URL` | UAT = `https://uat.connectips.com:7443`. Production = `https://login.connectips.com` (no port). |
| `CONNECTIPS_PRIVATE_KEY_PATH` | Absolute path on the server, readable by the PHP-FPM user. |
| `CONNECTIPS_PRIVATE_KEY_FORMAT` | `pem` (default) or `pfx`/`p12`. NCHL typically delivers `.pfx`. |
| `CONNECTIPS_PRIVATE_KEY_PASSPHRASE` | Passphrase for the private key, if any. |
| `CONNECTIPS_SUCCESS_URL` / `CONNECTIPS_FAILURE_URL` | Informational only — NCHL stores these separately at enrollment. Must match the registered static URLs. |

After changing env vars: `php artisan config:clear` (mandatory per AGENTS.md).

## 3. Private key formats

NCHL's token signing algorithm is `SHA256withRSA`: build the token string, hash it with SHA-256, sign with the private key, base64 the result.

The service supports two private-key formats:

### `.pem` (default)

A plain RSA private key. `openssl_pkey_get_private($pem, $passphrase)` loads it directly.

```bash
# Extract .pem from .pfx if your bank delivered the .pfx:
openssl pkcs12 -in CREDITOR.pfx -nocerts -nodes -out CREDITOR.pem
```

### `.pfx` / `.p12`

A PKCS#12 keystore. `openssl_pkey_get_private` cannot load `.pfx` directly; the service uses `openssl_pkcs12_read` to extract the PEM-formatted key in memory. Set `CONNECTIPS_PRIVATE_KEY_FORMAT=pfx`.

If the key file is missing or unreadable, `signWithPrivateKey()` throws a `RuntimeException` (no silent fallback). The previous behavior of `base64_encode(hash('sha256', $message, true))` was a misleading placeholder — the gateway would never accept those tokens.

## 4. UAT testing checklist

NCHL provides a test user in UAT. Use it to walk the flow end-to-end before going live.

1. Confirm `CONNECTIPS_BASE_URL=https://uat.connectips.com:7443`.
2. Pick a paid `ParticipantCategory` on a published event with `settings.enable_payment = true`.
3. Submit the public registration form. The browser should auto-submit to `https://uat.connectips.com:7443/connectipswebgw/loginpage`.
4. Log in with the NCHL test user, choose a bank, enter the captcha and OTP.
5. Confirm the user is redirected to your `payment/success?TXNID=…` URL.
6. The success handler calls `validateTxn` then `getTxnDetail`, then marks the payment as `success` if both confirm it.
7. Verify the `payments` row has `gateway_txn_id`, `batch_id`, `debit_bank_code`, `charge_amount_paisa`, and `credit_status` populated.
8. Test the **Return** button (redirects to `failure` URL), the **Return to Creditor Site** button (same), and clicking **Cancel** at OTP. All should land the payment in `initiated` and be cleared by `payment:expire` after 30 minutes.

## 5. Common errors

| HTTP / Response | Meaning | Fix |
|---|---|---|
| `403` after entering captcha | Browser is blocking ConnectIPS cookies. | Use a real browser, not headless. Ensure third-party cookies are allowed for `connectips.com`. |
| `403` after entering OTP | `successURL` / `failureURL` not registered with NCHL. | Confirm the URLs you sent NCHL match the deployed app. |
| `401` from `validatetxn` / `gettxndetail` | Wrong basic-auth credentials. | Verify `CONNECTIPS_APP_ID` (username) and `CONNECTIPS_APP_PASSWORD` (password). |
| `403` "Dear customer, it seems you are trying to make multiple gateway payments at once..." | Duplicate `TXNID` in the same session. | Our `transaction_id` generator uses base36 timestamp + 7 random chars; collisions are astronomically unlikely. If this happens, check the `payments` table for the TXNID. |
| Field length errors (TXNID > 20, REMARKS > 50, PARTICULARS > 100) | Non-spec-compliant input. | Service clips all fields. If you see this, something bypassed the service — log and investigate. |
| `validateTxn` returns `ERROR + TRANSACTION NOT FOUND` | Customer never reached the gateway. | Payment marked `failed`. User can retry. |
| `validateTxn` returns `ERROR + TRANSACTION INCOMPLETE` | Customer closed the page before entering OTP. | Payment left as `initiated`; `payment:expire` scheduler clears it after `expires_at` (default 30 min). |
| `validateTxn` returns `SUCCESS` but `creditStatus` is not `000`/`999`/`DEFER` | Gateway-side credit failed. | Payment marked `failed`. The full `gettxndetail` response is stored in `gateway_response` for reconciliation. |
| Amount mismatch on validate | Gateway's `txnAmt` differs from our `payments.amount_paisa`. | Logged as a warning. Payment is **not** auto-marked — manual review needed. |
| Signature verification failure | Wrong key or wrong message format. | Re-check the private key. The token string is `MERCHANTID=…,APPID=…,APPNAME=…,TXNID=…,TXNDATE=…,TXNCRNCY=…,TXNAMT=…,REFERENCEID=…,REMARKS=…,PARTICULARS=…,TOKEN=TOKEN` (no spaces). |

## 6. Reconciliation workflow

The ConnectIPS service captures enough data to reconcile against NCHL's merchant portal:

1. Filament → **Finance → Payments** shows every payment with status, gateway Txn ID, batch ID, debit bank, and charge.
2. To re-query a stuck payment, click **Re-validate**. This calls `validatetxn` then `gettxndetail` and updates the row + the activity log.
3. For reconciliation against NCHL's records, export the payment list as CSV and cross-reference by `batch_id` (NCHL's batch) and `gateway_txn_id` (NCHL's transaction ID).
4. The view page (click any payment) shows the raw `gateway_response` JSON in a collapsible section.
5. To issue a refund: mark the payment **Refund** in Filament. This sets `payment_status = refunded` locally; the actual money movement happens in NCHL's merchant portal (the Gateway API does not expose a refund endpoint).

## 7. Field-length reference

| Field | Max length | Enforced where |
|---|---|---|
| `TXNID` | 20 | `Payment::generateTransactionId()` + column constraint |
| `TXNDATE` | 10 (DD-MM-YYYY) | `ConnectIPSService::initiatePayment()` |
| `TXNCRNCY` | 3 (e.g. `NPR`) | `payments.currency` |
| `TXNAMT` | 20 (integer, paisa) | `payments.amount_paisa` (bigint) |
| `REFERENCEID` | 20 | `ConnectIPSService::clip()` |
| `REMARKS` | 50 | `ConnectIPSService::clip()` |
| `PARTICULARS` | 100 | `ConnectIPSService::clip()` |
| `TOKEN` | 512 | RSA-2048 signature base64 ≈ 344 chars — always fits |

## 8. Code map

| File | Role |
|---|---|
| `app/Services/Payment/ConnectIPSService.php` | Token signing, initiate/validate/getdetail API calls, result interpretation |
| `app/Services/Payment/PaymentRedirector.php` | Shared flow for public + onsite registrations: price → tax → discount → create Payment → auto-submit |
| `app/Http/Controllers/PublicRegistrationController.php` | Public self-registration flow + success/failure callbacks |
| `app/Http/Controllers/OnsiteRegistrationController.php` | Onsite desk flow with `payment_method` selector (gateway / cash / none) |
| `app/Models/Payment.php` | Status enum, `markAsSuccess/Failed/Refunded`, `recordReconciliationDetails`, `isMerchantCreditSuccess` |
| `app/Console/Commands/ExpirePayments.php` | Scheduled every 5 min — clears stuck `initiated`/`pending` past `expires_at` |
| `app/Filament/Resources/PaymentResource.php` | Filament list with row actions: Re-validate, Verify, Mark Invalid, Refund, Invoice |
| `app/Filament/Resources/PaymentResource/Pages/ViewPayment.php` | View page with reconciliation details + raw response |
| `config/connectips.php` | All env-driven config |
| `database/migrations/2026_06_22_120000_shrink_transaction_id_to_20_chars.php` | TXNID column → `varchar(20)` |
| `database/migrations/2026_06_22_121000_add_reconciliation_columns_to_payments.php` | `batch_id`, `debit_bank_code`, `charge_amount_paisa`, `credit_status` |
| `tests/Unit/Services/ConnectIPSServiceTest.php` | Token format, signature round-trip, field clipping, key format errors |
| `tests/Feature/PaymentFlowTest.php` | Status interpretation, INCOMPLETE leaves `initiated`, reconcile, expire scheduler |
| `tests/Feature/OnsitePaymentTest.php` | Onsite gateway/cash/none flows |

## 9. Out of scope

- **IPN / server-to-server webhooks** — NCHL's Gateway API only supports redirect + manual `validatetxn` from the merchant. There is no push notification.
- **Multi-currency** — ConnectIPS supports NPR only in the Gateway API.
- **Refund API** — refunds are initiated manually in the NCHL merchant portal.
- **Cancelling a pending transaction** — once a TXNID is posted, the customer must either complete or abandon it. We have no `cancel` API.
