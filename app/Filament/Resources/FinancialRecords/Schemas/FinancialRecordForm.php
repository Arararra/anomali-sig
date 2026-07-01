<?php

namespace App\Filament\Resources\FinancialRecords\Schemas;

use App\Models\FinancialRecord;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;

class FinancialRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->columnSpan('full')
                    ->schema([
                        Group::make([
                            TextInput::make('amount')
                                ->label('Amount')
                                ->numeric()
                                ->required(),
                            DatePicker::make('date')
                                ->label('Date')
                                ->required(),
                            Textarea::make('description')
                                ->label('Description')
                                ->columnSpan('full'),
                        ])->columns(2)->columnSpan(2),

                        Group::make([
                            Placeholder::make('created_by_placeholder')
                                ->label('Created By')
                                ->content(fn (?FinancialRecord $record): string => $record?->creator?->name ?? 'N/A'),

                            Placeholder::make('created_at_placeholder')
                                ->label('Created At')
                                ->content(fn (?FinancialRecord $record): string => $record?->created_at?->format('Y-m-d H:i:s') ?? 'N/A'),

                            Placeholder::make('updated_at_placeholder')
                                ->label('Updated At')
                                ->content(fn (?FinancialRecord $record): string => $record?->updated_at?->format('Y-m-d H:i:s') ?? 'N/A'),
                        ])->columnSpan(1)
                          ->visible(fn (?FinancialRecord $record): bool => $record !== null),
                    ]),
            ]);
    }
}
