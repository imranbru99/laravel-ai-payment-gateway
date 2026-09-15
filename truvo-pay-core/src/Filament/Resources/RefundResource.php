<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Models\Refund;

class RefundResource extends Resource
{
    protected static ?string $model = Refund::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Refunds & Disputes';
    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('refund_reference')->disabled(),
                Forms\Components\TextInput::make('amount')->disabled(),
                Forms\Components\TextInput::make('currency')->disabled(),
                Forms\Components\TextInput::make('status')->disabled(),
                Forms\Components\Textarea::make('reason')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('refund_reference')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('transaction.truvo_reference')
                    ->label('Transaction Ref')
                    ->searchable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'succeeded' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(30),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ]);
    }
}
