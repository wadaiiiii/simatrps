import { Head, useForm } from '@inertiajs/react';
import { BadgeCheck, UserPlus, Users } from 'lucide-react';

type UserRow = {
    id: number;
    name: string;
    academic_title?: string | null;
    nidn?: string | null;
    email: string;
    role: string;
    is_active: boolean;
    created_at: string;
};

export default function Page({ users }: { users: UserRow[] }) {
    const form = useForm({
        name: '',
        academic_title: '',
        nidn: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        form.post('/admin/pengguna', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Pengguna" />
            <div className="p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Pengguna</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Buat akun dosen dan lihat akun yang sudah dapat mengakses SiMatRPS.
                    </p>
                </div>

                <div className="mt-6 grid gap-6 xl:grid-cols-[0.85fr_1.15fr]">
                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-teal-50 p-3 text-teal-700">
                                <UserPlus className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-bold">Tambah Akun Dosen</h2>
                                <p className="text-sm text-slate-500">Akun langsung aktif setelah dibuat.</p>
                            </div>
                        </div>

                        <form onSubmit={submit} className="mt-5 space-y-4">
                            <Field label="Nama lengkap" error={form.errors.name}>
                                <input
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    placeholder="Nama dosen"
                                    className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"
                                />
                            </Field>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field label="Gelar akademik" error={form.errors.academic_title}>
                                    <input
                                        value={form.data.academic_title}
                                        onChange={(e) => form.setData('academic_title', e.target.value)}
                                        placeholder="Contoh: M.Si."
                                        className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"
                                    />
                                </Field>
                                <Field label="NIDN" error={form.errors.nidn}>
                                    <input
                                        value={form.data.nidn}
                                        onChange={(e) => form.setData('nidn', e.target.value)}
                                        placeholder="Opsional"
                                        className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"
                                    />
                                </Field>
                            </div>

                            <Field label="Email login" error={form.errors.email}>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    placeholder="nama@unsulbar.ac.id"
                                    className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"
                                />
                            </Field>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field label="Password awal" error={form.errors.password}>
                                    <input
                                        type="password"
                                        value={form.data.password}
                                        onChange={(e) => form.setData('password', e.target.value)}
                                        placeholder="Minimal 8 karakter"
                                        className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"
                                    />
                                </Field>
                                <Field label="Ulangi password" error={form.errors.password_confirmation}>
                                    <input
                                        type="password"
                                        value={form.data.password_confirmation}
                                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                                        placeholder="Ulangi password"
                                        className="w-full rounded-xl border border-slate-200 bg-white/70 px-3 py-3 text-sm"
                                    />
                                </Field>
                            </div>

                            <div className="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">
                                Role otomatis <b>Dosen</b> dan status otomatis <b>Aktif</b>.
                            </div>

                            <button
                                type="submit"
                                disabled={form.processing}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white disabled:opacity-50"
                            >
                                <UserPlus className="size-4" />
                                {form.processing ? 'Membuat Akun…' : 'Buat Akun Dosen'}
                            </button>
                        </form>
                    </section>

                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <div className="rounded-xl bg-slate-100 p-3 text-slate-700">
                                    <Users className="size-5" />
                                </div>
                                <div>
                                    <h2 className="font-bold">Daftar Pengguna</h2>
                                    <p className="text-sm text-slate-500">{users.length} akun terdaftar</p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-5 overflow-x-auto">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <th className="px-3 py-3">Dosen</th>
                                        <th className="px-3 py-3">NIDN</th>
                                        <th className="px-3 py-3">Email</th>
                                        <th className="px-3 py-3">Role</th>
                                        <th className="px-3 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {users.map((user) => (
                                        <tr key={user.id} className="border-b border-slate-100 last:border-0">
                                            <td className="px-3 py-4">
                                                <div className="font-semibold text-slate-900">{user.name}</div>
                                                {user.academic_title && (
                                                    <div className="mt-0.5 text-xs text-slate-500">{user.academic_title}</div>
                                                )}
                                            </td>
                                            <td className="px-3 py-4 text-slate-600">{user.nidn || '-'}</td>
                                            <td className="px-3 py-4 text-slate-600">{user.email}</td>
                                            <td className="px-3 py-4">
                                                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700">
                                                    {user.role}
                                                </span>
                                            </td>
                                            <td className="px-3 py-4">
                                                {user.is_active ? (
                                                    <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                        <BadgeCheck className="size-3.5" /> Aktif
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-rose-600">{error}</span>}
        </label>
    );
}

Page.layout = {
    breadcrumbs: [{ title: 'Pengguna', href: '/admin/pengguna' }],
};
