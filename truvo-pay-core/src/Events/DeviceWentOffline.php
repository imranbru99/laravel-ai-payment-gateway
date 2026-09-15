<?php

namespace Truvo\Pay\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Truvo\Pay\Models\Device;

class DeviceWentOffline
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Device $device
    ) {}
}
