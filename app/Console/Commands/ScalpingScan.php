<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\FileUpload\InputFile;
use App\Models\Stock;
use Throwable;

class ScalpingScan extends Command
{
    protected $signature = 'scalping:scan';
    protected $description = 'Scan scalping signal (Optimized Chart Generation)';

    private $minAvgVolume = 50000;
    private $chunkSize = 50;

    public function handle()
    {
        date_default_timezone_set('Asia/Jakarta');
        
        $this->info("🚀 Mulai Scan (Mode Cepat: Chart dibuat belakangan)...");

        $stocks = Stock::where('is_active', true)->pluck('code')->toArray();
        if (empty($stocks)) {
            $this->error("❌ Tidak ada emiten aktif.");
            return;
        }

        $signals = [];
        $chunks = array_chunk($stocks, $this->chunkSize);

        foreach ($chunks as $index => $chunkStocks) {
            $this->comment("   🔄 Processing Batch " . ($index + 1));

            try {
                $responses = Http::pool(function ($pool) use ($chunkStocks) {
                    foreach ($chunkStocks as $code) {
                        $symbol = strtoupper($code) . '.JK';
                        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=5m&range=5d";
                        
                        $pool->as($code)
                             ->withOptions(['verify' => false])
                             ->timeout(8)
                             ->get($url);
                    }
                });

                foreach ($responses as $code => $res) {
                    if ($res instanceof Throwable || !$res->successful()) continue;
                    if ($this->alreadyAlerted($code)) continue;

                    $analysis = $this->analyzeStock($code, $res->json());

                    if ($analysis && $analysis['score'] >= 65) {
                        $signals[] = $analysis;
                        $this->line("      Found: {$code} (Score: {$analysis['score']})");
                    }
                }
                
                usleep(200000); 

            } catch (\Exception $e) {
                $this->error("Error batch: " . $e->getMessage());
            }
        }

        if (empty($signals)) {
            $this->warn("⚠️  Scan selesai. Tidak ada signal valid.");
            return;
        }

        usort($signals, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $topSignals = array_slice($signals, 0, 5);

        $this->info("\n📸 Mulai Generate Chart & Broadcast untuk " . count($topSignals) . " Saham Terbaik...");

        foreach ($topSignals as $sig) {
            $chartImage = $this->downloadChartImage(
                $sig['code'],
                $sig['history']['closes'],
                $sig['history']['ema9'],
                $sig['history']['ema21'],
                $sig['history']['timestamps']
            );

            $sig['chart_img'] = $chartImage;

            $this->sendTelegram($sig);
            $this->saveAlert($sig);
            
            sleep(2);
        }

        $this->info("🏁 Selesai.");
    }

    private function analyzeStock($code, $data)
    {
        $result = $data['chart']['result'][0] ?? null;
        if (!$result) return null;

        $quote = $result['indicators']['quote'][0] ?? null;
        $timestamps = $result['timestamp'] ?? [];

        $lastTs = end($timestamps);
        if ((time() - $lastTs) > 3600) return null;

        $closes  = $this->clean($quote['close'] ?? []);
        $volumes = $this->clean($quote['volume'] ?? []);
        
        if (count($closes) < 30) return null;

        $price  = end($closes);
        $avgVol = array_sum(array_slice($volumes, -20)) / 20;
        
        if ($avgVol < $this->minAvgVolume) return null;

        $ema9Array  = $this->calculateEMAArray($closes, 9);
        $ema21Array = $this->calculateEMAArray($closes, 21);
        $rsi        = $this->calculateRSI($closes, 14);
        
        $ema9  = end($ema9Array);
        $ema21 = end($ema21Array);

        $score = 0;
        if ($ema9 > $ema21) $score += 30;
        if ($rsi >= 50 && $rsi <= 75) $score += 30;
        elseif ($rsi >= 40 && $rsi < 50) $score += 15;
        
        $highs = $this->clean($quote['high'] ?? []);
        $lows  = $this->clean($quote['low'] ?? []);
        $vwap = $this->calculateVWAP(
            array_slice($highs, -50), array_slice($lows, -50), 
            array_slice($closes, -50), array_slice($volumes, -50)
        );
        if ($price > $vwap) $score += 20;

        $vol = end($volumes);
        $volSpikeRatio = $avgVol > 0 ? ($vol / $avgVol) : 0;
        if ($volSpikeRatio > 1.2) $score += 20;

        if ($score < 65) return null;

        $recentLows = array_slice($lows, -5);
        $swingLow = !empty($recentLows) ? min($recentLows) : $price * 0.98;
        $sl = floor($swingLow * 0.99); 
        $risk = $price - $sl;
        $tp1 = ceil($price + ($risk * 1.5));
        $tp2 = ceil($price + ($risk * 2.5));

        return [
            'code'      => $code,
            'score'     => $score,
            'price'     => $price,
            'sl'        => $sl,
            'tp1'       => $tp1,
            'tp2'       => $tp2,
            'rsi'       => $rsi,
            'vol_spike' => number_format($volSpikeRatio, 1) . 'x',
            'time'      => date('H:i', $lastTs),
            
            'history'   => [
                'closes'     => array_slice($closes, -40),
                'ema9'       => array_slice($ema9Array, -40),
                'ema21'      => array_slice($ema21Array, -40),
                'timestamps' => array_slice($timestamps, -40)
            ]
        ];
    }

    private function downloadChartImage($code, $closes, $ema9Data, $ema21Data, $timestamps)
    {
        $this->line("   🎨 Generating chart for {$code}...");

        $formattedLabels = array_map(fn($t) => date('H:i', $t), $timestamps);
        
        $clean = fn($arr) => array_map(fn($v) => (is_nan($v) || is_infinite($v)) ? null : $v, array_values($arr));

        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => array_values($formattedLabels),
                'datasets' => [
                    [
                        'label' => 'Price', 'data' => $clean($closes),
                        'borderColor' => 'blue', 'borderWidth' => 2, 'fill' => false, 'pointRadius' => 0
                    ],
                    [
                        'label' => 'EMA9', 'data' => $clean($ema9Data),
                        'borderColor' => 'green', 'borderWidth' => 1, 'fill' => false, 'pointRadius' => 0
                    ],
                    [
                        'label' => 'EMA21', 'data' => $clean($ema21Data),
                        'borderColor' => 'red', 'borderWidth' => 1, 'fill' => false, 'pointRadius' => 0
                    ]
                ]
            ],
            'options' => [
                'title' => ['display' => true, 'text' => "$code - Scalping (5m)"],
                'legend' => ['display' => false]
            ]
        ];

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(10)
                ->post('https://quickchart.io/chart', [
                    'chart' => $chartConfig,
                    'width' => 500, 'height' => 300, 'backgroundColor' => 'white', 'format' => 'jpg'
                ]);

            if ($response->successful() && strlen($response->body()) > 1000) {
                return $response->body();
            }
        } catch (\Exception $e) {
            $this->error("❌ Gagal Download Chart: " . $e->getMessage());
        }
        return null;
    }

    private function sendTelegram($sig)
    {
        $caption = "⚡ <b>SCALPING SIGNAL</b>\n";
        $caption .= "<b>{$sig['code']}</b> | Score: {$sig['score']}\n";
        $caption .= "Price: " . number_format($sig['price']) . "\n";
        $caption .= "RSI: " . number_format($sig['rsi'], 1) . " | Vol: {$sig['vol_spike']}\n\n";
        $caption .= "🎯 <b>PLAN:</b>\n";
        $caption .= "Buy: " . number_format($sig['price']) . "\n";
        $caption .= "TP: " . number_format($sig['tp1']) . " - " . number_format($sig['tp2']) . "\n";
        $caption .= "SL: " . number_format($sig['sl']);

        if (!empty($sig['chart_img'])) {
            $tempName = tempnam(sys_get_temp_dir(), 'chart_' . $sig['code']);
            file_put_contents($tempName, $sig['chart_img']);

            try {
                Telegram::sendPhoto([
                    'chat_id' => '5952186192',
                    'photo'   => InputFile::create($tempName, $sig['code'].'.jpg'),
                    'caption' => $caption,
                    'parse_mode' => 'HTML'
                ]);
                $this->info("✅ Terkirim: {$sig['code']}");
            } catch (\Exception $e) {
                $this->error("🔥 Gagal kirim Telegram: " . $e->getMessage());
                Telegram::sendMessage([
                    'chat_id' => '5952186192',
                    'text' => $caption . "\n\n(⚠️ Gagal upload chart)",
                    'parse_mode' => 'HTML'
                ]);
            }

            @unlink($tempName);
        } else {
            Telegram::sendMessage([
                'chat_id' => '5952186192',
                'text' => $caption . "\n\n(⚠️ Chart gagal di-generate)",
                'parse_mode' => 'HTML'
            ]);
        }
    }
    
    private function saveAlert($sig)
    {
        DB::table('tb_scalping_alerts')->insert([
            'code' => $sig['code'],
            'score' => $sig['score'],
            'alerted_at' => now()
        ]);
    }

    private function alreadyAlerted($code)
    {
        return DB::table('tb_scalping_alerts')
            ->where('code', $code)
            ->where('alerted_at', '>', now()->subMinutes(60))
            ->exists();
    }

    private function clean($arr)
    {
        return array_values(array_filter($arr, fn($v) => !is_null($v) && is_numeric($v)));
    }
    
    private function calculateEMAArray($data, $period) {
        $emaArray = [];
        $count = count($data);
        if ($count <= $period) return array_fill(0, $count, 0);
        $sma = array_sum(array_slice($data, 0, $period)) / $period;
        $multiplier = 2 / ($period + 1);
        for ($i = 0; $i < $period; $i++) $emaArray[] = null;
        $ema = $sma;
        $emaArray[$period - 1] = $ema;
        for ($i = $period; $i < $count; $i++) {
            $ema = ($data[$i] - $ema) * $multiplier + $ema;
            $emaArray[] = $ema;
        }
        return $emaArray;
    }

    private function calculateRSI($data, $period = 14) {
         $count = count($data);
        if ($count <= $period) return 50;
        $avgGain = 0; $avgLoss = 0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $data[$i] - $data[$i - 1];
            if ($change > 0) $avgGain += $change; else $avgLoss += abs($change);
        }
        $avgGain /= $period; $avgLoss /= $period;
        for ($i = $period + 1; $i < $count; $i++) {
            $change = $data[$i] - $data[$i - 1];
            $currentGain = ($change > 0) ? $change : 0;
            $currentLoss = ($change < 0) ? abs($change) : 0;
            $avgGain = (($avgGain * ($period - 1)) + $currentGain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $currentLoss) / $period;
        }
        if ($avgLoss == 0) return 100;
        $rs = $avgGain / $avgLoss;
        return 100 - (100 / (1 + $rs));
    }

    private function calculateVWAP($h, $l, $c, $v) {
        $sumPV = 0; $sumV = 0;
        $count = min(count($h), count($l), count($c), count($v));
        for ($i = 0; $i < $count; $i++) {
            $tp = ($h[$i] + $l[$i] + $c[$i]) / 3;
            $sumPV += $tp * $v[$i]; $sumV += $v[$i];
        }
        return $sumV > 0 ? $sumPV / $sumV : 0;
    }
}