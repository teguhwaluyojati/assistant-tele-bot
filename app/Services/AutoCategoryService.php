<?php

namespace App\Services;

class AutoCategoryService
{
    private const CATEGORY_KEYWORDS = [
        'expense' => [
            'Food & Drink' => ['makan', 'makanan', 'sarapan', 'lunch', 'dinner', 'kopi', 'cafe', 'resto', 'warung', 'gofood', 'grabfood'],
            'Transport' => ['transport', 'bensin', 'bbm', 'tol', 'parkir', 'ojek', 'grab', 'gocar', 'taxi', 'bus', 'kereta'],
            'Bills & Utilities' => ['listrik', 'air', 'internet', 'wifi', 'pln', 'tagihan', 'pulsa', 'token', 'bpjs'],
            'Shopping' => ['belanja', 'shop', 'mall', 'alfamart', 'indomaret', 'minimarket', 'supermarket', 'marketplace', 'tokopedia', 'shopee'],
            'Health' => ['obat', 'dokter', 'klinik', 'rumah sakit', 'hospital', 'apotek', 'vitamin', 'medical'],
            'Education' => ['kursus', 'sekolah', 'kuliah', 'buku', 'les', 'training', 'sertifikasi'],
            'Entertainment' => ['bioskop', 'netflix', 'spotify', 'game', 'hiburan', 'rekreasi', 'travel', 'liburan'],
        ],
        'income' => [
            'Salary' => ['gaji', 'salary', 'payroll', 'upah'],
            'Bonus' => ['bonus', 'insentif', 'thr', 'komisi'],
            'Business' => ['penjualan', 'jualan', 'omzet', 'profit', 'project', 'invoice'],
            'Investment' => ['dividen', 'bunga', 'investasi', 'return', 'capital gain'],
            'Gift' => ['hadiah', 'gift', 'hibah', 'transfer masuk', 'uang kaget'],
        ],
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
            $categories = self::CATEGORY_KEYWORDS[$candidateType] ?? [];

            foreach ($categories as $category => $keywords) {
                $score = 0;
                foreach ($keywords as $keyword) {
                    if (str_contains($normalized, $keyword)) {
                        $score++;
                    }
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

        $confidence = min(0.95, 0.50 + ($bestScore * 0.15));

        return [
            'category' => $bestCategory,
            'confidence' => round($confidence, 2),
        ];
    }

    private function normalize(?string $text): string
    {
        $normalized = strtolower(trim((string) $text));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return $normalized;
    }
}
