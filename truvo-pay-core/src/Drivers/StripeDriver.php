<?php

namespace Truvo\Pay\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Truvo\Pay\Credentials\CredentialField;
use Truvo\Pay\Data\PaymentRequest;
use Truvo\Pay\Data\PaymentResponse;
use Truvo\Pay\Data\RefundResponse;
use Truvo\Pay\Data\WebhookResult;

class StripeDriver extends AbstractGatewayDriver
{
    public function getKey(): string
    {
        return 'stripe';
    }

    public function getName(): string
    {
        return 'Stripe';
    }

    public function getCredentialFields(): array
    {
        return [
            CredentialField::make('publishable_key', 'Publishable Key')
                ->placeholder('pk_live_... or pk_test_...')
                ->required(true)
                ->secret(false),
            CredentialField::make('secret_key', 'Secret Key')
                ->placeholder('sk_live_... or sk_test_...')
                ->required(true)
                ->secret(true),
            CredentialField::make('webhook_secret', 'Webhook Signing Secret')
                ->placeholder('whsec_...')
                ->required(false)
                ->secret(true),
        ];
    }

    public function charge(PaymentRequest $request): PaymentResponse
    {
        $secretKey = $this->credentials['secret_key'] ?? '';
        if (empty($secretKey)) {
            return PaymentResponse::failed('Stripe secret key is missing.');
        }

        // Zero decimal currencies handling
        $zeroDecimalCurrencies = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];
        $amount = in_array(strtoupper($request->currency), $zeroDecimalCurrencies)
            ? (int) $request->amount
            : (int) round($request->amount * 100);

        try {
            $response = Http::withToken($secretKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower($request->currency),
                            'unit_amount' => $amount,
                            'product_data' => [
                                'name' => "Order #{$request->merchantOrderId}",
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => $request->redirectUrl ? ($request->redirectUrl . (str_contains($request->redirectUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}&reference=' . $request->truvoReference) : url('/'),
                    'cancel_url' => $request->redirectUrl ? ($request->redirectUrl . (str_contains($request->redirectUrl, '?') ? '&' : '?') . 'cancelled=1&reference=' . $request->truvoReference) : url('/'),
                    'client_reference_id' => $request->truvoReference,
                    'customer_email' => $request->customerEmail,
                    'metadata' => array_merge($request->metadata, [
                        'truvo_reference' => $request->truvoReference,
                        'order_id' => $request->merchantOrderId,
                    ]),
                ]);

            if ($response->successful()) {
                $session = $response->json();
                return PaymentResponse::redirect($session['url'], $session['id'], $session);
            }

            $error = $response->json('error.message', 'Stripe checkout session creation failed.');
            return PaymentResponse::failed($error, $response->json() ?? []);
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function verify(string $transactionReference): PaymentResponse
    {
        $secretKey = $this->credentials['secret_key'] ?? '';
        if (empty($secretKey)) {
            return PaymentResponse::failed('Stripe secret key missing.');
        }

        try {
            $response = Http::withToken($secretKey)
                ->get("https://api.stripe.com/v1/checkout/sessions/{$transactionReference}");

            if ($response->successful()) {
                $session = $response->json();
                if ($session['payment_status'] === 'paid') {
                    $paymentIntentId = $session['payment_intent'] ?? $transactionReference;
                    return PaymentResponse::successful($paymentIntentId, $session);
                }
                return PaymentResponse::failed("Stripe payment status: {$session['payment_status']}", $session);
            }

            return PaymentResponse::failed($response->json('error.message', 'Stripe verification failed.'));
        } catch (\Throwable $e) {
            return PaymentResponse::failed($e->getMessage());
        }
    }

    public function executeRefund(string $gatewayTransactionId, float $amount, string $currency, ?string $reason = null): RefundResponse
    {
        $secretKey = $this->credentials['secret_key'] ?? '';
        if (empty($secretKey)) {
            return RefundResponse::failed('Stripe secret key missing.');
        }

        try {
            $payload = [
                'amount' => (int) round($amount * 100),
            ];

            // If ID starts with cs_ (checkout session), we would look up payment intent, or if it's pi_
            if (str_starts_with($gatewayTransactionId, 'pi_')) {
                $payload['payment_intent'] = $gatewayTransactionId;
            } elseif (str_starts_with($gatewayTransactionId, 'ch_')) {
                $payload['charge'] = $gatewayTransactionId;
            } else {
                $payload['payment_intent'] = $gatewayTransactionId;
            }

            if ($reason) {
                $payload['metadata'] = ['reason' => $reason];
            }

            $response = Http::withToken($secretKey)
                ->asForm()
                ->post('https://api.stripe.com/v1/refunds', $payload);

            if ($response->successful()) {
                $refund = $response->json();
                return RefundResponse::succeeded($refund['id'], $amount, $refund);
            }

            return RefundResponse::failed($response->json('error.message', 'Stripe refund failed.'));
        } catch (\Throwable $e) {
            return RefundResponse::failed($e->getMessage());
        }
    }

    public function webhookHandler(Request $request): WebhookResult
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = $this->credentials['webhook_secret'] ?? null;

        $event = json_decode($payload, true);
        if (!$event) {
            return WebhookResult::ignored('Invalid JSON payload');
        }

        $eventType = $event['type'] ?? '';

        if ($eventType === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $ref = $session['client_reference_id'] ?? ($session['metadata']['truvo_reference'] ?? null);
            $paymentIntent = $session['payment_intent'] ?? $session['id'];

            if ($ref) {
                return WebhookResult::handled($ref, 'paid', $paymentIntent, $event);
            }
        }

        return WebhookResult::ignored("Unhandled Stripe event: {$eventType}");
    }

    public function testConnection(): bool
    {
        $secretKey = $this->credentials['secret_key'] ?? '';
        if (empty($secretKey)) {
            return false;
        }

        try {
            $response = Http::withToken($secretKey)
                ->timeout(5)
                ->get('https://api.stripe.com/v1/balance');

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
