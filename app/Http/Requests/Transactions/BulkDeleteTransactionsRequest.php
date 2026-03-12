<?php

namespace App\Http\Requests\Transactions;

use App\Http\Requests\ApiFormRequest;

class BulkDeleteTransactionsRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|distinct|exists:transactions,id',
        ];
    }
}
