<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeSimatRpsAdmin extends Command
{
    protected $signature = 'simatrps:make-admin
                            {email : Email akun admin}
                            {--name=Admin SiMatRPS : Nama lengkap admin}';

    protected $description = 'Membuat atau mengubah akun menjadi Admin SiMatRPS';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) $this->option('name'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Masukkan password admin (minimal 8 karakter)');

        if (strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');
            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $name !== '' ? $name : 'Admin SiMatRPS',
            'email' => $email,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'password' => Hash::make($password),
            'role' => 'admin',
            'is_active' => true,
        ])->save();

        $this->newLine();
        $this->info('Admin SiMatRPS berhasil disiapkan.');
        $this->line("Email : {$user->email}");
        $this->line("Nama  : {$user->name}");
        $this->line("Role  : {$user->role}");

        return self::SUCCESS;
    }
}
