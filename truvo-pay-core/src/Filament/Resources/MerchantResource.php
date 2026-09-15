<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Models\Merchant;

class MerchantResource extends Resource
{
    protected static ?string $model = Merchant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationLabel = 'Merchants';
    protected static ?int $navigationSort = 4;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Merchant Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('slug')
                            ->required(),
                        Forms\Components\Select::make('kyc_status')
                            ->options([
                                'pending' => 'Pending Verification',
                                'approved' => 'Approved (Active)',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),

                Forms\Components\Section::make('Webhook Integration')
                    ->schema([
                        Forms\Components\TextInput::make('webhook_url')
                            ->url()
                            ->label('Merchant Webhook URL')
                            ->placeholder('https://merchant-shop.com/truvo-webhook'),
                        Forms\Components\TextInput::make('webhook_secret')
                            ->label('Webhook Signing Secret')
                            ->password()
                            ->revealable()
                            ->default(fn () => 'whsec_' . bin2hex(random_bytes(16))),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('kyc_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('generateApiKey')
                    ->label('Generate API Key')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\Select::make('mode')
                            ->options(['live' => 'Live Production', 'test' => 'Test Mode'])
                            ->default('live')
                            ->required(),
                    ])
                    ->action(function (Merchant $record, array $data) {
                        $keyData = $record->createApiKey($data['mode']);
                        Notification::make()
                            ->title('API Credentials Created')
                            ->body("API Key: {$keyData['api_key']}\nSecret: {$keyData['secret']}\n(Copy secret now; it won't be shown again)")
                            ->persistent()
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ]);
    }
}
