<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmergencyContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmergencyContactController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = $request->user()
            ->emergencyContacts()
            ->latest()
            ->get()
            ->map(fn (EmergencyContact $contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'relationship' => $contact->relationship,
            ])
            ->values()
            ->all();

        return Inertia::render('settings/EmergencyContacts', [
            'contacts' => $contacts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->emergencyContacts()->create($this->validatedContact($request));

        return to_route('emergency-contacts.index');
    }

    public function update(Request $request, EmergencyContact $emergencyContact): RedirectResponse
    {
        abort_unless($emergencyContact->user_id === $request->user()->id, 403, 'No autorizado.');

        $emergencyContact->update($this->validatedContact($request));

        return to_route('emergency-contacts.index');
    }

    public function destroy(EmergencyContact $emergencyContact): RedirectResponse
    {
        abort_unless($emergencyContact->user_id === Auth::id(), 403, 'No autorizado.');

        $emergencyContact->delete();

        return to_route('emergency-contacts.index');
    }

    /**
     * @return array{name: string, phone: string, email: string|null, relationship: string|null}
     */
    private function validatedContact(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:254'],
            'relationship' => ['nullable', 'string', 'max:50'],
        ]);

        $validated['email'] = isset($validated['email'])
            ? mb_strtolower(trim($validated['email']))
            : null;

        return $validated;
    }
}
