<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        Validator::make($input, [
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'name' => $this->fallbackName($input['email']),
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    private function fallbackName(string $email): string
    {
        $name = trim(Str::before($email, '@'));

        return $name === '' ? 'NEAREON Nutzer' : $name;
    }
}
