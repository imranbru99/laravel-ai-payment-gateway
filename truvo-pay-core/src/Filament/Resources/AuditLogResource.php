<?php

namespace Truvo\Pay\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Truvo\Pay\Models\AuditLog;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Audit Log';
    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return config('truvo-pay.filament.navigation_group', 'Truvo Pay');
    }

    public static function canCreate(): bool
    {
        return false; // Immutable audit log
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('action')->disabled(),
                Forms\Components\TextInput::make('ip_address')->disabled(),
                Forms\Components\TextInput::make('created_at')->disabled(),
                Forms\Components\KeyValue::make('old_values')->disabled(),
                Forms\Components\KeyValue::make('new_values')->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime('d M Y, h:i:s A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color('info')
                    ->searchable(),
                Tables\Columns\TextColumn::make('auditable_type')
                    ->label('Entity')
                    ->formatStateUsing(fn ($state) => class_basename($state)),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP Address'),
                Tables\Columns\TextColumn::make('user_id')
                    ->label('User ID')
                    ->formatStateUsing(fn ($state) => $state ? "User #{$state}" : 'System / AI'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }
}
