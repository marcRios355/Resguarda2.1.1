<?php

namespace App\Http\Controllers;

use App\Models\CommunityMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunityChatController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('communication.index', array_merge(
            ['tab' => 'community'],
            $request->only('district_id'),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'district_id' => ['nullable', 'exists:districts,id'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        CommunityMessage::create([
            'user_id' => $request->user()->id,
            'district_id' => $validated['district_id'] ?? null,
            'message' => $validated['message'],
            'is_reported' => false,
            'report_count' => 0,
            'is_hidden' => false,
        ]);

        return redirect()->route('communication.index', ['tab' => 'community'])
            ->with('status', 'Mensaje publicado en el chat comunitario.');
    }
}
