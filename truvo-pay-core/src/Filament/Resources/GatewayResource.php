<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Credentials\CredentialVault;
use Truvo\Pay\Facades\TruvoPay;
use Truvo\Pay\Models\GatewayConfig;

class GatewayResource extends Resource
{
    protected static ?string $model = GatewayConfig::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Gateways';
    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        $allDrivers = TruvoPay::registry()->all();
        $driverOptions = [];
        foreach ($allDrivers as $key => $driverClass) {
            $driver = app($driverClass);
            $driverOptions[$key] = $driver->getName() . ($driver->isManual() ? ' [Manual/AI Verification]' : ' [Direct API]');
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Gateway Configuration')
                    ->schema([
                        Forms\Components\Select::make('driver_key')
                            ->label('Payment Provider')
                            ->options($driverOptions)
                            ->required()
                            ->live()
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('display_name')
                            ->label('Checkout Display Name')
                            ->required()
                            ->placeholder('e.g. Pay with bKash (Personal)'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active on Checkout')
                            ->default(true),

                        Forms\Components\Select::make('mode')
                            ->options([
                                'sandbox' => 'Sandbox / Test Mode',
                                'live' => 'Live Production',
                            ])
                            ->default('sandbox')
                            ->required(),

                        Forms\Components\TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->label('Display Priority Order')
                            ->helperText('Lower numbers appear first on checkout.'),

                        Forms\Components\TagsInput::make('supported_currencies')
                            ->label('Supported Currencies')
                            ->default(['BDT'])
                            ->placeholder('Add currency code (e.g. BDT, USD)'),

                        Forms\Components\Textarea::make('instructions')
                            ->label('Checkout Customer Instructions')
                            ->rows(3)
                            ->helperText('Shown to customers when this payment method is selected on checkout.'),
                    ])->columns(2),

                // Dynamic Credential Fields Section generated automatically from Driver::getCredentialFields()
                Forms\Components\Section::make('Provider Credentials & Security Vault')
                    ->description('Credentials are automatically encrypted at rest using AES-256 and masked on display.')
                    ->schema(function (Get $get, ?GatewayConfig $record) {
                        $driverKey = $get('driver_key') ?? $record?->driver_key;
                        if (!$driverKey) {
                            return [Forms\Components\Placeholder::make('info')->content('Select a payment provider above to load required credential fields.')];
                        }

                        $driver = TruvoPay::registry()->get($driverKey);
                        if (!$driver) {
                            return [];
                        }

                        $fields = $driver->getCredentialFields();
                        return CredentialVault::toFilamentComponents($fields);
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('driver_key')
                    ->badge()
                    ->color(fn ($record) => $record->getDriver()?->isManual() ? 'warning' : 'primary'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('mode')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'sandbox' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('priority')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataBeforeFill(function (array $data, GatewayConfig $record): array {
                        $creds = $record->getDecryptedCredentials();
                        $fields = $record->getDriver()?->getCredentialFields() ?? [];
                        $data['credentials'] = CredentialVault::mask($creds, $fields);
                        return $data;
                    })
                    ->mutateFormDataBeforeSave(function (array $data, GatewayConfig $record): array {
                        if (isset($data['credentials']) && is_array($data['credentials'])) {
                            $existing = $record->getDecryptedCredentials();
                            $merged = CredentialVault::mergeWithExisting($data['credentials'], $existing);
                            $data['credentials'] = CredentialVault::encrypt($merged);
                        }
                        return $data;
                    }),

                // "Test Connection" button pinging provider
                Tables\Actions\Action::make('testConnection')
                    ->label('Test Connection')
                    ->icon('heroicon-o-signal')
                    ->color('info')
                    ->action(function (GatewayConfig $record) {
                        $driver = $record->getDriver();
                        if (!$driver) {
                            Notification::make()->title('Driver not loaded')->danger()->send();
                            return;
                        }

                        $result = $driver->testConnection();
                        if ($result) {
                            Notification::make()
                                ->title('Connection Successful')
                                ->body("{$record->display_name} connected and verified successfully.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Connection Failed')
                                ->body("Failed to verify credentials with {$record->display_name}. Please verify configuration.")
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
