<?php

namespace App\Imports;

use App\Models\Stock;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;

class StocksImport implements ToModel, WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        Log::info('Baris Excel:', $row);

        if (!isset($row['kode'])) {
            Log::warning('Key KODE tidak ditemukan. Key yang tersedia: ' . implode(', ', array_keys($row)));
            return null;
        }

        if (isset($row['papan_pencatatan']) && $row['papan_pencatatan'] === 'Pemantauan Khusus') {
            Log::info('Data dengan kode ' . $row['kode'] . ' dilewati karena status Pemantauan Khusus');
            return null;
        }

        return Stock::updateOrCreate(
            ['code' => strtoupper($row['kode'])], 
            [
                'name'      => $row['nama'] ?? null,
                'status'     => $row['papan_pencatatan'],
                'is_active' => true,
            ]
        );
    }
}
