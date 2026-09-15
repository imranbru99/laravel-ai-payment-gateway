<?php

namespace Truvo\Pay\WhatsApp\Filament;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\WhatsApp\Models\WhatsAppLog;
use Truvo\Pay\WhatsApp\WhatsAppService;

class WhatsAppLogResource extends Resource
{
    protected static ?string $model = WhatsAppLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'WhatsApp Logs';
    protected static ?int $navigationSort = 9;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('recipient_phone')->label('Phone')->disabled(),
                Forms\Components\TextInput::make('provider')->label('Provider')->disabled(),
                Forms\Components\TextInput::make('direction')->disabled(),
                Forms\Components\TextInput::make('message_type')->label('Type')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\TextInput::make('message_id')->label('Provider Message ID')->disabled(),
                Forms\Components\Textarea::make('content')->label('Message Content')->rows(4)->columnSpanFull()->disabled(),
                Forms\Components\Textarea::make('error_message')->label('Error Details')->rows(2)->columnSpanFull()->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'outbound' => 'info',
                        'inbound' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('recipient_phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('message_type')
                    ->label('Type')
                    ->badge(),
                Tables\Columns\TextColumn::make('transaction.truvo_reference')
                    ->label('Transaction')
                    ->searchable()
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent', 'delivered', 'read' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('direction')
                    ->options([
                        'outbound' => 'Outbound',
                        'inbound' => 'Inbound',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options([
                        'meta' => 'Meta Cloud API',
                        'twilio' => 'Twilio',
                        'ultramsg' => 'UltraMsg',
                        'fake' => 'Simulator / Sandbox',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'sent' => 'Sent',
                        'delivered' => 'Delivered',
                        'read' => 'Read',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (WhatsAppLog $record) => $record->direction === 'outbound')
                    ->requiresConfirmation()
                    ->action(function (WhatsAppLog $record, WhatsAppService $service) {
                        $success = $service->sendText($record->recipient_phone, $record->content, $record->transaction);
                        if ($success) {
                            Notification::make()->title('Message Resent via WhatsApp')->success()->send();
                        } else {
                            Notification::make()->title('Resend Failed')->danger()->send();
                        }
                    }),
            ]);
    }
}
