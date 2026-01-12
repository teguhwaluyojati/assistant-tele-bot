<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Stock;

class ScanStocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:scan-stocks';
    protected $signature = 'stock:scan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("🚀 Memulai Scanning Market dari Database...");

        $stocks = Stock::where('is_active', true)
                    ->get();

        if ($stocks->isEmpty()) {
            $this->error("❌ Tidak ada saham yang ditemukan. Cek filter 'board' di kodingan vs isi database.");
            return;
        }

        $total = $stocks->count();
        $this->info("🔍 Ditemukan $total saham potensial untuk dianalisa.");

        DB::table('stock_recommendations')->truncate();

        $bar = $this->output->createProgressBar($total);
        $found = 0;

        foreach ($stocks as $stock) {
            $result = $this->analyze($stock->code);

            if ($result && $result['score'] >= 20) { 
                DB::table('stock_recommendations')->insert([
                    'code'      => $stock->code,
                    'price'     => $result['price'],
                    'score'     => $result['score'],
                    'signal'    => $result['signal'],
                    'buy_area'  => $result['buy_area'],
                    'tp_target' => $result['tp'],
                    'cl_price'  => $result['cl'],
                    'created_at'=> now(),
                    'updated_at'=> now(),
                ]);
                $found++;
            }
            
            usleep(200000); 
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✅ Scan Selesai! Berhasil menyimpan $found rekomendasi saham.");
    }

    private function analyze($code) {
        $symbol = $code . '.JK';
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=3mo";
        
        try {
            $response = Http::get($url);
            $data = $response->json();
            
            if (empty($data['chart']['result'])) return null;
            
            $quote = $data['chart']['result'][0]['indicators']['quote'][0];
            $closes = array_values(array_filter($quote['close'] ?? []));
            $volumes = array_values(array_filter($quote['volume'] ?? []));

            if (count($closes) < 50) return null;

            $currentPrice = end($closes);
            $currentVolume = end($volumes);
            
            $ma50 = $this->sma($closes, 50);
            $ma20 = $this->sma($closes, 20);
            $rsi = $this->rsi($closes, 14);
            $avgVol = $this->sma($volumes, 20);

            $score = 0;
            if ($currentPrice > $ma50) $score += 20;
            if ($currentPrice > $ma20 && $ma20 > $ma50) $score += 10;
            if ($rsi < 30) $score += 40;
            elseif ($rsi >= 40 && $rsi <= 60) $score += 10;
            elseif ($rsi > 70) $score -= 30; 
            if ($currentVolume > ($avgVol * 1.5)) $score += 20;

            $signal = ($score >= 60) ? "STRONG BUY" : (($score >= 30) ? "BUY ON WEAKNESS" : "WAIT");
            
            if ($score >= 50) {
                 $buyArea = number_format($currentPrice);
            } else {
                 $buyArea = number_format($ma20) . " - " . number_format($currentPrice);
            }

            return [
                'price' => $currentPrice,
                'score' => $score,
                'signal' => $signal,
                'buy_area' => $buyArea,
                'tp' => $currentPrice * 1.05, 
                'cl' => $currentPrice * 0.96  
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    private function sma($data, $p) {
        if(count($data)<$p) return 0;
        return array_sum(array_slice($data, -$p)) / $p;
    }
    
    private function rsi($data, $period = 14) {
            if (count($data) < $period + 1) return 50; 

            $changes = [];
            for ($i = 1; $i < count($data); $i++) {
                $changes[] = $data[$i] - $data[$i - 1];
            }

            $recentChanges = array_slice($changes, -$period);
            
            $gains = 0;
            $losses = 0;

            foreach ($recentChanges as $change) {
                if ($change > 0) $gains += $change;
                else $losses += abs($change);
            }

            if ($losses == 0) return 100; 
            if ($gains == 0) return 0;

            $rs = $gains / $losses;
            return 100 - (100 / (1 + $rs));
        }
}
