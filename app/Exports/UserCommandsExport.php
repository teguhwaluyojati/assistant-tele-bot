<?php

namespace App\Exports;

use App\Models\TelegramUserCommand;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UserCommandsExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private ?string $search;
    private ?string $startDate;
    private ?string $endDate;

    public function __construct(?string $search = null, ?string $startDate = null, ?string $endDate = null)
    {
        $this->search = $search ? trim($search) : null;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function query()
    {
        $query = TelegramUserCommand::query()
            ->leftJoin('telegram_users', 'telegram_user_commands.user_id', '=', 'telegram_users.user_id')
            ->select([
                'telegram_user_commands.id',
                'telegram_user_commands.command',
                'telegram_user_commands.user_id',
                'telegram_user_commands.created_at',
                'telegram_users.username',
                'telegram_users.first_name',
                'telegram_users.last_name',
            ])
            ->latest('telegram_user_commands.created_at');

        if (!empty($this->search)) {
            $keyword = '%' . $this->search . '%';
            $query->where(function ($builder) use ($keyword) {
                $builder
                    ->where('telegram_user_commands.command', 'like', $keyword)
                    ->orWhere('telegram_users.username', 'like', $keyword)
                    ->orWhere('telegram_users.first_name', 'like', $keyword)
                    ->orWhere('telegram_users.last_name', 'like', $keyword)
                    ->orWhere('telegram_user_commands.user_id', 'like', $keyword);
            });
        }

        if (!empty($this->startDate)) {
            $query->whereDate('telegram_user_commands.created_at', '>=', $this->startDate);
        }

        if (!empty($this->endDate)) {
            $query->whereDate('telegram_user_commands.created_at', '<=', $this->endDate);
        }

        return $query;
    }

    public function headings(): array
    {
        return ['Date', 'User', 'Username', 'Telegram ID', 'Command'];
    }

    public function map($row): array
    {
        $fullName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));

        return [
            optional($row->created_at)->format('Y-m-d H:i:s'),
            $fullName !== '' ? $fullName : 'Unknown',
            $row->username ? '@' . $row->username : '-',
            $row->user_id,
            $row->command,
        ];
    }
}
