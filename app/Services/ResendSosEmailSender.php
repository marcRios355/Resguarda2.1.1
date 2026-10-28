<?php

namespace App\Services;

use App\Mail\SosAlertNotification;
use App\Models\SosAlertDelivery;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class ResendSosEmailSender
{
    public function send(SosAlertDelivery $delivery): ?string
    {
        $apiKey = config('services.resend.key');

        if (config('services.resend.sos_provider') !== 'resend' || ! is_string($apiKey) || trim($apiKey) === '') {
            throw new SosEmailDeliveryException(false, 'provider_not_configured');
        }

        $payload = $this->requestPayload($delivery);

        try {
            $response = Http::asJson()
                ->withToken($apiKey)
                ->withHeaders(['Idempotency-Key' => 'sos-alert-delivery-'.$delivery->id])
                ->connectTimeout(5)
                ->timeout(20)
                ->post('https://api.resend.com/emails', $payload);
        } catch (ConnectionException) {
            throw new SosEmailDeliveryException(true, 'provider_unavailable', true);
        }

        if ($response->successful()) {
            $messageId = $response->json('id');

            return is_string($messageId) ? $messageId : null;
        }

        $status = $response->status();
        if ($status === 409) {
            $providerCode = $response->json('name');

            if ($providerCode === 'concurrent_idempotent_requests') {
                $this->logSafeFailure($delivery->id, $status, $providerCode);

                throw new SosEmailDeliveryException(true, $providerCode, true);
            }

            if ($providerCode === 'invalid_idempotent_request') {
                $this->logSafeFailure($delivery->id, $status, $providerCode);

                throw new SosEmailDeliveryException(false, $providerCode, true);
            }

            $this->logSafeFailure($delivery->id, $status, 'provider_conflict_unknown');

            throw new SosEmailDeliveryException(false, 'provider_conflict_unknown', true);
        }

        $retryable = in_array($status, [408, 429], true) || $status >= 500;
        $outcomeUncertain = $status === 408 || $status >= 500;
        $failureCode = $retryable ? 'provider_unavailable' : 'provider_rejected';

        $this->logSafeFailure($delivery->id, $status, $failureCode);

        throw new SosEmailDeliveryException(
            $retryable,
            $failureCode,
            $outcomeUncertain,
        );
    }

    /**
     * @return array{from: string, to: array<int, string>, subject: string, html: string, text: string}
     */
    private function requestPayload(SosAlertDelivery $delivery): array
    {
        return DB::transaction(function () use ($delivery): array {
            $lockedDelivery = SosAlertDelivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if (is_array($lockedDelivery->request_payload)) {
                return $lockedDelivery->request_payload;
            }

            $mailable = new SosAlertNotification($lockedDelivery->alert);
            $html = $mailable->render();
            $viewData = $mailable->buildViewData();
            $payload = [
                'from' => sprintf(
                    '%s <%s>',
                    config('mail.from.name'),
                    config('mail.from.address'),
                ),
                'to' => [$lockedDelivery->recipient_email],
                'subject' => $mailable->envelope()->subject,
                'html' => $html,
                'text' => View::make('mail.sos-alert-text', $viewData)->render(),
            ];

            $lockedDelivery->forceFill(['request_payload' => $payload])->save();

            return $payload;
        });
    }

    private function logSafeFailure(int $deliveryId, int $status, string $failureCode): void
    {
        Log::warning('SOS email provider request did not return an accepted response.', [
            'delivery_id' => $deliveryId,
            'http_status' => $status,
            'failure_code' => $failureCode,
        ]);
    }
}
