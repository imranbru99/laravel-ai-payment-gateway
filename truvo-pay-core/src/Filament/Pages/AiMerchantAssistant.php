<?php

namespace Truvo\Pay\Filament\Pages;

use Filament\Pages\Page;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Services\FraudDetectionService;

class AiMerchantAssistant extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'AI Assistant';
    protected static ?int $navigationSort = 9;
    protected static string $view = 'truvo::filament.pages.ai-merchant-assistant';

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public string $userQuery = '';
    public array $chatHistory = [];

    public function mount()
    {
        $this->chatHistory = [
            [
                'role' => 'assistant',
                'message' => 'Hello! I am your Truvo Pay AI Merchant Assistant. You can ask me about flagged transactions, SMS match explanations, or daily security summaries (e.g. "Why was transaction #TRUVO-123 flagged?").',
            ],
        ];
    }

    public function sendMessage(FraudDetectionService $fraudService)
    {
        if (trim($this->userQuery) === '') {
            return;
        }

        $query = trim($this->userQuery);
        $this->chatHistory[] = ['role' => 'user', 'message' => $query];
        $this->userQuery = '';

        // Check if query mentions a transaction ID or reference
        $response = null;
        if (preg_match('/(?:TRUVO-[A-Za-z0-9]+|#?[0-9]+)/i', $query, $matches)) {
            $identifier = ltrim($matches[0], '#');
            $tx = Transaction::where('truvo_reference', $identifier)
                ->orWhere('merchant_order_id', $identifier)
                ->orWhere('id', is_numeric($identifier) ? (int)$identifier : 0)
                ->first();

            if ($tx) {
                $response = $fraudService->explainFlaggedTransaction($tx);
            }
        }

        if (!$response) {
            // General query to AI Hub
            if (class_exists(\ImranDevBd\AiHub\Facades\AIHub::class)) {
                try {
                    $systemPrompt = "You are Truvo Pay AI Merchant Assistant for an AI-verified payment gateway. Help the merchant with questions about payment verification, SMS auto-approval rules, chargebacks, or gateways.";
                    $aiRes = \ImranDevBd\AiHub\Facades\AIHub::prompt("{$systemPrompt}\nUser Query: {$query}")->generate();
                    $response = $aiRes->getText();
                } catch (\Throwable $e) {
                    $response = "I encountered an error querying the AI Hub: " . $e->getMessage();
                }
            } else {
                $response = "I can explain any transaction if you provide its Truvo Reference (e.g. TRUVO-XXXXXXXX) or Order ID.";
            }
        }

        $this->chatHistory[] = ['role' => 'assistant', 'message' => $response];
    }
}
