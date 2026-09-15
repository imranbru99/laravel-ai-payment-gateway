<?php

namespace Truvo\Pay\Filament\Widgets;

use Filament\Widgets\Widget;
use Truvo\Pay\Models\Device;

class DeviceHealthWidget extends Widget
{
    protected static string $view = 'truvo::filament.widgets.device-health';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function getDevicesProperty()
    {
        return Device::orderBy('last_seen_at', 'desc')->get();
    }
}
