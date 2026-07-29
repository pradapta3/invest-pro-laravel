<?php

namespace Database\Seeders;

use App\Models\StockRef;
use Illuminate\Database\Seeder;

/**
 * Quick-fill fallback list of well-known IDX tickers, replacing
 * setup_tickers.php's hardcoded "Isi Otomatis Saham Populer" button. For
 * full exchange coverage, prefer `php artisan idx:import-stock-reference
 * --csv=path/to/idx-export.csv` against an official IDX ticker export.
 */
class Lq45Seeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    public const TICKERS = [
        'BBCA.JK' => 'Bank Central Asia Tbk',
        'BBRI.JK' => 'Bank Rakyat Indonesia Tbk',
        'BMRI.JK' => 'Bank Mandiri Tbk',
        'BBNI.JK' => 'Bank Negara Indonesia Tbk',
        'TLKM.JK' => 'Telkom Indonesia Tbk',
        'ASII.JK' => 'Astra International Tbk',
        'UNVR.JK' => 'Unilever Indonesia Tbk',
        'ICBP.JK' => 'Indofood CBP Tbk',
        'INDF.JK' => 'Indofood Sukses Makmur Tbk',
        'GGRM.JK' => 'Gudang Garam Tbk',
        'HMSP.JK' => 'HM Sampoerna Tbk',
        'KLBF.JK' => 'Kalbe Farma Tbk',
        'ADRO.JK' => 'Adaro Energy Tbk',
        'PTBA.JK' => 'Bukit Asam Tbk',
        'PGAS.JK' => 'Perusahaan Gas Negara Tbk',
        'ANTM.JK' => 'Aneka Tambang Tbk',
        'INCO.JK' => 'Vale Indonesia Tbk',
        'MDKA.JK' => 'Merdeka Copper Gold Tbk',
        'UNTR.JK' => 'United Tractors Tbk',
        'SMGR.JK' => 'Semen Indonesia Tbk',
        'INTP.JK' => 'Indocement Tunggal Prakarsa Tbk',
        'CPIN.JK' => 'Charoen Pokphand Tbk',
        'JPFA.JK' => 'Japfa Comfeed Tbk',
        'TOWR.JK' => 'Sarana Menara Nusantara Tbk',
        'EXCL.JK' => 'XL Axiata Tbk',
        'ISAT.JK' => 'Indosat Tbk',
        'BUKA.JK' => 'Bukalapak.com Tbk',
        'GOTO.JK' => 'GoTo Gojek Tokopedia Tbk',
        'BRIS.JK' => 'Bank Syariah Indonesia Tbk',
        'AMRT.JK' => 'Sumber Alfaria Trijaya Tbk',
        'ACES.JK' => 'Ace Hardware Indonesia Tbk',
        'MAPI.JK' => 'Mitra Adiperkasa Tbk',
        'MEDC.JK' => 'Medco Energi Internasional Tbk',
        'AKRA.JK' => 'AKR Corporindo Tbk',
    ];

    public function run(): void
    {
        foreach (self::TICKERS as $ticker => $name) {
            StockRef::query()->updateOrCreate(
                ['ticker' => $ticker],
                ['nama_perusahaan' => $name],
            );
        }

        $this->command?->info(count(self::TICKERS).' tickers seeded.');
    }
}
