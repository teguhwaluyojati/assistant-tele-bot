<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

class BSJPController extends Controller
{
    /**
     * BSJP Validator v3.0
     * Fitur: Anti-Blokir, Realtime Time, Harga Sekarang, Saran Eksekusi (HK vs Antri).
     */
    public function analyzeBsjp($inputString)
    {
        $rawTickers = array_filter(explode(' ', strtoupper($inputString)));
        if (empty($rawTickers)) return "⚠️ Masukkan kode sahamnya bos. Contoh: `/bsjp ANTM BRIS`";
        $rawTickers = array_slice($rawTickers, 0, 10);
        $yahooTickers = array_map(fn($t) => str_replace('.JK', '', $t) . '.JK', $rawTickers);

        try {
            $responses = Http::pool(fn (Pool $pool) => 
                array_map(fn($ticker) => 
                    $pool->as($ticker)->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => 'application/json'
                    ])->timeout(12)->get("https://query1.finance.yahoo.com/v8/finance/chart/{$ticker}?interval=1d&range=1mo"), 
                $yahooTickers)
            );
        } catch (\Exception $e) { 
            Log::error("BSJP Yahoo API Error: " . $e->getMessage());
            return "❌ Gagal koneksi Yahoo."; 
        }

        $results = [];

        foreach ($responses as $tickerKey => $response) {
            $cleanSymbol = str_replace('.JK', '', $tickerKey);
            if ($response->failed()) continue;

            $json = $response->json();
            if (empty($json['chart']['result'])) continue;

            $resultData = $json['chart']['result'][0];
            $meta = $resultData['meta'];
            $quote = $resultData['indicators']['quote'][0];

            $marketTimeUnix = $meta['regularMarketTime'] ?? time();
            $timeWIB = gmdate("H:i", $marketTimeUnix + (7 * 3600));

            $closes = $quote['close'] ?? [];
            $highs = $quote['high'] ?? [];
            $volumes = $quote['volume'] ?? [];
            $opens = $quote['open'] ?? [];
            $lows = $quote['low'] ?? [];

            $cleanData = [];
            foreach ($closes as $i => $c) {
                if ($c !== null && $highs[$i] !== null) {
                    $cleanData[] = [
                        'close' => $c, 'high' => $highs[$i], 'low' => $lows[$i],
                        'vol' => $volumes[$i], 'open' => $opens[$i]
                    ];
                }
            }

            if (count($cleanData) < 2) continue;

            $today = $cleanData[count($cleanData) - 1];
            $yesterday = $cleanData[count($cleanData) - 2];

            $price = $today['close'];
            $high = $today['high'];
            $low = $today['low'];
            $open = $today['open'];
            $vol = $today['vol'];
            $prevClose = $yesterday['close'];

            $changePct = (($price - $prevClose) / $prevClose) * 100;
            $chgStr = ($changePct >= 0 ? '+' : '') . number_format($changePct, 2) . '%';
            
            $last20Closes = array_column(array_slice($cleanData, -20), 'close');
            $ma20 = count($last20Closes) > 0 ? (array_sum($last20Closes) / count($last20Closes)) : $price;
            $last20Vols = array_column(array_slice($cleanData, -20), 'vol');
            $avgVol20 = count($last20Vols) > 0 ? (array_sum($last20Vols) / count($last20Vols)) : 1;
            $volRatio = $avgVol20 > 0 ? ($vol / $avgVol20) : 0;

            $score = 0;
            $comments = [];

            if ($price < $open) { $score -= 20; $comments[] = "Candle Merah"; }
            elseif ($price > $open) { $score += 10; }

            if ($price > $ma20) { $score += 20; }
            elseif ($changePct > 3.0) { $score += 15; $comments[] = "Reversal"; }
            else { $score -= 10; $comments[] = "Downtrend"; }

            $proximityToHigh = $high > 0 ? ($price / $high) * 100 : 0;
            if ($changePct > 0) {
                if ($price == $high) { $score += 40; $comments[] = "ARA/High"; }
                elseif ($proximityToHigh >= 97) { $score += 30; $comments[] = "Tutup Kuat"; }
                elseif ($proximityToHigh < 95) { $score -= 10; $comments[] = "Jarum Atas"; }
            } else { $score -= 50; $comments[] = "Minus"; }

            if ($volRatio >= 2.0) { $score += 30; $comments[] = "Vol " . number_format($volRatio,1) . "x"; }
            elseif ($volRatio >= 1.0) { $score += 15; }
            else { $score -= 5; $comments[] = "Sepi"; }

            $rec = "❌ SKIP";
            $advice = "⛔ Jangan Masuk";

            if ($score >= 75) {
                $rec = "💎 STRONG BUY";
                if ($price == $high) {
                    $advice = "🔥 **HK SEKARANG** (Potensi ARA Besok)";
                } else {
                    $advice = "✅ **Hajar Kanan (HK)** di $price";
                }
            } elseif ($score >= 40) {
                $rec = "👀 PANTAU (Watch)";
                if ($changePct > 5.0 && $proximityToHigh < 98) {
                    $antriArea = $price - (floor($price * 0.01)); 
                    $advice = "🛡️ **Tunggu/Antri** di area $antriArea (Rawan Guyur)";
                } elseif ($price < $open) {
                    $advice = "⚠️ **Wait & See** (Tunggu mantul ke hijau)";
                } else {
                    $advice = "⚖️ **Cicil Masuk** di $price (Stoploss Ketat)";
                }
            }

            $results[] = [
                'symbol' => $cleanSymbol,
                'price' => number_format($price),
                'chg' => $chgStr,
                'score' => $score,
                'rec' => $rec,
                'advice' => $advice,
                'story' => implode(", ", $comments),
                'time' => $timeWIB
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $output = "🤖 **Laporan Pandangan Mata BSJP**\n\n";
        
        if (empty($results)) return "Hampa.. Gak ada data.";

        foreach ($results as $res) {
            $output .= "**{$res['symbol']}** ({$res['chg']}) • Rp {$res['price']} • 🕒 {$res['time']}\n";
            $output .= "Status: **{$res['rec']}** (Skor: {$res['score']})\n";
            
            if ($res['score'] >= 40) {
                $output .= "💡 Saran: {$res['advice']}\n";
            } else {
                $output .= "💡 Saran: ⛔ Skip dulu, jelek.\n";
            }
            
            $output .= "📝 Info: _{$res['story']}_\n\n";
        }

        return $output;
    }
}
