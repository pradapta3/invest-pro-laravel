<?php

namespace App\Console\Commands;

use App\Models\StockRef;
use Database\Seeders\Lq45Seeder;
use Illuminate\Console\Command;

/**
 * Replaces import_data.php / setup_tickers.php's CSV upload forms and
 * hardcoded "load LQ45" button with a proper Artisan command.
 */
class ImportStockReference extends Command
{
    protected $signature = 'idx:import-stock-reference
        {--csv= : Path to a CSV export with columns No,Kode,Nama (header row skipped)}
        {--lq45 : Seed the built-in fallback list of well-known tickers instead of a CSV}';

    protected $description = 'Import or refresh IDX ticker reference data (stock_refs.ticker/nama_perusahaan)';

    public function handle(): int
    {
        if ($this->option('lq45')) {
            $count = 0;
            foreach (Lq45Seeder::TICKERS as $ticker => $name) {
                StockRef::query()->updateOrCreate(['ticker' => $ticker], ['nama_perusahaan' => $name]);
                $count++;
            }
            $this->info("Seeded {$count} fallback tickers.");

            return self::SUCCESS;
        }

        $path = $this->option('csv');
        if (! $path) {
            $this->error('Pass --csv=path/to/file.csv or --lq45.');

            return self::FAILURE;
        }

        if (! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $imported = 0;
        $skipped = 0;
        $rowNumber = 0;

        while (($data = fgetcsv($handle, 2000, ',')) !== false) {
            $rowNumber++;
            if ($rowNumber === 1) {
                continue; // header row
            }

            $rawTicker = trim($data[1] ?? '');
            $rawName = trim($data[2] ?? '');

            if (! preg_match('/^[A-Z]{4}$/', $rawTicker)) {
                $skipped++;

                continue;
            }

            $name = (string) preg_replace('/[^\x20-\x7E]/', '', $rawName);

            StockRef::query()->updateOrCreate(
                ['ticker' => $rawTicker.'.JK'],
                ['nama_perusahaan' => $name],
            );
            $imported++;
        }

        fclose($handle);
        $this->info("Imported {$imported} tickers, skipped {$skipped} malformed rows.");

        return self::SUCCESS;
    }
}
