<?php

use Truvo\Pay\Facades\TruvoPay;

if (!function_exists('truvo_pay')) {
    /**
     * Helper accessor for Truvo Pay manager.
     */
    function truvo_pay(): \Truvo\Pay\TruvoPayManager
    {
        return app('truvo-pay');
    }
}
