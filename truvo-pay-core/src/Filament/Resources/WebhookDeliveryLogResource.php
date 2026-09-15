<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Models\WebhookDeliveryLog;
use Truvo\Pay\Services\WebhookDispatcherService;

class WebhookDeliveryLogResource extends Resource
{
    protected static ?string $model = WebhookDeliveryLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';
    protected static ?string $navigationLabel = 'Webhook Deliveries';
    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('event_name')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('response_status')->label('HTTP Response Code')->disabled(),
                Forms\Components\Textarea::make('response_body')->rows(4)->disabled(),
                Forms\Components\KeyValue::make('payload')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_name')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('transaction.truvo_reference')
                    ->label('Transaction')
                    ->searchable(),
                Tables\Columns\TextColumn::make('merchant.name')
                    ->label('Merchant'),
                Tables\Columns\TextColumn::make('response_status')
                    ->label('HTTP Status')
                    ->badge()
                    ->color(fn ($state) => ($state >= 200 && $state < 300) ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('attempt_number')
                    ->label('Attempts')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y, h:i:s A')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('replay')
                    ->label('Replay Webhook')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (WebhookDeliveryLog $record, WebhookDispatcherService $dispatcher) {
                        $success = $dispatcher->replay($record);
                        if ($success) {
                            Notification::make()->title('Webhook Delivered Successfully')->success()->send();
                        } else {
                            Notification::make()->title('Webhook Replay Failed')->danger()->send();
                        }
                    }),
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
