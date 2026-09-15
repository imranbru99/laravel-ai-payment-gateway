<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Models\Device;

class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;
    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?string $navigationLabel = 'Devices & Listeners';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Device Registration & Android Pairing')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Device Name / Friendly Label')
                            ->placeholder('e.g. Office bKash Phone #1 (Samsung Galaxy)')
                            ->required(),

                        Forms\Components\TextInput::make('device_id')
                            ->label('Device ID')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Assigned automatically on creation.'),

                        Forms\Components\TextInput::make('pairing_token')
                            ->label('Pairing Token')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Enter this code in Truvo Pay Android app to pair.'),

                        Forms\Components\TextInput::make('secret_key')
                            ->label('HMAC Secret Key')
                            ->disabled()
                            ->dehydrated(false)
                            ->password()
                            ->revealable()
                            ->helperText('Cryptographic secret used by Android app to sign webhooks.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Device Active')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Device Name')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('device_id')
                    ->label('Device ID')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Health Status')
                    ->badge()
                    ->state(fn (Device $record) => $record->isOnline() ? 'Online' : 'Offline')
                    ->color(fn (string $state): string => match ($state) {
                        'Online' => 'success',
                        'Offline' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('battery_level')
                    ->label('Battery')
                    ->formatStateUsing(fn ($state) => $state !== null ? "{$state}%" : '—')
                    ->badge()
                    ->color(fn ($state) => $state === null ? 'gray' : ($state <= 15 ? 'danger' : ($state <= 35 ? 'warning' : 'success'))),

                Tables\Columns\TextColumn::make('network_type')
                    ->label('Network')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last Heartbeat')
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('pairModal')
                    ->label('Pairing Info')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn (Device $record) => "Pair Android App: {$record->name}")
                    ->modalDescription(fn (Device $record) => "Open Truvo Pay Android app on the phone with the SIM card installed. Enter Pairing Token: {$record->pairing_token} or Server URL: " . url('/api/v1/devices/pair'))
                    ->modalSubmitAction(false),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
