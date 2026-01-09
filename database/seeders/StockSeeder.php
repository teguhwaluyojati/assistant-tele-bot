<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use App\Models\Stock;


class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⏳ Sedang mengunduh data saham terbaru dari GitHub...');


        $url = 'https://raw.githubusercontent.com/goapi-id/indonesia-stock-exchange-symbol/master/symbol.json';
        
        try {
            $response = Http::get($url);
            $stocks = $response->json();

            if (!$stocks) {
                $this->command->error('Gagal mengambil data atau data kosong.');
                return;
            }

            $count = 0;
            $bar = $this->command->getOutput()->createProgressBar(count($stocks));

            foreach ($stocks as $item) {
                Stock::updateOrCreate(
                    ['code' => $item['symbol']], 
                    [
                        'name' => $item['name'] ?? null,
                        'is_active' => true
                    ]
                );
                
                $bar->advance();
                $count++;
            }

            $bar->finish();
            $this->command->newLine();
            $this->command->info("✅ Berhasil mengimport $count saham ke database!");

        } catch (\Exception $e) {
            $this->command->error("Terjadi kesalahan: " . $e->getMessage());
        }
    }
}
