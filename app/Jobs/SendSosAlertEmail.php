<?php

namespace App\Jobs;

use App\Models\SosAlertDelivery;
use App\Services\ResendSosEmailSender;
use App\Services\SosEmailDeliveryException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendSosAlertEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 48;

    /** @var array<int, int> */
    public array $backoff = [30, 60, 120, 300, 600, 900, 1800];

    public int $timeout = 30;

    public int $uniqueFor = 82800;

    private const IDEMPOTENCY_WINDOW_HOURS = 23;

    public function __construct(public readonly int $deliveryId) {}

    public function uniqueId(): string
    {
        return 'sos-alert-delivery-'.$this->deliveryId;
    }

    public function handle(ResendSosEmailSender $sender): void
    {
        $delivery = DB::transaction(function (): ?SosAlertDelivery {
            $delivery = SosAlertDelivery::query()
                ->lockForUpdate()
                ->findOrFail($this->deliveryId);

            if ($delivery->status !== 'pending') {
                return null;
            }

            if ($delivery->first_attempted_at?->lte(now()->subHours(self::IDEMPOTENCY_WINDOW_HOURS))) {
                $this->markExpired($delivery);

                return null;
            }

            $delivery->forceFill([
                'first_attempted_at' => $delivery->first_attempted_at ?? now(),
                'attempt_count' => $delivery->attempt_count + 1,
                'failure_code' => $delivery->failure_code === 'outcome_unknown'
                    ? 'outcome_unknown'
                    : 'attempt_in_progress',
            ])->save();

            return $delivery->load('alert');
        });

        if (! $delivery) {
            return;
        }

        try {
            $providerMessageId = $sender->send($delivery);
        } catch (SosEmailDeliveryException $exception) {
            if ($exception->retryable) {
                $this->recordRetryableFailure($delivery->id, $exception);

                throw $exception;
            }

            $this->markDeliveryTerminal(
                $delivery->id,
                $exception->failureCode,
                $exception->outcomeUncertain,
            );

            return;
        }

        DB::transaction(function () use ($delivery, $providerMessageId): void {
            $lockedDelivery = SosAlertDelivery::query()
                ->lockForUpdate()
                ->findOrFail($delivery->id);

            if ($lockedDelivery->status !== 'accepted') {
                $lockedDelivery->forceFill([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'provider_message_id' => $providerMessageId,
                    'failure_code' => null,
                ])->save();
            }

            $lockedDelivery->alert->refreshDeliveryStatus();
        });
    }

    public function failed(?Throwable $exception): void
    {
        $failureCode = $exception instanceof SosEmailDeliveryException
            ? $exception->failureCode
            : 'retry_exhausted';

        $this->markDeliveryTerminal(
            $this->deliveryId,
            $failureCode,
            $exception instanceof SosEmailDeliveryException && $exception->outcomeUncertain,
            true,
        );
    }

    private function recordRetryableFailure(int $deliveryId, SosEmailDeliveryException $exception): void
    {
        DB::transaction(function () use ($deliveryId, $exception): void {
            $delivery = SosAlertDelivery::query()
                ->lockForUpdate()
                ->find($deliveryId);

            if (! $delivery || $delivery->status !== 'pending') {
                return;
            }

            if ($exception->outcomeUncertain) {
                $delivery->forceFill(['failure_code' => 'outcome_unknown'])->save();
            } elseif ($delivery->failure_code !== 'outcome_unknown') {
                $delivery->forceFill(['failure_code' => $exception->failureCode])->save();
            }
        });
    }

    private function markDeliveryTerminal(
        int $deliveryId,
        string $failureCode,
        bool $outcomeUncertain,
        bool $attemptsExhausted = false,
    ): void {
        DB::transaction(function () use ($deliveryId, $failureCode, $outcomeUncertain, $attemptsExhausted): void {
            $delivery = SosAlertDelivery::query()
                ->lockForUpdate()
                ->find($deliveryId);

            if (! $delivery || $delivery->status !== 'pending') {
                return;
            }

            $isUncertain = $outcomeUncertain
                || $delivery->failure_code === 'outcome_unknown'
                || ($attemptsExhausted && $delivery->failure_code === 'attempt_in_progress');
            $status = $isUncertain ? 'uncertain' : 'failed';
            $storedFailureCode = $isUncertain
                ? ($outcomeUncertain ? $failureCode : 'outcome_unknown')
                : ($attemptsExhausted ? 'retry_exhausted' : $failureCode);

            $delivery->forceFill([
                'status' => $status,
                'failure_code' => $storedFailureCode,
            ])->save();

            $delivery->alert->refreshDeliveryStatus();
        });
    }

    private function markExpired(SosAlertDelivery $delivery): void
    {
        $isUncertain = in_array($delivery->failure_code, ['outcome_unknown', 'attempt_in_progress'], true);

        $delivery->forceFill([
            'status' => $isUncertain ? 'uncertain' : 'failed',
            'failure_code' => $isUncertain ? 'idempotency_window_expired_uncertain' : 'idempotency_window_expired',
        ])->save();
        $delivery->alert->refreshDeliveryStatus();
    }
}
