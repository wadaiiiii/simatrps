<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'academic_title',
                    'nidn',
                    'email',
                    'role',
                    'is_active',
                    'created_at',
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_title' => ['nullable', 'string', 'max:100'],
            'nidn' => ['nullable', 'string', 'max:50', 'unique:users,nidn'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = new User();
        $user->name = trim($validated['name']);
        $user->academic_title = filled($validated['academic_title'] ?? null)
            ? trim($validated['academic_title'])
            : null;
        $user->nidn = filled($validated['nidn'] ?? null)
            ? trim($validated['nidn'])
            : null;
        $user->email = strtolower(trim($validated['email']));
        $user->password = $validated['password'];
        $user->role = 'dosen';
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();

        return back()->with('success', 'Akun dosen berhasil dibuat dan sudah dapat digunakan untuk login.');
    }
}
