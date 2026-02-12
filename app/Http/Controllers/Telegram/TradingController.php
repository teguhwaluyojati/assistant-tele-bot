<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class TradingController extends Controller
{
    public function bsjp($chatId)
    {
        $stocks = DB::table('day_trade_recommendations')
            ->orderBy('change_pct', 'desc')
            ->limit(5)
            ->get();

        if ($stocks->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Data rekomendasi belum tersedia. Jalankan scanner dulu.',
            ]);
            return;
        }

        $msg = "📈 **REKOMENDASI BUY ON STRONG JUMP\n";
        $msg .= "_(Saham dengan lonjakan volume & harga signifikan)_\n\n";

        foreach ($stocks as $i => $stock) {
            $num = $i + 1;
            $msg .= "{$num}. **{$stock->code}** (+{$stock->change_pct}%)\n";
            $msg .= "   💰 Close: " . number_format($stock->price) . "\n";
            $msg .= "   🛒 Area Beli: {$stock->buy_area}\n";
            $msg .= "   🎯 TP: " . number_format($stock->tp_target) . " (3-5%)\n";
            $msg .= "   🛡 CL: " . number_format($stock->cl_price) . " (Ketata!)\n\n";
        }

        $msg .= "🕒 Data: " . Carbon::parse($stocks[0]->created_at)->format('d M H:i') . "\n";
        $msg .= "_Disclaimer On._";

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }

    public function swingTrade($chatId)
    {
        $recommendations = DB::table('stock_recommendations')
            ->orderBy('score', 'desc')
            ->limit(5)
            ->get();

        if ($recommendations->isEmpty()) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Data rekomendasi belum tersedia. Jalankan scanner dulu.',
            ]);
            return;
        }

        $msg = "💎 **HIDDEN GEMS HARI INI** 💎\n";
        $msg .= "_(Hasil scan seluruh market)_\n\n";

        foreach ($recommendations as $i => $stock) {
            $num = $i + 1;
            $msg .= "{$num}. **{$stock->code}** (Skor: {$stock->score})\n";
            $msg .= "   Sinyal: {$stock->signal}\n";
            $msg .= "   🛒 Buy: {$stock->buy_area}\n";
            $msg .= "   🎯 TP: " . number_format($stock->tp_target);
            $msg .= "   🛑 CL: " . number_format($stock->cl_price, 0) . "\n\n";
        }

        $msg .= "Data diupdate: " . $recommendations[0]->updated_at;

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
        ]);
    }

    public function analyzeAdvanced($chatId, $code)
    {
        $symbol = strtoupper($code) . '.JK';
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=1d&range=3mo";

        try {
            Telegram::sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

            $response = Http::get($url);
            if ($response->failed()) {
                throw new \Exception('Gagal koneksi data.');
            }

            $data = $response->json();

            if (empty($data['chart']['result']) || empty($data['chart']['result'][0]['timestamp'])) {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => "⚠️ Data saham {$code} tidak ditemukan."]);
                return;
            }

            $result = $data['chart']['result'][0];

            $meta = $result['meta'];
            $lastUpdateUnix = $meta['regularMarketTime'] ?? time();
            $wibTime = Carbon::createFromTimestamp($lastUpdateUnix)->setTimezone('Asia/Jakarta')->format('d M Y H:i');

            $quote = $result['indicators']['quote'][0];

            $closes = $this->cleanData($quote['close']);
            $highs = $this->cleanData($quote['high']);
            $lows = $this->cleanData($quote['low']);
            $volumes = $this->cleanData($quote['volume']);

            if (count($closes) < 20) {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => '⚠️ Data historis kurang.']);
                return;
            }

            $currentPrice = end($closes);
            $currentVolume = end($volumes);

            $ma50 = $this->calculateSMA($closes, 50);
            $ma20 = $this->calculateSMA($closes, 20);
            $rsi14 = $this->calculateRSI($closes, 14);
            $avgVolume = $this->calculateSMA($volumes, 20);

            $score = 0;

            if ($currentPrice > $ma50) {
                $score += 20;
            }
            if ($currentPrice > $ma20 && $ma20 > $ma50) {
                $score += 10;
            }

            if ($rsi14 < 30) {
                $score += 40;
            } elseif ($rsi14 > 70) {
                $score -= 30;
            } elseif ($rsi14 >= 40 && $rsi14 <= 60) {
                $score += 10;
            }

            if ($currentVolume > ($avgVolume * 1.5)) {
                $score += 20;
            } elseif ($currentVolume < ($avgVolume * 0.5)) {
                $score -= 10;
            }

            $recommendation = 'WAIT / NEUTRAL 😐';
            if ($score >= 60) {
                $recommendation = 'STRONG BUY 🟢';
            } elseif ($score >= 30) {
                $recommendation = 'BUY ON WEAKNESS 🟡';
            } elseif ($score < 0) {
                $recommendation = 'SELL / AVOID 🔴';
            }

            $recentLowsShort = array_slice($lows, -20);
            $shortTermSupport = min($recentLowsShort);
            $isRallying = $currentPrice > ($ma20 * 1.05);

            $planType = 'Wait & See';
            $buyMax = 0;
            $buyMin = 0;

            if ($recommendation === 'SELL / AVOID 🔴') {
                $planType = 'Jangan Entry (Falling Knife)';
                $buyMin = 0;
                $buyMax = 0;
            } elseif ($recommendation === 'STRONG BUY 🟢') {
                $buyMax = $currentPrice;
                $buyMin = $currentPrice * 0.98;
                $planType = 'HAKA (Aggressive Buy)';
            } elseif ($isRallying) {
                $buyMin = $ma20;
                $buyMax = $ma20 * 1.03;
                $planType = 'Pullback ke MA20';
            } else {
                if ($currentPrice < $shortTermSupport) {
                    $shortTermSupport = min($lows);
                    $planType = 'High Risk (Support Jebol)';
                } else {
                    $planType = 'Buy on Weakness (Support)';
                }
                $buyMin = $shortTermSupport;
                $buyMax = $shortTermSupport * 1.03;
            }

            $buyAreaStr = '-';
            $tp1Str = '-';
            $tp2Str = '-';
            $clStr = '-';

            if ($buyMin > 0) {
                $buyMin = $this->adjustToFraksi($buyMin);
                $buyMax = $this->adjustToFraksi($buyMax);

                $entryAvg = ($buyMin + $buyMax) / 2;

                $clPriceRaw = $buyMin * 0.96;
                $clPrice = $this->adjustToFraksi($clPriceRaw);

                $risk = $entryAvg - $clPrice;

                $mathTP1 = $entryAvg + ($risk * 1.5);
                $mathTP2 = $entryAvg + ($risk * 2.5);

                $resistanceHigh = max($highs);

                $tp1Note = '';
                $tp2Note = '';

                if ($mathTP1 > $resistanceHigh && $currentPrice < $resistanceHigh) {
                    $realTP1 = $resistanceHigh;
                    $tp1Note = '(Resist)';

                    $realTP2 = $mathTP2;
                    $tp2Note = '(Breakout)';
                } else {
                    $realTP1 = $mathTP1;
                    $realTP2 = $mathTP2;
                }

                $realTP1 = $this->adjustToFraksi($realTP1);
                $realTP2 = $this->adjustToFraksi($realTP2);

                $buyAreaStr = number_format($buyMin) . ' - ' . number_format($buyMax);
                $tp1Str = number_format($realTP1) . ' ' . $tp1Note;
                $tp2Str = number_format($realTP2) . ' ' . $tp2Note;

                $clStr = '&lt; ' . number_format($clPrice);
            }

            $msg = '🧠 <b>AI TRADING ANALYST: ' . htmlspecialchars(strtoupper($code)) . "</b>\n";
            $msg .= 'Harga: ' . number_format($currentPrice) . "\n\n";

            $msg .= "📊 <b>Sinyal: {$recommendation}</b> (Score: {$score})\n";
            $msg .= '• Trend: ' . ($currentPrice > $ma50 ? 'Bullish 🐂' : 'Bearish 🐻') . "\n";
            $msg .= '• RSI: ' . number_format($rsi14, 1) . "\n";
            $msg .= '• Vol: ' . ($currentVolume > $avgVolume ? 'High 🔊' : 'Low 🔇') . "\n";

            $msg .= "\n🎯 <b>STRATEGI: {$planType}</b>\n";
            $msg .= "-----------------------------\n";
            $msg .= '🛒 <b>Buy Area:</b> ' . $buyAreaStr . "\n";
            $msg .= '✅ <b>TP 1:</b> ' . $tp1Str . "\n";
            $msg .= '🚀 <b>TP 2:</b> ' . $tp2Str . "\n";
            $msg .= '🛡️ <b>Cut Loss:</b> ' . $clStr . "\n";

            $msg .= "\n<i>Disclaimer On. Data: {$wibTime} WIB.</i>";

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $msg,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error('Bot Error: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => '⚠️ Gagal menganalisa saham.']);
        }
    }

    public function analyzeScalpingV2($chatId, $code)
    {
        $symbol = strtoupper($code) . '.JK';
        $url = "https://query1.finance.yahoo.com/v8/finance/chart/{$symbol}?interval=5m&range=5d";

        try {
            Telegram::sendChatAction(['chat_id' => $chatId, 'action' => 'typing']);

            $now = Carbon::now('Asia/Jakarta');
            $time = $now->format('H:i');

            $validSession =
                ($time >= '09:00' && $time <= '11:00') ||
                ($time >= '13:30' && $time <= '14:30');

            $response = Http::get($url);
            if ($response->failed()) {
                throw new \Exception('Koneksi gagal');
            }

            $data = $response->json();
            if (empty($data['chart']['result'][0]['timestamp'])) {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => '⚠️ Data intraday kosong']);
                return;
            }

            $quote = $data['chart']['result'][0]['indicators']['quote'][0];

            $closes = $this->cleanData($quote['close']);
            $highs = $this->cleanData($quote['high']);
            $lows = $this->cleanData($quote['low']);
            $volumes = $this->cleanData($quote['volume']);

            if (count($closes) < 50) {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => '⚠️ Data belum cukup']);
                return;
            }

            $price = end($closes);
            $vol = end($volumes);

            $ema9 = $this->calculateEMAScalping($closes, 9);
            $ema21 = $this->calculateEMAScalping($closes, 21);
            $rsi7 = $this->calculateRSIScalping($closes, 7);

            $avgVol = array_sum(array_slice($volumes, -20)) / 20;

            $volSlice = array_slice($volumes, -78);
            $highSlice = array_slice($highs, -78);
            $lowSlice = array_slice($lows, -78);
            $closeSlice = array_slice($closes, -78);

            $totalVolume = array_sum($volSlice);
            if ($totalVolume <= 0) {
                return;
            }

            $vwapValue = 0;
            foreach ($volSlice as $i => $v) {
                $vwapValue += (($highSlice[$i] + $lowSlice[$i] + $closeSlice[$i]) / 3) * $v;
            }
            $vwap = $vwapValue / $totalVolume;

            if ($avgVol < 5000) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "🚫 <b>NO TRADE</b>\nLikuiditas rendah.",
                    'parse_mode' => 'HTML',
                ]);
                return;
            }

            if ($rsi7 >= 45 && $rsi7 <= 55 && abs($ema9 - $ema21) / $price < 0.002) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⚠️ <b>NO TRADE</b>\nMarket choppy.",
                    'parse_mode' => 'HTML',
                ]);
                return;
            }

            $recentHigh = max(array_slice($highs, -5));
            if ($price < $recentHigh * 0.998 && $vol < $avgVol * 1.3) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⚠️ <b>WAIT</b>\nBelum ada konfirmasi breakout.",
                    'parse_mode' => 'HTML',
                ]);
                return;
            }

            $score = 0;
            if ($price > $vwap) {
                $score += 25;
            }
            if ($ema9 > $ema21) {
                $score += 25;
            }
            if ($rsi7 >= 58 && $rsi7 <= 72) {
                $score += 25;
            }
            if ($vol > $avgVol * 1.3) {
                $score += 25;
            }

            if ($score < 75) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "⛔ <b>NO TRADE</b>\nSetup belum clean ({$score}).",
                    'parse_mode' => 'HTML',
                ]);
                return;
            }

            $buyMin = $this->adjustToFraksi($price * 0.999);
            $buyMax = $this->adjustToFraksi($price * 1.002);
            $entry = ($buyMin + $buyMax) / 2;

            $swingLow = min(array_slice($lows, -5));
            $cl = $this->adjustToFraksi($swingLow * 0.997);
            $tp1 = $this->adjustToFraksi($entry * 1.006);
            $tp2 = $this->adjustToFraksi($entry * 1.010);

            $msg = '⚡ <b>AI SCALPING V2: ' . strtoupper($code) . "</b>\n";
            $msg .= 'Harga: ' . number_format($price) . "\n\n";

            $msg .= "📊 <b>VALID SCALP BUY 🟢</b> (Score: {$score})\n";
            $msg .= "• EMA9 > EMA21\n";
            $msg .= '• RSI(7): ' . number_format($rsi7, 1) . "\n";
            $msg .= '• VWAP: ' . number_format($vwap) . "\n\n";

            $msg .= "🎯 <b>PLAN</b>\n";
            $msg .= '🛒 Buy: ' . number_format($buyMin) . ' - ' . number_format($buyMax) . "\n";
            $msg .= '✅ TP1: ' . number_format($tp1) . "\n";
            $msg .= '🚀 TP2: ' . number_format($tp2) . "\n";
            $msg .= '🛡️ CL: < ' . number_format($cl) . "\n";

            $msg .= "\n<i>Disiplin. 1–2 trade saja.</i>";

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $msg,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error('ScalpV2 Error: ' . $e->getMessage());
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => '⚠️ Error analisa scalping.',
            ]);
        }
    }

    private function adjustToFraksi($price)
    {
        if ($price <= 0) {
            return 0;
        }

        if ($price < 200) {
            return round($price);
        }
        if ($price < 500) {
            return round($price / 2) * 2;
        }
        if ($price < 2000) {
            return round($price / 5) * 5;
        }
        if ($price < 5000) {
            return round($price / 10) * 10;
        }

        return round($price / 25) * 25;
    }

    private function cleanData($array)
    {
        return array_values(array_filter($array, function ($v) {
            return !is_null($v);
        }));
    }

    private function calculateSMA($data, $period)
    {
        if (count($data) < $period) {
            return 0;
        }

        $slice = array_slice($data, -$period);
        return array_sum($slice) / count($slice);
    }

    private function calculateRSI($data, $period = 14)
    {
        if (count($data) < $period + 1) {
            return 50;
        }

        $changes = [];
        for ($i = 1; $i < count($data); $i++) {
            $changes[] = $data[$i] - $data[$i - 1];
        }

        $recentChanges = array_slice($changes, -$period);

        $gains = 0;
        $losses = 0;

        foreach ($recentChanges as $change) {
            if ($change > 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }

        if ($losses == 0) {
            return 100;
        }
        if ($gains == 0) {
            return 0;
        }

        $rs = $gains / $losses;
        return 100 - (100 / (1 + $rs));
    }

    private function calculateEMA($data, $period)
    {
        if (count($data) < $period) {
            return 0;
        }

        $k = 2 / ($period + 1);
        $ema = $data[0];

        foreach ($data as $price) {
            $ema = ($price * $k) + ($ema * (1 - $k));
        }

        return $ema;
    }

    private function calculateEMAScalping($data, $period)
    {
        $slice = array_slice($data, -50);
        if (count($slice) < $period) {
            return end($slice);
        }

        $k = 2 / ($period + 1);
        $ema = array_sum(array_slice($slice, 0, $period)) / $period;

        for ($i = $period; $i < count($slice); $i++) {
            $ema = ($slice[$i] * $k) + ($ema * (1 - $k));
        }

        return $ema;
    }

    private function calculateRSIScalping($data, $period = 7)
    {
        if (count($data) < $period + 1) {
            return 50;
        }

        $gains = 0;
        $losses = 0;

        $start = count($data) - $period;

        for ($i = $start + 1; $i < count($data); $i++) {
            $change = $data[$i] - $data[$i - 1];
            if ($change > 0) {
                $gains += $change;
            } else {
                $losses += abs($change);
            }
        }

        if ($losses == 0) {
            return 100;
        }
        if ($gains == 0) {
            return 0;
        }

        $rs = $gains / $losses;
        return 100 - (100 / (1 + $rs));
    }
}
