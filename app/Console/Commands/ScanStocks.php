<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Stock;

class ScanStocks extends Command
{
    protected $signature = 'stock:scan';
    protected $description = 'Scan Market IHSG - Buy The Dip in Uptrend (V2 Improved)';

    public function handle()
    {
        $this->info("🚀 Memulai Scanning Market (Logic V2 Improved)...");

        $stocks = Stock::where('is_active', true)->get();

        if ($stocks->isEmpty()) {
            $this->error("❌ Tidak ada saham aktif di database.");
            return;
        }

        $total = $stocks->count();
        $this->info("🔍 Menganalisa {$total} saham...");

        DB::table('stock_recommendations')->truncate();

        $bar = $this->output->createProgressBar($total);
        $found = 0;

        foreach ($stocks as $stock) {
            $result = $this->analyze($stock->code);

            if ($result && $result['score'] >= 20) {
                DB::table('stock_recommendations')->insert([
                    'code'       => $stock->code,
                    'price'      => $result['price'],
                    'score'      => $result['score'],
                    'signal'     => $result['signal'],
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
        $this->info("✅ Scan selesai. {$found} saham lolos filter.");
    }

    private function analyze(string $code): ?array
    {
        $symbol = $code . '.JK';
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=1y";

        try {
            $response = Http::timeout(10)->get($url);
            $json = $response->json();

            if (empty($json['chart']['result'][0]['indicators']['quote'][0])) {
                return null;
            }

            $quote   = $json['chart']['result'][0]['indicators']['quote'][0];
            $closes  = $this->cleanSeries($quote['close'] ?? []);
            $volumes = $this->cleanSeries($quote['volume'] ?? []);

            if (count($closes) < 200) return null;

            $currentPrice  = end($closes);
            $currentVolume = end($volumes);

            $ma200 = $this->sma($closes, 200);
            $ma50  = $this->sma($closes, 50);
            $ma20  = $this->sma($closes, 20);
            $avgVol = $this->sma($volumes, 20);
            $rsi   = $this->rsiWilder($closes, 14);

            $score = 0;

            if ($currentPrice > $ma200) {
                $score += 20;
                if ($ma50 > $ma200) $score += 10;
            } else {
                $score -= 50;
            }

            if ($rsi < 30 && $currentPrice > $ma200) {
                $score += 35;
            } elseif ($rsi >= 40 && $rsi <= 60) {
                $score += 10;
            }

            $last3 = array_slice($closes, -3);
            if ($last3[2] > $last3[1]) {
                $score += 5;
            }


            if ($currentVolume > ($avgVol * 1.5)) {
                $score += 20;
            }


            $signal = "WAIT 😐";
            if ($score >= 60) {
                $signal = "STRONG BUY 🟢";
            } elseif ($score >= 30) {
                $signal = "BUY ON WEAKNESS 🟡";
            } elseif ($score < 0) {
                $signal = "AVOID / SELL 🔴";
            }

 
            if ($signal === "STRONG BUY 🟢") {
                $buyLow  = $currentPrice * 0.98;
                $buyHigh = $currentPrice;
            } else {
                $buyLow  = min($ma20, $currentPrice * 0.99);
                $buyHigh = $buyLow * 1.02;
            }

            $entry = ($buyLow + $buyHigh) / 2;
            $cl = $entry * 0.95;
            $tp = $entry * 1.08;

            return [
                'price'    => round($currentPrice, 2),
                'score'    => $score,
                'signal'   => $signal,
                'buy_area' => number_format($buyLow, 0) . " - " . number_format($buyHigh, 0),
                'tp'       => round($tp, 2),
                'cl'       => round($cl, 2),
            ];

        } catch (\Throwable $e) {
            return null;
        }
    }


    private function cleanSeries(array $data): array
    {
        $data = array_slice($data, -260);
        return array_values(array_filter($data, fn($v) => $v !== null));
    }

    private function sma(array $data, int $period): float
    {
        if (count($data) < $period) return 0;
        return array_sum(array_slice($data, -$period)) / $period;
    }

    private function rsiWilder(array $prices, int $period = 14): float
    {
        if (count($prices) <= $period) return 50;

        $gains = 0;
        $losses = 0;

        for ($i = 1; $i <= $period; $i++) {
            $diff = $prices[$i] - $prices[$i - 1];
            if ($diff >= 0) $gains += $diff;
            else $losses += abs($diff);
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        for ($i = $period + 1; $i < count($prices); $i++) {
            $diff = $prices[$i] - $prices[$i - 1];
            $gain = $diff > 0 ? $diff : 0;
            $loss = $diff < 0 ? abs($diff) : 0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;
        }

        if ($avgLoss == 0) return 100;
        $rs = $avgGain / $avgLoss;

        return 100 - (100 / (1 + $rs));
    }
}
