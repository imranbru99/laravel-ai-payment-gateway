<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Jobs\GenerateInvoicePdfJob;
use Truvo\Pay\Models\Transaction;
use Truvo\Pay\Services\FraudDetectionService;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Transactions';
    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Transaction Overview')
                    ->schema([
                        Forms\Components\TextInput::make('truvo_reference')
                            ->label('Truvo Reference')
                            ->disabled(),
                        Forms\Components\TextInput::make('merchant_order_id')
                            ->label('Merchant Order ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('amount')
                            ->disabled(),
                        Forms\Components\TextInput::make('currency')
                            ->disabled(),
                        Forms\Components\TextInput::make('status')
                            ->disabled(),
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Provider TrxID'),
                        Forms\Components\TextInput::make('customer_name')
                            ->disabled(),
                        Forms\Components\TextInput::make('customer_phone')
                            ->disabled(),
                        Forms\Components\TextInput::make('sender_number')
                            ->label('Sender Mobile Number'),
                        Forms\Components\TextInput::make('ai_confidence_score')
                            ->label('AI Confidence Score (%)')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('AI Verification & Fraud Reasoning')
                    ->schema([
                        Forms\Components\Textarea::make('ai_reasoning')
                            ->rows(3)
                            ->disabled(),
                        Forms\Components\KeyValue::make('fraud_flags')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('truvo_reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('merchant_order_id')
                    ->label('Order #')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gateway_key')
                    ->badge()
                    ->label('Gateway'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ai_approved' => 'success',
                        'manually_approved' => 'success',
                        'pending' => 'warning',
                        'flagged' => 'danger',
                        'rejected' => 'gray',
                        'refunded' => 'info',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('ai_confidence_score')
                    ->label('AI Score')
                    ->badge()
                    ->color(fn ($state) => $state >= 90 ? 'success' : ($state >= 70 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => $state ? "{$state}%" : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('TrxID')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Customer')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M, h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'ai_approved' => 'AI Approved',
                        'manually_approved' => 'Manually Approved',
                        'flagged' => 'Flagged for Review',
                        'rejected' => 'Rejected',
                        'refunded' => 'Refunded',
                    ]),
                Tables\Filters\SelectFilter::make('gateway_key')
                    ->label('Gateway'),
            ])
            ->actions([
                // 1-Click Human Approval
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Transaction $record) => in_array($record->status, ['pending', 'flagged']))
                    ->form([
                        Forms\Components\TextInput::make('transaction_id')
                            ->label('Gateway Transaction ID (TrxID)')
                            ->default(fn (Transaction $record) => $record->transaction_id)
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Approval Notes')
                            ->default('Manually approved by admin after verification.'),
                    ])
                    ->action(function (Transaction $record, array $data) {
                        $record->markAsApproved($data['transaction_id'], 100, $data['notes'], false);
                        dispatch(new GenerateInvoicePdfJob($record));

                        Notification::make()
                            ->title('Transaction Approved')
                            ->success()
                            ->send();
                    }),

                // 1-Click Reject
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Transaction $record) => in_array($record->status, ['pending', 'flagged']))
                    ->requiresConfirmation()
                    ->action(function (Transaction $record) {
                        $record->markAsRejected('Manually rejected by admin.');

                        Notification::make()
                            ->title('Transaction Rejected')
                            ->warning()
                            ->send();
                    }),

                // Explain Flag Reason via AI
                Tables\Actions\Action::make('aiExplain')
                    ->label('AI Insights')
                    ->icon('heroicon-o-sparkles')
                    ->color('purple')
                    ->modalHeading(fn ($record) => "AI Insights for #{$record->truvo_reference}")
                    ->modalDescription(function (Transaction $record, FraudDetectionService $fraudService) {
                        return $fraudService->explainFlaggedTransaction($record);
                    })
                    ->modalSubmitAction(false),

                Tables\Actions\ViewAction::make(),
            ]);
    }
}
