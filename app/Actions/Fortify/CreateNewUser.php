<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Carbon;
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
        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'birthdate' => ['required', 'date'],
            'password' => $this->passwordRules(),
        ]);

        $validator->after(function ($validator) use ($input): void {
            if ($validator->errors()->has('birthdate')) {
                return;
            }

            $birthdate = Carbon::parse($input['birthdate'])->startOfDay();
            $minimumBirthdate = now()->subYears(14)->startOfDay();

            if ($birthdate->greaterThan($minimumBirthdate)) {
                $validator->errors()->add(
                    'birthdate',
                    __('NEAREON kann aktuell erst ab 14 Jahren genutzt werden.'),
                );
            }
        });

        $validator->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'birthdate' => $input['birthdate'],
            'age_gate_passed_at' => now(),
            'password' => $input['password'],
        ]);
    }
}
