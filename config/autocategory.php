<?php

return [
    'min_confidence' => 0.60,
    'log_decisions' => true,

    'ml' => [
        'enabled' => true,
        'cache_ttl_seconds' => 600,
        'min_samples' => 8,
        'min_confidence' => 0.70,
        'min_margin' => 0.30,
        'allowed_sources' => ['manual'],
        'stopwords' => [
            'dan', 'yang', 'di', 'ke', 'dari', 'untuk', 'pada', 'ini', 'itu', 'aja', 'saja',
            'bulan', 'hari', 'minggu', 'tahun', 'biaya', 'bayar', 'beli', 'uang', 'transfer',
        ],
    ],

    'llm' => [
        'enabled' => false,
        'provider' => 'openai-compatible',
        'endpoint' => env('AUTOCATEGORY_LLM_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        'api_key' => env('AUTOCATEGORY_LLM_API_KEY', ''),
        'model' => env('AUTOCATEGORY_LLM_MODEL', 'gpt-4o-mini'),
        'timeout_seconds' => 8,
        'cache_ttl_seconds' => 3600,
        'rollout_percentage' => 10,
        'max_requests_per_minute' => 30,
        'min_confidence' => 0.75,
    ],

    'category_keywords' => [
        'expense' => [
            'Food & Drink' => ['makan', 'makanan', 'sarapan', 'lunch', 'dinner', 'kopi', 'cafe', 'resto', 'warung', 'gofood', 'grabfood'],
            'Transport' => ['transport', 'bensin', 'bbm', 'tol', 'parkir', 'ojek', 'grab', 'gocar', 'taxi', 'bus', 'kereta'],
            'Bills & Utilities' => ['listrik', 'air', 'internet', 'wifi', 'pln', 'tagihan', 'pulsa', 'token', 'bpjs'],
            'Shopping' => ['belanja', 'shop', 'mall', 'alfamart', 'indomaret', 'minimarket', 'supermarket', 'marketplace', 'tokopedia', 'shopee'],
            'Health' => ['obat', 'dokter', 'klinik', 'rumah sakit', 'hospital', 'apotek', 'vitamin', 'medical'],
            'Education' => ['kursus', 'sekolah', 'kuliah', 'buku', 'les', 'training', 'sertifikasi'],
            'Entertainment' => ['nonton', 'film', 'movie', 'cinema', 'bioskop', 'netflix', 'spotify', 'game', 'hiburan', 'rekreasi', 'travel', 'liburan'],
        ],
        'income' => [
            'Salary' => ['gaji', 'salary', 'payroll', 'upah'],
            'Bonus' => ['bonus', 'insentif', 'thr', 'komisi'],
            'Business' => ['penjualan', 'jualan', 'omzet', 'profit', 'project', 'invoice'],
            'Investment' => ['dividen', 'bunga', 'investasi', 'return', 'capital gain'],
            'Gift' => ['hadiah', 'gift', 'hibah', 'transfer masuk', 'uang kaget'],
        ],
    ],

    'category_synonyms' => [
        'expense' => [
            'Food & Drink' => ['mkn', 'jajan', 'ngopi', 'coffee', 'coffeeshop', 'warkop', 'go food', 'grab food', 'bakso', 'mie ayam', 'nasi goreng'],
            'Transport' => ['bensin motor', 'isi bensin', 'naik ojol', 'go ride', 'grab bike', 'angkot', 'parkiran', 'tiket kereta', 'tiket bus'],
            'Bills & Utilities' => ['tagihan listrik', 'tagihan air', 'bayar internet', 'paket data', 'data plan', 'bayar wifi', 'token listrik', 'bayar bpjs', 'cicilan', 'kontrakan', 'sewa kos'],
            'Shopping' => ['beli barang', 'checkout', 'keranjang', 'ecommerce', 'online shop', 'topup', 'top up', 'isi saldo', 'beli kebutuhan', 'bayar marketplace'],
            'Health' => ['berobat', 'periksa', 'medical checkup', 'cek kesehatan', 'tebus obat', 'rawat jalan'],
            'Education' => ['belajar', 'kelas', 'bootcamp', 'ujian', 'modul', 'biaya sekolah'],
            'Entertainment' => ['nntn', 'nontonin', 'film', 'movie', 'cinema', 'bioskopan', 'nongkrong', 'nongki', 'hangout', 'mabar', 'streaming', 'karaoke', 'healing', 'ngabuburit'],
        ],
        'income' => [
            'Salary' => ['gajian', 'salary bulanan', 'payday', 'honor bulanan', 'fee tetap'],
            'Bonus' => ['bonus kantor', 'reward', 'incentive', 'thr kantor', 'uang lembur'],
            'Business' => ['closing', 'deal', 'fee project', 'jasa', 'order masuk', 'hasil jualan'],
            'Investment' => ['cuan saham', 'profit saham', 'bunga deposito', 'capital gain', 'return investasi'],
            'Gift' => ['dikasih', 'pemberian', 'angpao', 'hadiah ulang tahun', 'transfer dari keluarga'],
        ],
    ],

    'normalization_map' => [
        'nntn' => 'nonton',
        'nontonin' => 'nonton',
        'filim' => 'film',
        'flm' => 'film',
        'mkn' => 'makan',
        'jln2' => 'jalan jalan',
        'nongki' => 'nongkrong',
        'top up' => 'topup',
        'go food' => 'gofood',
        'grab food' => 'grabfood',
        'gajian' => 'gaji',
        'thr' => 'bonus',
    ],
];
