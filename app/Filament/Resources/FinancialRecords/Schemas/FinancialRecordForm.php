<?php

namespace App\Filament\Resources\FinancialRecords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FinancialRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->required()
                    ->columnSpan('full'),
                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->columnSpan('full'),
                TextInput::make('created_by')
                    ->label('Created By')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan('full'),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(4)
                    ->columnSpan('full'),
            ]);
    }
}
