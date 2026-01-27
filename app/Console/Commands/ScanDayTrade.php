<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Stock;

class ScanDayTrade extends Command
{
    protected $signature = 'stock:overnight';
    protected $description = 'Fast & Accurate Overnight Stock Scanner (Buy Close Sell Open)';

    public function handle()
    {
        $this->info("⚡ Overnight Scan Started...");

        $stocks = Stock::where('is_active', true)->pluck('code');

        if ($stocks->isEmpty()) {
            $this->error("❌ No active stocks.");
            return;
        }

        DB::table('day_trade_recommendations')
            ->whereDate('created_at', today())
            ->delete();

        $symbols = $stocks->map(fn ($c) => $c . '.JK')->values();

        $responses = Http::pool(fn ($pool) =>
            $symbols->map(fn ($symbol) =>
                $pool->as($symbol)
                    ->timeout(6)
                    ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1h&range=5d")
            )->toArray()
        );

        $found = 0;

        foreach ($responses as $symbol => $response) {
            $code = str_replace('.JK', '', $symbol);

            try {
                if (!$response->ok()) continue;

                $result = Cache::remember(
                    "overnight_scan_{$code}",
                    now()->addMinutes(3),
                    fn () => $this->analyze($response->json())
                );

                if (!$result) continue;

                DB::table('day_trade_recommendations')->insert([
                    'code'       => $code,
                    'price'      => $result['entry'],
                    'change_pct' => $result['momentum'],
                    'signal'     => '🌙 OVERNIGHT',
                    'buy_area'   => $result['entry'],
                    'tp_target'  => $result['tp'],
                    'cl_price'   => $result['sl'],
                    'notes'      => $result['notes'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $found++;

            } catch (\Throwable $e) {
                Log::warning("Skip {$code}: " . $e->getMessage());
            }
        }

        $this->info("✅ Scan done. {$found} valid overnight setups found.");
    }

    /**
     * Core overnight analysis
     */
    private function analyze(array $res): ?array
    {
        $data = $res['chart']['result'][0] ?? null;
        if (!$data) return null;

        $meta  = $data['meta'] ?? null;
        $quote = $data['indicators']['quote'][0] ?? null;

        $close  = $this->clean($quote['close'] ?? []);
        $high   = $this->clean($quote['high'] ?? []);
        $low    = $this->clean($quote['low'] ?? []);
        $volume = $this->clean($quote['volume'] ?? []);

        if (count($close) < 55) return null;

        $lastClose = end($close);
        $lastHigh  = end($high);
        $lastLow   = end($low);

        if ($lastClose < 200) return null;
        if ($lastClose < ($lastHigh * 0.98)) return null; 

        $prevDayClose = $meta['chartPreviousClose'] ?? null;
        
        if (!$prevDayClose) return null;

        $momentum = (($lastClose - $prevDayClose) / $prevDayClose) * 100;
        if ($momentum < 1.0 || $momentum > 8.0) return null;

        $volLast  = end($volume);
        $prevVols = array_slice($volume, -6, 5);
        $avgVol   = array_sum($prevVols) / count($prevVols);

        if ($avgVol <= 0) return null;
        $volSpike = $volLast / $avgVol;

        if ($volSpike < 1.4) return null;

        $ema20 = $this->calculateEMA($close, 20); 
        $ema50 = $this->calculateEMA($close, 55);
        
        if ($ema20 <= $ema50) return null;

        $sumVol3 = array_sum(array_slice($volume, -3));
        $avgPrice3 = array_sum(array_slice($close, -3)) / 3;
        $value3h = $sumVol3 * $avgPrice3; 

        if ($value3h < 3000000000) return null;

        $range = $lastHigh - $lastLow;
        if ($range == 0) $range = $lastClose * 0.01; 

        $tp = $lastClose + ($range * 1.5); 
        $sl = $lastClose - ($range * 1.0); 

        return [
            'entry'    => round($lastClose),
            'tp'       => round($tp),
            'sl'       => round($sl),
            'momentum' => round($momentum, 2),
            'notes'    => "Vol " . round($volSpike, 1) . "x | Uptrend EMA | Mtm " . round($momentum, 1) . "%",
        ];
    }

    /**
     * EMA calculation
     */
    private function calculateEMA(array $data, int $period): float
    {
        $data = array_values($data);
        
        if (count($data) <= $period) {
            return array_sum($data) / count($data);
        }

        $initialSMA = array_sum(array_slice($data, 0, $period)) / $period;
        
        $multiplier = 2 / ($period + 1);
        $ema = $initialSMA;

        for ($i = $period; $i < count($data); $i++) {
            $ema = ($data[$i] - $ema) * $multiplier + $ema;
        }

        return $ema;
    }

    /**
     * Remove null & reindex
     */
    private function clean(array $data): array
    {
        return array_values(array_filter($data, fn ($v) => $v !== null));
    }
}
