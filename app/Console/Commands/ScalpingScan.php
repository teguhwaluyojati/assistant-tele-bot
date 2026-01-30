<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Telegram\Bot\Laravel\Facades\Telegram;
use App\Models\Stock;
use Throwable;

class ScalpingScan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scalping:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan scalping signal with Yahoo Finance (Delayed Data Tolerant)';

    private $minAvgVolume = 50000; 
    private $chunkSize = 50;
    private $rsiPeriod = 14;

    public function handle()
    {
        date_default_timezone_set('Asia/Jakarta');
        
        $this->info("🚀 Memulai Scalping Scan...");


        $stocks = Stock::where('is_active', true)->pluck('code')->toArray();
        
        if (empty($stocks)) {
            $this->error("❌ Tidak ada emiten aktif di database.");
            return;
        }

        $this->info("📊 Total Emiten: " . count($stocks));
        
        $signals = [];
        $chunks = array_chunk($stocks, $this->chunkSize);

        foreach ($chunks as $index => $chunkStocks) {
            $batchNum = $index + 1;
            $this->comment("🔄 Processing Batch {$batchNum} (" . count($chunkStocks) . " emiten)...");

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
                    if ($res instanceof Throwable || !$res->successful()) {
                        continue; 
                    }

                    if ($this->alreadyAlerted($code)) continue;

                    $analysis = $this->analyzeStock($code, $res->json());

                    if ($analysis) {
                        $this->line("   👉 {$code} \t| Score: {$analysis['score']} \t| RSI: " . number_format($analysis['rsi'], 1));

                        if ($analysis['score'] >= 65) {
                            $signals[] = $analysis;
                        }
                    }
                }

                usleep(500000); 

            } catch (\Exception $e) {
                $this->error("Error batch {$batchNum}: " . $e->getMessage());
            }
        }

        if (empty($signals)) {
            $this->warn("⚠️  Scan selesai. Tidak ada signal valid yang ditemukan.");
            return;
        }

        usort($signals, fn($a, $b) => $b['score'] <=> $a['score']);
        
        $topSignals = array_slice($signals, 0, 5);

        $this->info("✅ Ditemukan " . count($topSignals) . " signal potensial. Mengirim ke Telegram...");

        foreach ($topSignals as $sig) {
            $this->sendTelegram($sig);
            $this->saveAlert($sig);
        }

        $this->info("🏁 Selesai.");
    }

    /**
     * Core Logic: Analisa Teknikal
     */
    private function analyzeStock($code, $data)
    {
        $result = $data['chart']['result'][0] ?? null;
        if (!$result) return null;

        $quote = $result['indicators']['quote'][0] ?? null;
        $timestamps = $result['timestamp'] ?? [];

        if (empty($timestamps) || empty($quote)) return null;

        $lastTs = end($timestamps);
        if (time() - $lastTs > 3600) { 
            return null; 
        }

        $closes  = $this->clean($quote['close'] ?? []);
        $highs   = $this->clean($quote['high'] ?? []);
        $lows    = $this->clean($quote['low'] ?? []);
        $volumes = $this->clean($quote['volume'] ?? []);

        if (count($closes) < 30) return null;

        $price  = end($closes);
        $vol    = end($volumes);
        
        $avgVol = array_sum(array_slice($volumes, -20)) / 20;
        
        if ($avgVol < $this->minAvgVolume) return null;

        $ema9  = $this->calculateEMA($closes, 9);
        $ema21 = $this->calculateEMA($closes, 21);
        $rsi   = $this->calculateRSI($closes, $this->rsiPeriod);
        
        $vwap = $this->calculateVWAP(
            array_slice($highs, -50),
            array_slice($lows, -50),
            array_slice($closes, -50),
            array_slice($volumes, -50)
        );

        $score = 0;

        if ($ema9 > $ema21) $score += 30;

        if ($rsi >= 50 && $rsi <= 75) $score += 30;
        elseif ($rsi >= 40 && $rsi < 50) $score += 15; 

        if ($price > $vwap) $score += 20;

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
            'time'      => date('H:i', $lastTs)
        ];
    }

    /**
     * Kirim Notif ke Telegram
     */
    private function sendTelegram($sig)
    {
        $msg = "⚡ <b>SCALPING SIGNAL</b>\n";
        $msg .= "--------------------------------\n";
        $msg .= "Emiten: <b>{$sig['code']}</b>\n";
        $msg .= "Score: <b>{$sig['score']}</b> / 100\n";
        $msg .= "Price: " . number_format($sig['price']) . "\n";
        $msg .= "RSI: " . number_format($sig['rsi'], 1) . "\n";
        $msg .= "Vol Spike: {$sig['vol_spike']}\n";
        $msg .= "Data Time: {$sig['time']} WIB\n";
        $msg .= "--------------------------------\n";
        $msg .= "🎯 <b>PLAN:</b>\n";
        $msg .= "Buy: " . number_format($sig['price']) . " - " . number_format($sig['price'] * 1.005) . "\n";
        $msg .= "TP1: " . number_format($sig['tp1']) . "\n";
        $msg .= "TP2: " . number_format($sig['tp2']) . " 🚀\n";
        $msg .= "SL:  <b>" . number_format($sig['sl']) . "</b> (Strict!)\n";

        try {
            Telegram::sendMessage([
                'chat_id' => ENV('TELEGRAM_CHAT_ID'), 
                'text' => $msg,
                'parse_mode' => 'HTML'
            ]);
        } catch (\Exception $e) {
            $this->error("Gagal kirim Telegram {$sig['code']}");
        }
    }

    /**
     * Simpan Alert ke DB
     */
    private function saveAlert($sig)
    {
        DB::table('tb_scalping_alerts')->insert([
            'code'       => $sig['code'],
            'score'      => $sig['score'],
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

    private function calculateEMA($data, $period)
    {
        $count = count($data);
        if ($count <= $period) return end($data);

        $sma = array_sum(array_slice($data, 0, $period)) / $period;
        $multiplier = 2 / ($period + 1);
        $ema = $sma;

        for ($i = $period; $i < $count; $i++) {
            $ema = ($data[$i] - $ema) * $multiplier + $ema;
        }
        return $ema;
    }

    private function calculateRSI($data, $period = 14)
    {
        $count = count($data);
        if ($count <= $period) return 50;

        $avgGain = 0;
        $avgLoss = 0;

        for ($i = 1; $i <= $period; $i++) {
            $change = $data[$i] - $data[$i - 1];
            if ($change > 0) $avgGain += $change;
            else $avgLoss += abs($change);
        }

        $avgGain /= $period;
        $avgLoss /= $period;

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

    private function calculateVWAP($h, $l, $c, $v)
    {
        $sumPV = 0;
        $sumV = 0;
        $count = min(count($h), count($l), count($c), count($v));

        for ($i = 0; $i < $count; $i++) {
            $tp = ($h[$i] + $l[$i] + $c[$i]) / 3;
            $sumPV += $tp * $v[$i];
            $sumV += $v[$i];
        }

        return $sumV > 0 ? $sumPV / $sumV : 0;
    }
}