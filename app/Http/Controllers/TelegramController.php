<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Keyboard\Keyboard;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Telegram\Bot\FileUpload\InputFile;

class TelegramController extends Controller
{
    /**
     * Handle incoming Telegram updates.
     */
    public function handle()
    {
        date_default_timezone_set('Asia/Jakarta');
        $update = Telegram::getWebhookUpdate();

        // Panggil fungsi untuk mencatat data pengguna di sini
        $this->logUserActivity($update);

        // 1. MENANGANI CALLBACK QUERY (Saat tombol inline ditekan)
        if ($update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            $chatId = $callbackQuery->getMessage()->getChat()->getId();
            $messageId = $callbackQuery->getMessage()->getMessageId();
            $data = $callbackQuery->getData();

            // Memberi tahu Telegram bahwa kita sudah menerima callback
            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);

            $this->handleCallback($chatId, $messageId, $data);
        
        // 2. MENANGANI PESAN TEKS (Logika Anda yang sudah ada)
        } else if ($update->getMessage() && $update->getMessage()->has('text')) {
            $chatId = $update->getMessage()->getChat()->getId();
            $text = $update->getMessage()->getText();

    // Catat SEMUA pesan user ke database
    \App\Models\TelegramUserCommand::create([
        'user_id' => $chatId,
        'command' => $text,
    ]);

            if ($text === '/start' || $text === '/menu') {
                $this->showMainMenu($chatId);
            } else {
                switch ($text) {
                    case 'Cuaca di Jakarta 🌤️':
                        $this->sendWeatherInfo($chatId);
                        break;
                    case 'Nasihat Bijak 💡':
                        $this->sendAdvice($chatId);
                        break;
                    case 'Fakta Kucing 🐱':
                        $this->sendCatFact($chatId);
                        break;
                    case 'Tentang Developer 👨‍💻':
                        $this->sendDeveloperInfo($chatId);
                        break;
                    case 'Top List Crypto 📈':
                        $this->topListCrypto($chatId);
                        break;
                    case 'Aku Mau Kopi ☕️':
                        $this->coffeeGenerate($chatId);
                        break;
                    case 'Info Genshin 🎮':
                        $this->showGenshinCategories($chatId);
                        break;
                    case 'AI Chat 🤖':
                            $this->showGenshinCategories($chatId);
                    break;
                    default:
                        if (strtolower($text) === 'halo') {
                            $this->sendGreeting($chatId);
                        } else {
                            $this->sendUnknownCommand($chatId);
                        }
                        break;
                }
            }
        }

    return response()->json(['ok' => true]);
    }

    private function logUserActivity($update)
{
    try {
        $user = null;
        if ($update->isType('callback_query')) {
            $user = $update->getCallbackQuery()->getFrom();
        } else if ($update->getMessage()) {
            $user = $update->getMessage()->getFrom();
        }

        if ($user) {
            \App\Models\TelegramUser::updateOrCreate(
                ['user_id' => $user->getId()], // Kondisi pencarian
                [
                    'username' => $user->getUsername(),
                    'first_name' => $user->getFirstName(),
                    'last_name' => $user->getLastName(),
                    'last_interaction_at' => now(),
                ]
            );
        }
    } catch (\Exception $e) {
        Log::error('Gagal mencatat pengguna Telegram: ' . $e->getMessage());
    }
}

        /**
     * Router untuk semua callback_data dari inline keyboard.
     */
private function handleCallback($chatId, $messageId, $data)
{
    // Pola baru: genshin_page_{category}_{page}
    if (str_starts_with($data, 'genshin_page_')) {
        list($_, $_, $category, $page) = explode('_', $data, 4);
        $this->showItemsInCategory($chatId, $messageId, $category, (int)$page);
    }
    // Saat pertama kali memilih kategori, selalu mulai dari halaman 1
    else if (str_starts_with($data, 'category_')) {
        $category = substr($data, 9);
        $this->showItemsInCategory($chatId, $messageId, $category, 1); // Mulai dari page 1
    }
    else if (str_starts_with($data, 'item_')) {
        list($_, $category, $itemName) = explode('_', $data, 3);
        $this->showItemDetails($chatId, $messageId, $category, $itemName);
    }
    else if ($data === 'back_to_categories') {
        $this->showGenshinCategories($chatId, $messageId);
    }
}


    /**
     * Menampilkan menu utama dengan keyboard.
     */
    private function showMainMenu($chatId)
    {
        $keyboard = [
            ['Cuaca di Jakarta 🌤️', 'Nasihat Bijak 💡'],
            ['Fakta Kucing 🐱', 'Top List Crypto 📈'],
            ['Aku Mau Kopi ☕️','Info Genshin 🎮'],
            ['AI Chat 🤖','Tentang Developer 👨‍💻'],
        ];

        $reply_markup = Keyboard::make([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => 'Hai! 👋 Silakan pilih salah satu menu di bawah ini:',
            'reply_markup' => $reply_markup
        ]);
    }

        /**
     * Mengambil kategori dari API dan menampilkannya sebagai tombol inline.
     */
    private function showGenshinCategories($chatId, $messageId = null)
    {
        try {
            $response = Http::get('https://genshin.jmp.blue/');
            if ($response->successful()) {
                $categories = $response->json()['types'];
                $inlineKeyboard = [];

                foreach ($categories as $category) {
                    $inlineKeyboard[] = [
                        Keyboard::inlineButton([
                            'text' => ucwords(str_replace('-', ' ', $category)),
                            'callback_data' => 'category_' . $category
                        ])
                    ];
                }

                $messageData = [
                    'chat_id' => $chatId,
                    'text' => 'Pilih kategori Genshin Impact yang ingin Anda lihat:',
                    'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
                ];

                if ($messageId) {
                    // Jika ada messageId, EDIT pesan yang ada
                    $messageData['message_id'] = $messageId;
                    Telegram::editMessageText($messageData);
                } else {
                    // Jika tidak, KIRIM pesan baru
                    Telegram::sendMessage($messageData);
                }
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, gagal mengambil data kategori dari API.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil kategori Genshin: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat menghubungi API Genshin.']);
        }
    }

/**
 * Menampilkan daftar item dalam sebuah kategori dengan pagination.
 */
private function showItemsInCategory($chatId, $messageId, $category, $page = 1)
{
    // Tentukan berapa item yang ingin ditampilkan per halaman
    define('ITEMS_PER_PAGE', 20);

    try {
        $response = Http::get('https://genshin.jmp.blue/' . $category);

        if ($response->successful()) {
            $allItems = $response->json();
            
            // --- Logika Pagination ---
            $totalItems = count($allItems);
            $totalPages = ceil($totalItems / ITEMS_PER_PAGE);
            $offset = ($page - 1) * ITEMS_PER_PAGE;
            
            // Ambil hanya data untuk halaman saat ini
            $itemsForCurrentPage = array_slice($allItems, $offset, ITEMS_PER_PAGE);
            // --- Akhir Logika Pagination ---

            $inlineKeyboard = [];
            $row = [];

            // Buat tombol hanya untuk item di halaman ini
            foreach ($itemsForCurrentPage as $item) {
                $button = Keyboard::inlineButton([
                    'text' => ucwords(str_replace('-', ' ', $item)),
                    'callback_data' => 'item_' . $category . '_' . $item
                ]);
                $row[] = $button;
                if (count($row) == 2) {
                    $inlineKeyboard[] = $row;
                    $row = [];
                }
            }
            if (!empty($row)) {
                $inlineKeyboard[] = $row;
            }

            // --- Membuat Tombol Navigasi ---
            $navKeyboard = [];
            if ($page > 1) { // Jika bukan halaman pertama, tampilkan tombol PREV
                $navKeyboard[] = Keyboard::inlineButton([
                    'text' => '⬅️ Prev', 
                    'callback_data' => 'genshin_page_' . $category . '_' . ($page - 1)
                ]);
            }

            // Tombol penunjuk halaman
            $navKeyboard[] = Keyboard::inlineButton(['text' => "Page {$page}/{$totalPages}", 'callback_data' => 'no_action']);

            if ($page < $totalPages) { // Jika bukan halaman terakhir, tampilkan tombol NEXT
                $navKeyboard[] = Keyboard::inlineButton([
                    'text' => 'Next ➡️', 
                    'callback_data' => 'genshin_page_' . $category . '_' . ($page + 1)
                ]);
            }
            
            // Tambahkan baris tombol navigasi ke keyboard utama
            if (!empty($navKeyboard)) {
                $inlineKeyboard[] = $navKeyboard;
            }
            // --- Akhir Tombol Navigasi ---

            // Tambahkan tombol "Kembali" ke menu utama
            $inlineKeyboard[] = [Keyboard::inlineButton(['text' => '⬅️ Kembali ke Kategori', 'callback_data' => 'back_to_categories'])];

            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => 'Silakan pilih item dari kategori *' . ucwords($category) . '* (Halaman ' . $page . '):',
                'parse_mode' => 'Markdown',
                'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
            ]);
        }
    } catch (\Exception $e) {
        Log::error("Error ambil item Genshin ($category): " . $e->getMessage());
    }
}

    /**
     * Menampilkan detail dari item yang dipilih.
     */
private function showItemDetails($chatId, $messageId, $category, $itemName)
{
    try {
        $response = Http::get("https://genshin.jmp.blue/{$category}/{$itemName}");

        if ($response->successful()) {
            $details = $response->json();
            
            if (empty($details)) {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, detail untuk item ini tidak ditemukan.']);
                return;
            }

            // --- Logika Dinamis Dimulai Di Sini ---

            // Gunakan 'name' sebagai judul utama
            $text = "✨ *" . ($details['name'] ?? 'Detail Item') . "* ✨\n\n";

            // Daftar field yang tidak perlu ditampilkan dalam loop (karena sudah dipakai atau tidak relevan)
            $ignoreKeys = ['name', 'id', 'images', 'slug'];

            // Lakukan perulangan pada setiap data (key => value) yang diterima dari API
            foreach ($details as $key => $value) {
                // Lewati field yang ada di dalam daftar $ignoreKeys
                if (in_array($key, $ignoreKeys)) {
                    continue;
                }

                // Hanya tampilkan data yang bukan array atau object (data sederhana)
                if (is_scalar($value) && !empty($value)) {
                    // Ubah key menjadi lebih mudah dibaca (contoh: 'rarity' -> 'Rarity')
                    $formattedKey = ucwords(str_replace(['-', '_'], ' ', $key));
                    
                    // Tambahkan baris baru ke pesan
                    $text .= "🔹 *" . $formattedKey . ":* " . $value . "\n";
                }
            }
            // --- Akhir Logika Dinamis ---

            $inlineKeyboard = [[
                Keyboard::inlineButton([
                    'text' => '⬅️ Kembali ke ' . ucwords($category),
                    'callback_data' => 'category_' . $category
                ])
            ]];

            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => rtrim($text), // Menghapus spasi atau baris baru terakhir
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true, // Mencegah preview link jika ada
                'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard])
            ]);
        }
    } catch (\Exception $e) {
        Log::error("Error ambil detail Genshin ({$itemName}): " . $e->getMessage());
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil detail item.']);
    }
}

    private function topListCrypto($chatId)
    {
        try{
            $response = Http::get('https://api.coinlore.net/api/tickers/?start=0&limit=10');

            if($response->successful()){
                $coins = $response->json()['data'];
                $msg = "📊 Top 10 Cryptocurrency saat ini:\n\n";
                foreach($coins as $coin){
                    $price = number_format($coin['price_usd'], 2);
                    $change = number_format($coin['percent_change_24h'], 2);
                    $msg .= "{$coin['rank']}. {$coin['name']} ({$coin['symbol']})\nHarga: \${$price}\nPerubahan 24h: {$change}%\n\n";
                }
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $msg]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, tidak bisa mengambil data cryptocurrency sekarang.']);
            }
        } catch(\Exception $e){
            Log::error('Error ambil crypto: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil data cryptocurrency.']);
        }
    }

    private function coffeeGenerate($chatId)
    {
        try {
            $response = Http::get('https://coffee.alexflipnote.dev/random.json');
            if ($response->successful()) {
                $imageUrl = $response->json()['file'];
                $image = InputFile::create($imageUrl, 'coffee.jpg');
                Telegram::sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => $image,
                    'caption' => '☕️ Nikmati secangkir kopi virtual!'
                ]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, tidak bisa menghasilkan gambar kopi sekarang.']);
            }
        } catch (\Exception $e) {
            Log::error('Error generate kopi: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat menghasilkan gambar kopi.']);
        }
    }

    /**
     * Mengirim informasi cuaca.
     */
    private function sendWeatherInfo($chatId)
    {
        try {
            $response = Http::get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => -6.2, // Koordinat Jakarta
                'longitude' => 106.8,
                'current_weather' => true,
            ]);

            if ($response->successful()) {
                $weather = $response->json()['current_weather'];
                $time = date('d M Y, H:i', strtotime($weather['time']));
                
                $msg = "🌤 Cuaca saat ini di Jakarta:\n\n";
                $msg .= "Suhu: {$weather['temperature']}°C\n";
                $msg .= "Kecepatan Angin: {$weather['windspeed']} km/j\n";
                $msg .= "Update Terakhir: {$time} WIB";

                Telegram::sendMessage(['chat_id' => $chatId, 'text' => $msg]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, tidak bisa mengambil data cuaca sekarang.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil weather: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil data cuaca.']);
        }
    }

    /**
     * Mengirim nasihat acak.
     */
    private function sendAdvice($chatId)
    {
        try {
            $response = Http::get('https://api.adviceslip.com/advice');
            if ($response->successful()) {
                $advice = $response->json()['slip']['advice'];
                $translated = GoogleTranslate::trans($advice, 'id', 'en');
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => "💡 Nasihat hari ini:\n\"$translated\""]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, tidak bisa mengambil nasihat sekarang.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil nasihat: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil nasihat.']);
        }
    }

    /**
     * Mengirim fakta kucing acak.
     */
    private function sendCatFact($chatId)
    {
        try {
            $response = Http::get('https://catfact.ninja/fact');
            if ($response->successful()) {
                $fact = $response->json()['fact'];
                $translated = GoogleTranslate::trans($fact, 'id', 'en');
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => "🐱 Fakta tentang kucing:\n\"$translated\""]);
            } else {
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Maaf, saya tidak bisa mengambil fakta kucing saat ini.']);
            }
        } catch (\Exception $e) {
            Log::error('Error ambil fakta kucing: ' . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil fakta kucing.']);
        }
    }

    /**
     * Mengirim info tentang developer.
     */
    private function sendDeveloperInfo($chatId)
    {
        $responses = [
            // "Teguh Waluyojati adalah Developer Bot ini.\nhttps://teguhwaluyojati.github.io/",
            "Teguh Waluyojati adalah seorang yang berprofesi sebagai professional Full-Stack Developer.\nhttps://teguhwaluyojati.github.io/"
        ];
        $randomResponse = $responses[array_rand($responses)];
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => $randomResponse]);
    }
    
    /**
     * Mengirim sapaan halo.
     */
    private function sendGreeting($chatId)
    {
        $responses = [
            'Halo juga! Ada yang bisa dibantu? Tampilkan menu dengan /menu',
            'Hai! Semoga harimu menyenangkan. Coba ketik /menu',
            'Halo! Senang bertemu denganmu. Lihat menu dengan /menu',
        ];
        $randomResponse = $responses[array_rand($responses)];
        Telegram::sendMessage(['chat_id' => $chatId, 'text' => $randomResponse]);
    }

    /**
     * Mengirim pesan jika perintah tidak dikenali.
     */
    private function sendUnknownCommand($chatId)
    {
        $text = 'Maaf, saya tidak mengerti perintah itu. Silakan gunakan tombol menu di bawah atau ketik /menu untuk memulai.';
        // Panggil lagi menu utama agar pengguna tidak bingung
        $this->showMainMenu($chatId);
    }
}