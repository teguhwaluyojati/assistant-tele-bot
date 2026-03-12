<?php

namespace App\Http\Requests\Transactions;

use App\Http\Requests\ApiFormRequest;

class StoreTransactionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'integer', 'min:1'],
            'transaction_date' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'description' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }
}
