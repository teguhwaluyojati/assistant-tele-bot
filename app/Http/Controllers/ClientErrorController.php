<?php

namespace App\Http\Controllers;

use App\Services\TelegramNotifier;
use Illuminate\Http\Request;

class ClientErrorController extends Controller
{
    public function store(Request $request, TelegramNotifier $notifier)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'stack' => ['nullable', 'string', 'max:4000'],
            'url' => ['nullable', 'string', 'max:500'],
            'component' => ['nullable', 'string', 'max:120'],
            'userAgent' => ['nullable', 'string', 'max:300'],
        ]);

        $user = $request->user();

        $notifier->notifyError('Frontend Error', [
            'url' => $validated['url'] ?? '- ',
            'message' => $validated['message'],
            'component' => $validated['component'] ?? '-',
            'user' => $user?->email ?? 'guest',
            'ip' => $request->ip(),
        ]);

        return response()->json(['success' => true]);
    }
}
