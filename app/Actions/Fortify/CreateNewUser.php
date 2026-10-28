<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        // Limpiar el @ inicial si el usuario lo incluyó
        if (isset($input['username'])) {
            $input['username'] = ltrim(trim($input['username']), '@');
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-zA-Z0-9_]+$/',
                'unique:users,username',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => $this->passwordRules(),
        ], [
            'username.regex' => 'El nombre de usuario solo puede contener letras, números y guiones bajos (_).',
            'username.unique' => 'Este nombre de usuario ya está registrado en RESGUARDA.',
            'email.unique' => 'Este correo electrónico ya se encuentra en uso.',
        ])->validate();

        return User::create([
            'name' => trim($input['name']),
            'last_name' => trim($input['last_name']),
            'username' => $input['username'],
            'email' => strtolower(trim($input['email'])),
            'password' => $input['password'],
            'role' => 'user',
            'reliability_level' => 'new',
            'reputation_score' => 10,
            'status' => 'active',
        ]);
    }
}
