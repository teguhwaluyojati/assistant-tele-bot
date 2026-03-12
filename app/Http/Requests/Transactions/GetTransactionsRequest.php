<?php

namespace App\Http\Requests\Transactions;

use App\Http\Requests\ApiFormRequest;

class GetTransactionsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'type' => ['nullable', 'in:all,income,expense'],
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'sort' => ['nullable', 'in:created_at,amount,type,description'],
            'direction' => ['nullable', 'in:asc,desc'],
        ];
    }
}
