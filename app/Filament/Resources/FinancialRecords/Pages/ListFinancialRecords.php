<?php

namespace App\Filament\Resources\FinancialRecords\Pages;

use App\Filament\Resources\FinancialRecords\FinancialRecordResource;
use App\Models\FinancialRecord;
use App\Services\GeminiImportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ListFinancialRecords extends ListRecords
{
    protected static string $resource = FinancialRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('Excel File')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->directory('imports')
                        ->disk('public'),
                ])
                ->action(function (array $data): void {
                    $this->importFinancialRecords($data['file']);
                })
                ->successNotificationTitle('Import completed')
                ->failureNotificationTitle('Import failed'),
        ];
    }

    protected function importFinancialRecords(string $file): void
    {
        $filePath = Storage::disk('public')->path($file);

        $gemini = app(GeminiImportService::class);
        $output = $gemini->convertExcelToJson($filePath);

        if (! is_array($output)) {
            return;
        }

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            FinancialRecord::create([
                'amount' => $item['amount'] ?? null,
                'date' => isset($item['date']) ? Carbon::parse($item['date'])->format('Y-m-d') : null,
                'description' => $item['description'] ?? null,
            ]);
        }
    }
}
