<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResguardaModeController extends Controller
{
    public function mode(Request $request): Response
    {
        return Inertia::render('resguarda-mode/Index', [
            'user' => [
                'name' => $request->user()?->name,
                'username' => $request->user()?->username,
            ],
            'status' => 'active',
            'districts' => [],
        ]);
    }

    public function emergency(Request $request): Response
    {
        $contacts = $request->user()?->emergencyContacts()
            ->latest()
            ->get()
            ->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'relationship' => $contact->relationship,
            ])
            ->values()
            ->all() ?? [];

        return Inertia::render('Emergency', [
            'user' => [
                'name' => $request->user()?->name,
                'username' => $request->user()?->username,
            ],
            'status' => 'emergency',
            'contacts' => $contacts,
        ]);
    }
}
