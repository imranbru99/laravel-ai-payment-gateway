<?php

namespace Truvo\Pay\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Truvo\Pay\Services\FraudDetectionService;
use Truvo\Pay\Services\SmsVerificationService;

class SmsExtractionTest extends TestCase
{
    public function test_can_extract_bkash_sms_fields(): void
    {
        $service = new SmsVerificationService(new FraudDetectionService());

        $bkashSms = "You have received Tk 1,500.00 from 01712345678. Fee Tk 0.00. Balance Tk 5,420.50. TrxID BL56XYZ123 at 16/09/2026 14:30";
        $extracted = $service->extractDataWithAi($bkashSms, 'bKash');

        $this->assertEquals(1500.00, $extracted->amount);
        $this->assertEquals('BL56XYZ123', $extracted->transactionId);
        $this->assertEquals('bKash', $extracted->gateway);
        $this->assertEquals('01712345678', $extracted->senderNumber);
    }

    public function test_can_extract_nagad_sms_fields(): void
    {
        $service = new SmsVerificationService(new FraudDetectionService());

        $nagadSms = "Money Received. Amount: Tk 2,400.00. Sender: 01987654321. TxnID: 76HG89NM. New Balance: Tk 8,900.00.";
        $extracted = $service->extractDataWithAi($nagadSms, 'NAGAD');

        $this->assertEquals(2400.00, $extracted->amount);
        $this->assertEquals('76HG89NM', $extracted->transactionId);
        $this->assertEquals('Nagad', $extracted->gateway);
        $this->assertEquals('01987654321', $extracted->senderNumber);
    }
}
