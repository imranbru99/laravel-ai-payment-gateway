<?php

namespace Truvo\Pay\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Truvo\Pay\Jobs\GenerateInvoicePdfJob;
use Truvo\Pay\Models\Transaction;

class AiReviewQueue extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-eye';
    protected static ?string $navigationLabel = 'AI Review Queue';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'truvo::filament.pages.ai-review-queue';

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Transaction::where('status', 'flagged')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getFlaggedTransactionsProperty()
    {
        return Transaction::with(['matchedSmsLogs', 'merchant'])
            ->where('status', 'flagged')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function approveTransaction(int $transactionId, ?string $trxId = null)
    {
        $tx = Transaction::findOrFail($transactionId);
        $finalTrxId = $trxId ?? $tx->transaction_id ?? ('MANUAL_' . time());

        $tx->markAsApproved($finalTrxId, 100, 'Manually approved from AI Review Queue', false);
        dispatch(new GenerateInvoicePdfJob($tx));

        Notification::make()
            ->title("Transaction #{$tx->truvo_reference} Approved")
            ->success()
            ->send();
    }

    public function rejectTransaction(int $transactionId)
    {
        $tx = Transaction::findOrFail($transactionId);
        $tx->markAsRejected('Rejected by administrator from AI Review Queue.');

        Notification::make()
            ->title("Transaction #{$tx->truvo_reference} Rejected")
            ->warning()
            ->send();
    }
}
