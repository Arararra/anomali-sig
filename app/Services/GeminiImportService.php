<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GeminiImportService
{
    public function convertExcelToJson(string $filePath): array
    {
        $spreadsheetData = $this->loadSpreadsheetData($filePath);
        $prompt = $this->buildPrompt($spreadsheetData);

        $response = $this->callGeminiApi($prompt);

        $outputText = $this->getGeminiResponseText($response);
        if (! is_string($outputText) || $outputText === '') {
            throw new \RuntimeException('Gemini API response did not contain any generated text.');
        }

        $json = $this->extractJson($outputText);
        if ($json === null) {
            throw new \RuntimeException('Gemini API response could not be parsed as JSON.');
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Gemini API JSON output was not valid JSON.');
        }

        $transactions = $this->extractTransactionArray($decoded);
        if ($transactions === null) {
            throw new \RuntimeException('Gemini API JSON output was not an array of transactions.');
        }

        $validated = [];
        foreach ($transactions as $item) {
            $row = $this->normalizeTransactionRow($item);
            if ($row !== null) {
                $validated[] = $row;
            }
        }

        if (empty($validated)) {
            throw new \RuntimeException('Gemini output did not contain any valid transaction rows.');
        }

        return $validated;
    }

    protected function getGeminiResponseText(array $response): ?string
    {
        $candidate = $response['candidates'][0] ?? null;
        if (! is_array($candidate)) {
            return null;
        }

        if (isset($candidate['output']) && is_string($candidate['output'])) {
            return $candidate['output'];
        }

        $content = $candidate['content'] ?? null;
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            if (isset($content['text']) && is_string($content['text'])) {
                return $content['text'];
            }

            $parts = $content['parts'] ?? null;
            if (is_array($parts)) {
                $texts = [];
                foreach ($parts as $part) {
                    if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                        $texts[] = $part['text'];
                    }
                }

                if (! empty($texts)) {
                    return implode("\n", $texts);
                }
            }
        }

        return null;
    }

    protected function loadSpreadsheetData(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();

        return $worksheet->toArray(null, true, true, true);
    }

    protected function buildPrompt(array $spreadsheetData): string
    {
        $rawData = json_encode($spreadsheetData, JSON_UNESCAPED_UNICODE);

        return "Konversi data array mentah spreadsheet berikut menjadi format JSON bersih sebagai array objek. Gunakan hanya key: 'date', 'description', 'amount'. \n" .
            "- date harus menjadi tanggal transaksi yang valid. Abaikan baris header, subtotal, rentang tanggal, atau teks non-transaksi seperti 'Januari - Juni 2026'.\n" .
            "- description harus menjadi deskripsi singkat transaksi (misalnya nama produk atau kategori).\n" .
            "- amount harus menjadi nilai numerik bersih dari total pendapatan / nilai transaksi. Hilangkan mata uang dan pemisah ribuan.\n" .
            "Jika data tidak tampak seperti daftar transaksi valid, jawab dengan array kosong: []\n" .
            "Data: {$rawData}";
    }

    protected function callGeminiApi(string $prompt): array
    {
        $apiKey = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-3.5-flash');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new \RuntimeException('Gemini API key is not configured.');
        }

        $endpoints = [
            sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', $model),
            sprintf('https://generativelanguage.googleapis.com/v1/models/%s:generateContent', $model),
        ];

        foreach ($endpoints as $endpoint) {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])->post($endpoint, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.2,
                    'maxOutputTokens' => 1024,
                ],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            if ($response->status() === 404) {
                continue;
            }

            throw new \RuntimeException(sprintf('Gemini API request failed (%s): %s', $response->status(), $response->body()));
        }

        throw new \RuntimeException(sprintf('Gemini API request failed (404): no valid endpoint found for model %s', $model));
    }

    protected function normalizeTransactionRow(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $date = $item['date'] ?? $item['tanggal'] ?? $item['Date'] ?? null;
        $description = $item['description'] ?? $item['keterangan'] ?? $item['Description'] ?? $item['produk'] ?? $item['Produk'] ?? $item['kategori'] ?? $item['Kategori'] ?? null;
        $amount = $item['amount'] ?? $item['jumlah'] ?? $item['Amount'] ?? $item['Jumlah'] ?? $item['total pendapatan'] ?? $item['Total Pendapatan'] ?? $item['total_pendapatan'] ?? null;

        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        $parsedDate = $this->parseDate($date);
        if ($parsedDate === null) {
            return null;
        }

        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        if (! is_string($amount) && ! is_numeric($amount)) {
            return null;
        }

        $normalizedAmount = $this->normalizeAmount($amount);
        if ($normalizedAmount === null) {
            return null;
        }

        return [
            'date' => $parsedDate->format('Y-m-d'),
            'description' => trim($description),
            'amount' => $normalizedAmount,
        ];
    }

    protected function parseDate(string $date): ?\Carbon\Carbon
    {
        try {
            // Prefer English date parsing first; fallback to Indonesian month names.
            return Carbon::parse($date);
        } catch (\Throwable $exception) {
            $indonesian = [
                'Januari' => 'January',
                'Februari' => 'February',
                'Maret' => 'March',
                'April' => 'April',
                'Mei' => 'May',
                'Juni' => 'June',
                'Juli' => 'July',
                'Agustus' => 'August',
                'September' => 'September',
                'Oktober' => 'October',
                'November' => 'November',
                'Desember' => 'December',
            ];

            $translated = str_ireplace(array_keys($indonesian), array_values($indonesian), $date);

            try {
                return Carbon::parse($translated);
            } catch (\Throwable $exception) {
                return null;
            }
        }
    }

    protected function extractTransactionArray(array $decoded): ?array
    {
        if ($this->isTransactionArray($decoded)) {
            return $decoded;
        }

        if ($this->isTransactionRow($decoded)) {
            return [$decoded];
        }

        foreach ($decoded as $value) {
            if (is_array($value)) {
                $nested = $this->extractTransactionArray($value);
                if ($nested !== null) {
                    return $nested;
                }
            }
        }

        return null;
    }

    protected function isTransactionArray(array $items): bool
    {
        if (empty($items)) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item) || $this->normalizeTransactionRow($item) === null) {
                return false;
            }
        }

        return true;
    }

    protected function isTransactionRow(array $item): bool
    {
        return $this->normalizeTransactionRow($item) !== null;
    }

    protected function normalizeAmount(mixed $amount): ?string
    {
        if (is_numeric($amount)) {
            return (string) $amount;
        }

        if (! is_string($amount)) {
            return null;
        }

        $clean = preg_replace('/[^0-9\-,\.]/u', '', $amount);
        if ($clean === null || $clean === '') {
            return null;
        }

        $clean = str_replace(['.', ','], ['', '.'], $clean);

        if (! is_numeric($clean)) {
            return null;
        }

        return (string) $clean;
    }

    protected function extractJson(string $output): ?string
    {
        $trimmed = trim($output);

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return $trimmed;
        }

        if (preg_match('/```json\s*(.*?)\s*```/is', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/(\[.*\]|\{.*\})/s', $trimmed, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
