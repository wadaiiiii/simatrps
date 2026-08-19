<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::query()
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
            ])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'academic_title' => $user->academic_title,
                'nidn' => $user->nidn,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
                'rps_count' => DB::table('rps')->where('owner_id', $user->id)->count(),
            ]);

        return Inertia::render('admin/users', ['users' => $users]);
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

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertLecturer($user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'academic_title' => ['nullable', 'string', 'max:100'],
            'nidn' => ['nullable', 'string', 'max:50', Rule::unique('users', 'nidn')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->name = trim($validated['name']);
        $user->academic_title = filled($validated['academic_title'] ?? null)
            ? trim($validated['academic_title'])
            : null;
        $user->nidn = filled($validated['nidn'] ?? null)
            ? trim($validated['nidn'])
            : null;
        $user->email = strtolower(trim($validated['email']));
        $user->save();

        return back()->with('success', 'Data akun dosen berhasil diperbarui.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $this->assertLecturer($user);

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $user->is_active = (bool) $validated['is_active'];
        $user->save();

        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with(
            'success',
            $user->is_active
                ? 'Akun dosen diaktifkan kembali.'
                : 'Akun dosen dinonaktifkan dan sesi login aktif dihentikan.'
        );
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->assertLecturer($user);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->password = $validated['password'];
        $user->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back()->with('success', 'Password dosen berhasil direset. Semua sesi login lama telah dihentikan.');
    }

    private function assertLecturer(User $user): void
    {
        abort_unless($user->role === 'dosen', 403, 'Akun administrator tidak dapat diubah dari pengelolaan dosen.');
    }
}
