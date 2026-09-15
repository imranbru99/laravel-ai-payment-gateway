<?php

namespace Truvo\Pay\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Truvo\Pay\Models\Transaction;

class LiveTransactionFeedWidget extends BaseWidget
{
    protected static ?string $heading = 'Live Transaction Stream';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()->latest()->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('truvo_reference')
                    ->label('Reference')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money(fn ($record) => $record->currency)
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gateway_key')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ai_approved', 'manually_approved', 'paid' => 'success',
                        'flagged' => 'danger',
                        'pending' => 'warning',
                        'rejected' => 'gray',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('ai_confidence_score')
                    ->label('AI Match')
                    ->formatStateUsing(fn ($state) => $state ? "{$state}%" : '—')
                    ->badge(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->since(),
            ])
            ->paginated(false);
    }
}
