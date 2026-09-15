<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Models\Settlement;

class SettlementResource extends Resource
{
    protected static ?string $model = Settlement::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationLabel = 'Settlements & Payouts';
    protected static ?int $navigationSort = 6;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('settlement_reference')->disabled(),
                Forms\Components\TextInput::make('total_collected')->disabled(),
                Forms\Components\TextInput::make('total_fees')->disabled(),
                Forms\Components\TextInput::make('net_payout')->disabled(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing Payout',
                        'paid' => 'Settled / Paid',
                    ])->required(),
                Forms\Components\TextInput::make('payout_method')
                    ->placeholder('e.g. Bank Wire / BEFTN'),
                Forms\Components\TextInput::make('payout_reference')
                    ->placeholder('Bank Transaction Narration / Reference'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('settlement_reference')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('merchant.name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('period_start')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('period_end')
                    ->date('d M Y'),
                Tables\Columns\TextColumn::make('total_collected')
                    ->money('BDT'),
                Tables\Columns\TextColumn::make('net_payout')
                    ->money('BDT')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'processing' => 'info',
                        'pending' => 'warning',
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }
}
