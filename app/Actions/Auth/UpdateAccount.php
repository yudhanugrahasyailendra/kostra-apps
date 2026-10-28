<?php

namespace App\Actions\Auth;

use App\Models\User;

class UpdateAccount
{
    /**
     * @param  array{name: string, email: ?string, phone: ?string, password?: ?string}  $data
     */
    public function __invoke(User $user, array $data): User
    {
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ]);

        // Kata sandi hanya berubah bila diisi; cast "hashed" yang mengenkripsinya.
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }

        $user->save();

        return $user;
    }
}
