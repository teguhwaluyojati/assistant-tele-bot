<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Telegram\FinanceController;
use App\Http\Controllers\Telegram\GeminiController;
use App\Http\Controllers\Telegram\PoopController;
use App\Http\Controllers\Telegram\TradingController;
use App\Models\TelegramUser;
use App\Models\TelegramUserCommand;
use App\Services\Telegram\MenuService;
use App\Services\Telegram\TelegramMessageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramController extends Controller
{
    private TradingController $tradingController;
    private GeminiController $geminiController;
    private PoopController $poopController;
    private FinanceController $financeController;
    private MenuService $menuService;
    private TelegramMessageService $messageService;

    public function __construct(
        TradingController $tradingController,
        GeminiController $geminiController,
        PoopController $poopController,
        FinanceController $financeController,
        MenuService $menuService,
        TelegramMessageService $messageService
    ) {
        $this->tradingController = $tradingController;
        $this->geminiController = $geminiController;
        $this->poopController = $poopController;
        $this->financeController = $financeController;
        $this->menuService = $menuService;
        $this->messageService = $messageService;
    }

    /**
     * Handle incoming Telegram updates.
     */
    public function handle()
    {
        try {
            date_default_timezone_set('Asia/Jakarta');
            $update = Telegram::getWebhookUpdate();

            $user = $this->findOrCreateUser($update);

            if (!$user) {
                Log::warning('Request diabaikan: Gagal mendapatkan data user/chatId.');
                return response()->json(['ok' => true]);
            }

            if ($user->state === 'gemini_chat' && $user->last_interaction_at && now()->diffInMinutes($user->last_interaction_at->setTimezone('Asia/Jakarta')) > 5) {
                Log::info("User {$user->user_id} di-timeout dari mode Gemini.");
                $this->geminiController->exitGeminiChatMode($user, true);
                return response()->json(['ok' => true]);
            }

            $this->updateLastInteraction($user);

            if ($user->state !== 'normal') {
                $this->handleStatefulInput($user, $update);
            } else {
                $this->handleNormalMode($user, $update);
            }

            // return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            if (isset($update['message']['chat']['id'])) {
                $chatId = $update['message']['chat']['id'];
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => '⚠️ Terjadi kesalahan pada bot. Coba lagi nanti.']);
            }
        }
    }

    /**
     * Mencari atau membuat pengguna baru TANPA mengubah timestamp interaksi.
     * @param \Telegram\Bot\Objects\Update $update
     * @return \App\Models\TelegramUser|null
     */
    private function findOrCreateUser($update)
    {
        try {
            $from = $update->isType('callback_query') ? $update->getCallbackQuery()->getFrom() : $update->getMessage()->getFrom();
            if (!$from) {
                return null;
            }

            return \App\Models\TelegramUser::firstOrCreate(
                ['user_id' => $from->getId()],
                [
                    'username' => $from->getUsername(),
                    'first_name' => $from->getFirstName(),
                    'last_name' => $from->getLastName(),
                ]
            );
        } catch (\Exception $e) {
            Log::error('Gagal findOrCreateUser: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Memperbarui timestamp interaksi terakhir untuk pengguna.
     * @param \App\Models\TelegramUser $user
     */
    private function updateLastInteraction(\App\Models\TelegramUser $user)
    {
        $user->last_interaction_at = now();
        $user->save();
    }

    /**
     * Memproses input berdasarkan state pengguna saat ini.
     */
    private function handleStatefulInput(TelegramUser $user, $update)
    {
        try {
            if ($user->state === 'gemini_chat') {
                $this->geminiController->handleGeminiChatMode($user, $update);
            } else if ($user->state === 'editing_options') {
                $this->financeController->handleEditingChatMode($user, $update);
            } else if (str_starts_with($user->state, 'editing_')) {
                $this->financeController->handleEditTransactionInput($user, $update->getMessage()->getText());
            }
        } catch (\Throwable $e) {
            Log::error("Error di Stateful Input (State: {$user->state}): " . $e->getMessage());

            $user->state = 'normal';
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $user->chat_id,
                'text' => "⚠️ Terjadi kesalahan saat memproses permintaan Anda.\nStatus Anda telah dikembalikan ke menu utama (Normal Mode).",
            ]);
        }
    }

    /**
     * Memproses input dalam mode normal.
     */
    private function handleNormalMode(TelegramUser $user, $update)
    {
        if ($update->isType('callback_query')) {
            $callbackQuery = $update->getCallbackQuery();
            $chatId = $callbackQuery->getFrom()->getId();
            $messageId = $callbackQuery->getMessage()->getMessageId();
            $data = $callbackQuery->getData();

            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQuery->getId()]);
            \App\Models\TelegramUserCommand::create([
                'user_id' => $chatId,
                'command' => 'CALLBACK: ' . $data,
            ]);

            if (str_starts_with($data, 'genshin_page_')) {
                list($_, $_, $category, $page) = explode('_', $data, 4);
                $this->showItemsInCategory($chatId, $messageId, $category, (int) $page);
            } else if (str_starts_with($data, 'category_')) {
                $category = substr($data, 9);
                $this->showItemsInCategory($chatId, $messageId, $category, 1);
            } else if (str_starts_with($data, 'item_')) {
                list($_, $category, $itemName) = explode('_', $data, 3);
                $this->showItemDetails($chatId, $messageId, $category, $itemName);
            } else if ($data === 'back_to_categories') {
                $this->showGenshinCategories($chatId, $messageId);
            } else if (str_starts_with($data, 'delete_trx_')) {
                $transactionId = substr($data, 11);
                $this->financeController->deleteTransactionFromCallback($chatId, $messageId, $transactionId);
            } else if (str_starts_with($data, 'select_edit_trx_')) {
                $user->state = 'editing_options';
                $user->save();

                $transactionId = substr($data, 16);

                $this->financeController->showEditOptions($user, $messageId, $transactionId);
            } else if ($data === 'cancel_generic') {
                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => 'Proses dibatalkan.',
                    'reply_markup' => null,
                ]);
            } else if (str_starts_with($data, 'summary_')) {
                $period = substr($data, 8);
                $this->financeController->generateSummary($chatId, $messageId, $period);
            }
        } else if ($update->getMessage() && $update->getMessage()->has('text')) {
            $chatId = $update->getMessage()->getChat()->getId();
            $text = $update->getMessage()->getText();
            \App\Models\TelegramUserCommand::create([
                'user_id' => $chatId,
                'command' => $text,
            ]);

            if (str_starts_with($text, '/')) {
                if ($text === '/start' || $text === '/menu') {
                    $this->menuService->showMainMenu($chatId);
                } else if ($text === '/summary' || $text === '/laporan') {
                    $this->financeController->showSummaryOptions($chatId);
                } else if ($text === '/hapus') {
                    $this->financeController->showRecentTransactionsForDeletion($chatId);
                } else if ($text === '/edit') {
                    $this->financeController->startEditMode($user);
                } else if (str_starts_with($text, '/poop') || (str_starts_with($text, '/poophistory'))) {
                    $this->poopController->handlePoopCommand($chatId, $text);
                } else if ($text === '/swingtrade') {
                    $this->tradingController->swingTrade($chatId);
                } else if (str_starts_with(strtolower($text), '/saham')) {
                    $rawCode = substr($text, 6);

                    $code = strtoupper(trim(str_replace('-', '', $rawCode)));

                    if (empty($code)) {
                        $this->messageService->sendMessageSafely([
                            'chat_id' => $chatId,
                            'text' => "⚠️ Format salah.\nKetik: /saham-KODE\nContoh: /saham-BBCA",
                        ]);
                        return;
                    }

                    if (strlen($code) !== 4) {
                        $this->messageService->sendMessageSafely([
                            'chat_id' => $chatId,
                            'text' => "⚠️ Kode saham harus 4 huruf. Contoh: /saham-TLKM",
                        ]);
                        return;
                    }

                    $this->tradingController->analyzeAdvanced($chatId, $code);
                    return;
                } else if (str_starts_with(strtolower($text), '/scalping')) {
                    $parts = explode('-', $text, 2);

                    if (count($parts) < 2 || empty(trim($parts[1]))) {
                        $this->messageService->sendMessageSafely([
                            'chat_id' => $chatId,
                            'text' => "⚠️ Format salah.\nKetik: /scalping-KODE\nContoh: /scalping-BBCA",
                        ]);
                        return;
                    }

                    $code = strtoupper(trim($parts[1]));

                    if (!preg_match('/^[A-Z]{4}$/', $code)) {
                        $this->messageService->sendMessageSafely([
                            'chat_id' => $chatId,
                            'text' => "⚠️ Kode saham harus 4 huruf.\nContoh: /scalping-TLKM",
                        ]);
                        return;
                    }

                    $this->tradingController->analyzeScalpingV2($chatId, $code);
                    return;
                } else if (strpos($text, '/check') === 0) {
                    $input = trim(str_replace('/check', '', $text));

                    $analyzer = new \App\Http\Controllers\BSJPController();
                    $replyText = $analyzer->analyzeBsjp($input);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => $replyText,
                        'parse_mode' => 'Markdown',
                    ]);
                } else if ($text === '/bsjp') {
                    $this->tradingController->bsjp($chatId);
                } else {
                    $this->handleAdminCommands($chatId, $text);
                }
            } else if (str_starts_with($text, '+') || str_starts_with($text, '-')) {
                $this->financeController->recordTransaction($chatId, $text);
            } else {
                switch ($text) {
                    case 'AI Chat 🤖':
                        $this->geminiController->enterGeminiChatMode($user, $chatId);
                        break;
                    case 'Cuaca di Jakarta 🌤️':
                        $this->sendWeatherInfo($chatId);
                        break;
                    case 'Nasihat Bijak 💡':
                        $this->sendAdvice($chatId);
                        break;
                    case 'Fakta Kucing 🐱':
                        $this->sendCatFact($chatId);
                        break;
                    case 'Aku Mau Kopi ☕️':
                        $this->coffeeGenerate($chatId);
                        break;
                    case 'Tentang Developer 👨‍💻':
                        $this->sendDeveloperInfo($chatId);
                        break;
                    case 'Money Tracker 💸':
                        $this->financeController->showMoneyTrackerMenu($chatId);
                        break;
                    // case 'Info Genshin 🎮': $this->showGenshinCategories($chatId); break;
                    // case 'Poop Tracker 💩': $this->poopController->sendPoopTrackerInfo($chatId); break;
                    case 'Info Saham 📊':
                        $this->tradingController->analyzeAdvanced($chatId, 'BIPI');
                        break;
                    case 'Swing Trade Saham 📊':
                        $this->tradingController->swingTrade($chatId);
                        break;
                    case 'BSJP Saham 📊':
                        $this->tradingController->bsjp($chatId);
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
    }

    private function isUserAdmin($chatId)
    {
        $adminIdsString = env('TELEGRAM_ADMIN_ID');

        if (!$adminIdsString) {
            return false;
        }

        $adminIdsArray = explode(',', $adminIdsString);

        return in_array((string) $chatId, $adminIdsArray);
    }

    private function handleAdminCommands($chatId, $text)
    {
        if (!$this->isUserAdmin($chatId)) {
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => '🚫 Anda tidak memiliki izin untuk menggunakan perintah ini.',
            ]);
            return;
        }

        if ($text === '/listusers') {
            $users = TelegramUser::latest('last_interaction_at')->take(10)->get();

            if ($users->isEmpty()) {
                $this->messageService->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => 'Belum ada pengguna yang tercatat.',
                ]);
                return;
            }

            $message = "👥 *10 Pengguna Terakhir:*\n\n";
            foreach ($users as $user) {
                $username = $user->username ? '@' . $user->username : 'N/A';
                $message .= "ID: `{$user->id}`\n";
                $message .= "Nama: {$user->first_name}\n";
                $message .= "Username: {$username}\n";
                $message .= "User ID: {$user->user_id}\n";
                $message .= "Last Active: {$user->last_interaction_at}\n";
                $message .= "--------------------\n";
            }
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
        } else if (str_starts_with($text, '/usercommands ')) {
            $targetUserId = substr($text, 14);

            if (!is_numeric($targetUserId)) {
                $this->messageService->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => 'Format salah. Gunakan: `/usercommands [user_id]`',
                ]);
                return;
            }

            $commands = TelegramUserCommand::where('user_id', $targetUserId)
                ->latest('created_at')->take(10)->get();

            if ($commands->isEmpty()) {
                $this->messageService->sendMessageSafely([
                    'chat_id' => $chatId,
                    'text' => "Tidak ditemukan perintah untuk User ID: `{$targetUserId}`",
                ]);
                return;
            }

            $message = "📜 *10 Perintah Terakhir dari User ID `{$targetUserId}`:*\n\n";
            foreach ($commands as $command) {
                $rawCommandText = $command->command;

                $safeCommandText = $this->messageService->escapeMarkdown($rawCommandText);

                $message .= '`' . $command->created_at->format('Y-m-d H:i') . "`\n";
                $message .= "> {$safeCommandText}\n\n";
            }

            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ]);
        } else {
            $this->messageService->sendMessageSafely([
                'chat_id' => $chatId,
                'text' => "Perintah admin tidak dikenal. Gunakan:\n`/listusers`\n`/usercommands [user_id]`",
            ]);
        }
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
                            'callback_data' => 'category_' . $category,
                        ]),
                    ];
                }

                $messageData = [
                    'chat_id' => $chatId,
                    'text' => 'Pilih kategori Genshin Impact yang ingin Anda lihat:',
                    'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard]),
                ];

                if ($messageId) {
                    $messageData['message_id'] = $messageId;
                    Telegram::editMessageText($messageData);
                } else {
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
        define('ITEMS_PER_PAGE', 20);

        try {
            $response = Http::get('https://genshin.jmp.blue/' . $category);

            if ($response->successful()) {
                $allItems = $response->json();
                $totalItems = count($allItems);
                $totalPages = ceil($totalItems / ITEMS_PER_PAGE);
                $offset = ($page - 1) * ITEMS_PER_PAGE;
                $itemsForCurrentPage = array_slice($allItems, $offset, ITEMS_PER_PAGE);
                $inlineKeyboard = [];
                $row = [];

                foreach ($itemsForCurrentPage as $item) {
                    $button = Keyboard::inlineButton([
                        'text' => ucwords(str_replace('-', ' ', $item)),
                        'callback_data' => 'item_' . $category . '_' . $item,
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

                $navKeyboard = [];
                if ($page > 1) {
                    $navKeyboard[] = Keyboard::inlineButton([
                        'text' => '⬅️ Prev',
                        'callback_data' => 'genshin_page_' . $category . '_' . ($page - 1),
                    ]);
                }

                $navKeyboard[] = Keyboard::inlineButton(['text' => "Page {$page}/{$totalPages}", 'callback_data' => 'no_action']);

                if ($page < $totalPages) {
                    $navKeyboard[] = Keyboard::inlineButton([
                        'text' => 'Next ➡️',
                        'callback_data' => 'genshin_page_' . $category . '_' . ($page + 1),
                    ]);
                }

                if (!empty($navKeyboard)) {
                    $inlineKeyboard[] = $navKeyboard;
                }

                $inlineKeyboard[] = [Keyboard::inlineButton(['text' => '⬅️ Kembali ke Kategori', 'callback_data' => 'back_to_categories'])];

                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => 'Silakan pilih item dari kategori *' . ucwords($category) . '* (Halaman ' . $page . '):',
                    'parse_mode' => 'Markdown',
                    'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error ambil item Genshin ({$category}): " . $e->getMessage());
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

                $text = '✨ *' . ($details['name'] ?? 'Detail Item') . "* ✨\n\n";

                $ignoreKeys = ['name', 'id', 'images', 'slug'];

                foreach ($details as $key => $value) {
                    if (in_array($key, $ignoreKeys)) {
                        continue;
                    }
                    if (is_scalar($value) && !empty($value)) {
                        $formattedKey = ucwords(str_replace(['-', '_'], ' ', $key));
                        $text .= '🔹 *' . $formattedKey . ':* ' . $value . "\n";
                    }
                }

                $inlineKeyboard = [[
                    Keyboard::inlineButton([
                        'text' => '⬅️ Kembali ke ' . ucwords($category),
                        'callback_data' => 'category_' . $category,
                    ]),
                ]];

                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => rtrim($text),
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true,
                    'reply_markup' => Keyboard::make(['inline_keyboard' => $inlineKeyboard]),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error ambil detail Genshin ({$itemName}): " . $e->getMessage());
            Telegram::sendMessage(['chat_id' => $chatId, 'text' => 'Terjadi kesalahan saat mengambil detail item.']);
        }
    }

    /**
     * Menghasilkan dan mengirim gambar kopi acak.
     */
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
                    'caption' => '☕️ Nikmati secangkir kopi virtual!',
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
                'latitude' => -6.2,
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
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => "💡 Nasihat hari ini:\n\"{$translated}\""]);
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
                Telegram::sendMessage(['chat_id' => $chatId, 'text' => "🐱 Fakta tentang kucing:\n\"{$translated}\""]);
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
            "Teguh Waluyojati adalah seorang yang berprofesi sebagai professional Full-Stack Developer.\nhttps://teguhwaluyojati.github.io/",
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
        $this->menuService->showMainMenu($chatId);
    }

    /**
     * API untuk broadcast daily pengeluaran ke semua user.
     */
    public function broadcastDailyExpenses()
    {
        $this->financeController->broadcastDailyExpenses();
    }
}
