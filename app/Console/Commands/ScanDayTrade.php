<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Stock;

class ScanDayTrade extends Command
{
    protected $signature = 'stock:daytrade';
    protected $description = 'Scan Saham BSJP (Daily Momentum – Safe Version)';

    public function handle()
    {
        $this->info("🚀 Scanning Saham BSJP (Safe Daily Version)...");

        $stocks = Stock::where('is_active', true)->get();
        if ($stocks->isEmpty()) {
            $this->error("❌ Tidak ada saham aktif.");
            return;
        }

        DB::table('day_trade_recommendations')->truncate();

        $bar = $this->output->createProgressBar($stocks->count());
        $found = 0;

        foreach ($stocks as $stock) {
            $result = $this->analyze($stock->code);

            if ($result) {
                DB::table('day_trade_recommendations')->insert([
                    'code'       => $stock->code,
                    'price'      => $result['price'],
                    'change_pct' => $result['change_pct'],
                    'signal'     => '🔥 BSJP',
                    'buy_area'   => $result['buy_area'],
                    'tp_target'  => $result['tp'],
                    'cl_price'   => $result['cl'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $found++;
            }

            usleep(120000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ BSJP selesai. {$found} saham lolos.");
    }

    private function analyze(string $code): ?array
    {
        $symbol = $code . '.JK';
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=2mo";

        try {
            $res = Http::timeout(6)->get($url)->json();
            if (empty($res['chart']['result'][0])) return null;

            $data  = $res['chart']['result'][0];
            $quote = $data['indicators']['quote'][0];
            $meta  = $data['meta'];

            $closes  = $this->clean($quote['close'] ?? []);
            $highs   = $this->clean($quote['high'] ?? []);
            $volumes = $this->clean($quote['volume'] ?? []);

            if (count($closes) < 20) return null;

            $price     = end($closes);
            $high      = end($highs);
            $volume    = end($volumes);
            $prevClose = $meta['chartPreviousClose'];

            /** ======================
             *  FILTER DASAR
             *  ====================== */
            if ($price < 200) return null;

            $changePct = (($price - $prevClose) / $prevClose) * 100;
            if ($changePct < 2.5 || $changePct > 8.0) return null;

            /** ======================
             *  CLOSE NEAR HIGH
             *  ====================== */
            if ($price < ($high * 0.995)) return null;

            /** ======================
             *  VOLUME & VALUE
             *  ====================== */
            $avgVol = array_sum(array_slice($volumes, -20)) / 20;
            if ($volume < ($avgVol * 1.5)) return null;

            $avgValue = $price * (array_sum(array_slice($volumes, -5)) / 5);
            if ($avgValue < 5000000000) return null;

            /** ======================
             *  RISK MANAGEMENT
             *  ====================== */
            $tp = $price * 1.02;   // +2%
            $cl = $price * 0.985;  // -1.5%

            return [
                'price'      => round($price, 0),
                'change_pct' => round($changePct, 2),
                'buy_area'   => number_format($price, 0),
                'tp'         => round($tp, 0),
                'cl'         => round($cl, 0),
            ];

        } catch (\Throwable $e) {
            return null;
        }
    }

    private function clean(array $data): array
    {
        return array_values(array_filter($data, fn($v) => $v !== null));
    }
}
