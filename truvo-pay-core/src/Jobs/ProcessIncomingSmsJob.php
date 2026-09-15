<?php

namespace Truvo\Pay\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Truvo\Pay\Models\Device;
use Truvo\Pay\Services\SmsVerificationService;

class ProcessIncomingSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public Device $device,
        public string $senderAddress,
        public string $rawBody,
        public string $receivedAt
    ) {}

    public function handle(SmsVerificationService $verificationService): void
    {
        $verificationService->processIncomingSms(
            $this->device,
            $this->senderAddress,
            $this->rawBody,
            $this->receivedAt
        );
    }
}
