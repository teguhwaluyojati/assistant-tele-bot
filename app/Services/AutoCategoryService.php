<?php

namespace App\Services;

class AutoCategoryService
{
    private const DEFAULT_MIN_CONFIDENCE = 0.60;

    private const CATEGORY_KEYWORDS = [
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
    ];

    private const CATEGORY_SYNONYMS = [
        'expense' => [
            'Food & Drink' => ['mkn', 'makanan', 'jajan', 'ngopi', 'coffee', 'coffeeshop', 'warkop', 'go food', 'grab food'],
            'Transport' => ['bensin motor', 'isi bensin', 'naik ojol', 'go ride', 'grab bike', 'angkot'],
            'Bills & Utilities' => ['tagihan listrik', 'tagihan air', 'bayar internet', 'paket data', 'data plan'],
            'Shopping' => ['beli barang', 'checkout', 'keranjang', 'ecommerce', 'online shop'],
            'Health' => ['berobat', 'periksa', 'medical checkup', 'cek kesehatan'],
            'Education' => ['belajar', 'kelas', 'bootcamp', 'ujian'],
            'Entertainment' => ['nntn', 'nontonin', 'film', 'movie', 'cinema', 'bioskopan', 'nongkrong', 'hangout', 'mabar', 'streaming'],
        ],
        'income' => [
            'Salary' => ['gajian', 'salary bulanan', 'payday'],
            'Bonus' => ['bonus kantor', 'reward', 'incentive'],
            'Business' => ['closing', 'deal', 'fee project', 'jasa'],
            'Investment' => ['cuan saham', 'profit saham', 'bunga deposito'],
            'Gift' => ['dikasih', 'pemberian', 'angpao'],
        ],
    ];

    private const NORMALIZATION_MAP = [
        'nntn' => 'nonton',
        'nontonin' => 'nonton',
        'filim' => 'film',
        'flm' => 'film',
        'mkn' => 'makan',
        'jln2' => 'jalan jalan',
        'go food' => 'gofood',
        'grab food' => 'grabfood',
        'gajian' => 'gaji',
    ];

    public function infer(?string $description, ?string $type = null): ?array
    {
        $normalized = $this->normalize($description);
        if ($normalized === '') {
            return null;
        }

        $typesToCheck = [];
        if (in_array($type, ['income', 'expense'], true)) {
            $typesToCheck[] = $type;
        } else {
            $typesToCheck = ['expense', 'income'];
        }

        $bestCategory = null;
        $bestScore = 0;

        foreach ($typesToCheck as $candidateType) {
            $categories = $this->categoryKeywords()[$candidateType] ?? [];
            $synonyms = $this->categorySynonyms()[$candidateType] ?? [];

            foreach ($categories as $category => $keywords) {
                $keywords = array_values(array_unique(array_merge($keywords, $synonyms[$category] ?? [])));
                $score = 0;
                foreach ($keywords as $keyword) {
                    $score += $this->keywordScore($normalized, $keyword);
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestCategory = $category;
                }
            }
        }

        if ($bestScore <= 0 || $bestCategory === null) {
            return null;
        }

        $confidence = min(0.97, 0.46 + ($bestScore * 0.16));

        if ($confidence < $this->minConfidence()) {
            return null;
        }

        return [
            'category' => $bestCategory,
            'confidence' => round($confidence, 2),
        ];
    }

    private function keywordScore(string $normalizedDescription, string $keyword): float
    {
        $normalizedKeyword = $this->normalize($keyword);
        if ($normalizedKeyword === '') {
            return 0;
        }

        if (str_contains($normalizedKeyword, ' ')) {
            return str_contains($normalizedDescription, $normalizedKeyword) ? 1.2 : 0;
        }

        $pattern = '/\\b' . preg_quote($normalizedKeyword, '/') . '\\b/u';

        return preg_match($pattern, $normalizedDescription) === 1 ? 1.0 : 0;
    }

    private function normalize(?string $text): string
    {
        $normalized = strtolower(trim((string) $text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';

        foreach ($this->normalizationMap() as $variant => $canonical) {
            $pattern = '/\\b' . preg_quote($variant, '/') . '\\b/u';
            $normalized = preg_replace($pattern, $canonical, $normalized) ?? $normalized;
        }

        $normalized = preg_replace('/(.)\\1{2,}/u', '$1$1', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return $normalized;
    }

    private function categoryKeywords(): array
    {
        return config('autocategory.category_keywords', self::CATEGORY_KEYWORDS);
    }

    private function categorySynonyms(): array
    {
        return config('autocategory.category_synonyms', self::CATEGORY_SYNONYMS);
    }

    private function normalizationMap(): array
    {
        return config('autocategory.normalization_map', self::NORMALIZATION_MAP);
    }

    private function minConfidence(): float
    {
        return (float) config('autocategory.min_confidence', self::DEFAULT_MIN_CONFIDENCE);
    }
}
