<?php

namespace App\Exports;

use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AuditLogsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private ?string $startDate;
    private ?string $endDate;
    private ?string $search;

    public function __construct(?string $startDate = null, ?string $endDate = null, ?string $search = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search ? trim($search) : null;
    }

    public function query()
    {
        $query = Activity::with('causer')->latest();

        if (!empty($this->startDate)) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }

        if (!empty($this->endDate)) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }

        if (!empty($this->search)) {
            $keyword = $this->search;
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('description', 'like', "%{$keyword}%")
                    ->orWhere('subject_type', 'like', "%{$keyword}%")
                    ->orWhere('log_name', 'like', "%{$keyword}%")
                    ->orWhere('event', 'like', "%{$keyword}%")
                    ->orWhere('properties', 'like', "%{$keyword}%")
                    ->orWhereHasMorph('causer', [User::class], function ($causerQuery) use ($keyword) {
                        $causerQuery
                            ->where('name', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%");
                    });
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Date',
            'Action',
            'User',
            'Subject',
        ];
    }

    public function map($log): array
    {
        $causer = $log->causer;
        $causerLabel = $causer?->email ?: ($causer?->name ?: 'system');
        $subject = $log->subject_type ? class_basename($log->subject_type) : '-';

        return [
            optional($log->created_at)->format('Y-m-d H:i:s'),
            $log->description,
            $causerLabel,
            $subject,
        ];
    }
}
