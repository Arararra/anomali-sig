<?php

namespace App\Filament\Resources\FinancialRecords;

use App\Filament\Resources\FinancialRecords\Pages\CreateFinancialRecord;
use App\Filament\Resources\FinancialRecords\Pages\EditFinancialRecord;
use App\Filament\Resources\FinancialRecords\Pages\ListFinancialRecords;
use App\Filament\Resources\FinancialRecords\Schemas\FinancialRecordForm;
use App\Filament\Resources\FinancialRecords\Tables\FinancialRecordsTable;
use App\Models\FinancialRecord;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FinancialRecordResource extends Resource
{
    protected static ?string $model = FinancialRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return FinancialRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FinancialRecordsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFinancialRecords::route('/'),
            'create' => CreateFinancialRecord::route('/create'),
            'edit' => EditFinancialRecord::route('/{record}/edit'),
        ];
    }
}
