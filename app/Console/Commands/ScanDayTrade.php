<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\Stock;

class ScanDayTrade extends Command
{
    protected $signature = 'stock:daytrade'; 
    protected $description = 'Scan Saham BSJP (Save to day_trade_recommendations)';

    public function handle()
    {
        $this->info("🚀 Memulai Scanning BSJP / Day Trade...");

        $stocks = Stock::where('is_active', true)
                    ->get();

        if ($stocks->isEmpty()) {
            $this->error("❌ Database kosong."); return;
        }

        $total = $stocks->count();
        
        DB::table('day_trade_recommendations')->truncate();

        $bar = $this->output->createProgressBar($total);
        $found = 0;

        foreach ($stocks as $stock) {
            $result = $this->analyze($stock->code);

            if ($result) { 
                DB::table('day_trade_recommendations')->insert([
                    'code'       => $stock->code,
                    'price'      => $result['price'],
                    'change_pct' => $result['change_pct'],
                    'signal'     => "🔥 BSJP",
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
        $this->info("✅ Scan BSJP Selesai! $found saham disimpan ke tabel 'day_trade_recommendations'.");
    }

    private function analyze($code) {

        
        $symbol = $code . '.JK';
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=3mo";
        
        try {
            $response = Http::timeout(5)->get($url);
            $data = $response->json();
            
            if (empty($data['chart']['result'])) return null;
            
            $quote = $data['chart']['result'][0]['indicators']['quote'][0];
            $meta  = $data['chart']['result'][0]['meta']; 

            $closes = $this->clean($quote['close'] ?? []);
            $volumes = $this->clean($quote['volume'] ?? []);
            $highs = $this->clean($quote['high'] ?? []);

            if (count($closes) < 20) return null;

            $price      = end($closes);
            $volume     = end($volumes);
            $high       = end($highs);
            $prevPrice  = $meta['chartPreviousClose'];

            $transactionValue = $price * $volume;
            if ($transactionValue < 5000000000) return null; 

            $changePct = (($price - $prevPrice) / $prevPrice) * 100;
            if ($changePct < 2.0 || $changePct > 15.0) return null; 

            if ($price < ($high * 0.97)) return null; 

            $avgVol = array_sum(array_slice($volumes, -20)) / 20;
            if ($volume < ($avgVol * 1.2)) return null; 

            return [
                'price'     => $price,
                'change_pct'=> round($changePct, 2),
                'buy_area'  => number_format($price), 
                'tp'        => $price * 1.04,
                'cl'        => $price * 0.97 
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    private function clean($arr) {
        return array_values(array_filter($arr));
    }
}