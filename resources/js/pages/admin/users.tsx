import { Head, router, useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Ban,
    CheckCircle2,
    KeyRound,
    Pencil,
    Search,
    ShieldCheck,
    UserPlus,
    Users,
    X,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

type UserRow = {
    id: number;
    name: string;
    academic_title?: string | null;
    nidn?: string | null;
    email: string;
    role: string;
    is_active: boolean;
    created_at?: string | null;
    rps_count: number;
};

export default function Page({ users }: { users: UserRow[] }) {
    const [query, setQuery] = useState('');
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [passwordUser, setPasswordUser] = useState<UserRow | null>(null);

    const createForm = useForm({
        name: '',
        academic_title: '',
        nidn: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const editForm = useForm({
        name: '',
        academic_title: '',
        nidn: '',
        email: '',
    });

    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    const filteredUsers = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return users;

        return users.filter((user) =>
            [user.name, user.academic_title, user.nidn, user.email, user.role]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(needle)),
        );
    }, [query, users]);

    const activeLecturers = users.filter((user) => user.role === 'dosen' && user.is_active).length;
    const inactiveLecturers = users.filter((user) => user.role === 'dosen' && !user.is_active).length;

    const submitCreate = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        createForm.post('/admin/pengguna', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const startEdit = (user: UserRow) => {
        editForm.clearErrors();
        editForm.setData({
            name: user.name,
            academic_title: user.academic_title ?? '',
            nidn: user.nidn ?? '',
            email: user.email,
        });
        setEditingUser(user);
    };

    const submitEdit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!editingUser) return;

        editForm.put(`/admin/pengguna/${editingUser.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditingUser(null),
        });
    };

    const startPasswordReset = (user: UserRow) => {
        passwordForm.reset();
        passwordForm.clearErrors();
        setPasswordUser(user);
    };

    const submitPassword = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!passwordUser) return;

        passwordForm.put(`/admin/pengguna/${passwordUser.id}/password`, {
            preserveScroll: true,
            onSuccess: () => {
                passwordForm.reset();
                setPasswordUser(null);
            },
        });
    };

    const toggleStatus = (user: UserRow) => {
        const nextActive = !user.is_active;
        const message = nextActive
            ? `Aktifkan kembali akun ${user.name}?`
            : `Nonaktifkan akun ${user.name}? Dosen akan dikeluarkan dari sesi login aktif.`;

        if (!window.confirm(message)) return;

        router.patch(
            `/admin/pengguna/${user.id}/status`,
            { is_active: nextActive },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Kelola Pengguna" />
            <div className="space-y-6 p-4 md:p-6">
                <section className="rounded-3xl border border-cyan-100 bg-gradient-to-br from-slate-950 via-cyan-950 to-teal-900 p-6 text-white shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.22em] text-cyan-200">Administrasi</div>
                            <h1 className="mt-2 text-2xl font-black tracking-tight md:text-3xl">Kelola Pengguna</h1>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-cyan-50/80">
                                Tambah akun dosen, perbarui identitas login, aktifkan atau nonaktifkan akses, dan reset password dari satu halaman.
                            </p>
                        </div>
                        <div className="grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                            <MiniStat label="Total akun" value={users.length} />
                            <MiniStat label="Dosen aktif" value={activeLecturers} />
                            <MiniStat label="Nonaktif" value={inactiveLecturers} />
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[0.78fr_1.22fr]">
                    <section className="sim-surface h-fit rounded-2xl p-5">
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-teal-50 p-3 text-teal-700">
                                <UserPlus className="size-5" />
                            </div>
                            <div>
                                <h2 className="font-bold text-slate-900">Tambah Akun Dosen</h2>
                                <p className="text-sm text-slate-500">Akun langsung aktif setelah dibuat.</p>
                            </div>
                        </div>

                        <form onSubmit={submitCreate} className="mt-5 space-y-4">
                            <Field label="Nama lengkap" error={createForm.errors.name}>
                                <TextInput
                                    value={createForm.data.name}
                                    onChange={(value) => createForm.setData('name', value)}
                                    placeholder="Nama dosen"
                                />
                            </Field>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field label="Gelar akademik" error={createForm.errors.academic_title}>
                                    <TextInput
                                        value={createForm.data.academic_title}
                                        onChange={(value) => createForm.setData('academic_title', value)}
                                        placeholder="Contoh: M.Si."
                                    />
                                </Field>
                                <Field label="NIDN" error={createForm.errors.nidn}>
                                    <TextInput
                                        value={createForm.data.nidn}
                                        onChange={(value) => createForm.setData('nidn', value)}
                                        placeholder="Opsional"
                                    />
                                </Field>
                            </div>

                            <Field label="Email login" error={createForm.errors.email}>
                                <TextInput
                                    type="email"
                                    value={createForm.data.email}
                                    onChange={(value) => createForm.setData('email', value)}
                                    placeholder="nama@unsulbar.ac.id"
                                />
                            </Field>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field label="Password awal" error={createForm.errors.password}>
                                    <TextInput
                                        type="password"
                                        value={createForm.data.password}
                                        onChange={(value) => createForm.setData('password', value)}
                                        placeholder="Minimal 8 karakter"
                                    />
                                </Field>
                                <Field label="Ulangi password" error={createForm.errors.password_confirmation}>
                                    <TextInput
                                        type="password"
                                        value={createForm.data.password_confirmation}
                                        onChange={(value) => createForm.setData('password_confirmation', value)}
                                        placeholder="Ulangi password"
                                    />
                                </Field>
                            </div>

                            <div className="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">
                                Role otomatis <b>Dosen</b> dan status otomatis <b>Aktif</b>.
                            </div>

                            <button
                                type="submit"
                                disabled={createForm.processing}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-teal-700 px-5 py-3 text-sm font-bold text-white transition hover:bg-teal-800 disabled:opacity-50"
                            >
                                <UserPlus className="size-4" />
                                {createForm.processing ? 'Membuat Akun…' : 'Buat Akun Dosen'}
                            </button>
                        </form>
                    </section>

                    <section className="sim-surface rounded-2xl p-5">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div className="flex items-center gap-3">
                                <div className="rounded-xl bg-slate-100 p-3 text-slate-700">
                                    <Users className="size-5" />
                                </div>
                                <div>
                                    <h2 className="font-bold text-slate-900">Daftar Pengguna</h2>
                                    <p className="text-sm text-slate-500">{filteredUsers.length} dari {users.length} akun ditampilkan</p>
                                </div>
                            </div>

                            <label className="relative block md:w-72">
                                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Cari nama, NIDN, email..."
                                    className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-9 pr-3 text-sm outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
                                />
                            </label>
                        </div>

                        <div className="mt-5 overflow-x-auto">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <th className="px-3 py-3">Pengguna</th>
                                        <th className="px-3 py-3">NIDN</th>
                                        <th className="px-3 py-3">Email</th>
                                        <th className="px-3 py-3">RPS</th>
                                        <th className="px-3 py-3">Status</th>
                                        <th className="px-3 py-3 text-right">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredUsers.map((user) => {
                                        const isAdmin = user.role === 'admin';
                                        return (
                                            <tr key={user.id} className="border-b border-slate-100 last:border-0 hover:bg-slate-50/70">
                                                <td className="px-3 py-4">
                                                    <div className="flex items-start gap-2.5">
                                                        <div className={`mt-0.5 rounded-lg p-1.5 ${isAdmin ? 'bg-violet-50 text-violet-700' : 'bg-teal-50 text-teal-700'}`}>
                                                            {isAdmin ? <ShieldCheck className="size-4" /> : <Users className="size-4" />}
                                                        </div>
                                                        <div>
                                                            <div className="font-semibold text-slate-900">{user.name}</div>
                                                            <div className="mt-0.5 text-xs text-slate-500">
                                                                {user.academic_title || (isAdmin ? 'Administrator' : 'Dosen')}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-4 text-slate-600">{user.nidn || '-'}</td>
                                                <td className="px-3 py-4 text-slate-600">{user.email}</td>
                                                <td className="px-3 py-4">
                                                    <span className="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700">
                                                        {user.rps_count} RPS
                                                    </span>
                                                </td>
                                                <td className="px-3 py-4">
                                                    {user.is_active ? (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                            <BadgeCheck className="size-3.5" /> Aktif
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                                            <Ban className="size-3.5" /> Nonaktif
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-4">
                                                    {isAdmin ? (
                                                        <div className="text-right text-xs font-semibold text-slate-400">Akun dilindungi</div>
                                                    ) : (
                                                        <div className="flex justify-end gap-1.5">
                                                            <ActionButton title="Edit data" onClick={() => startEdit(user)}>
                                                                <Pencil className="size-3.5" /> Edit
                                                            </ActionButton>
                                                            <ActionButton title="Reset password" onClick={() => startPasswordReset(user)}>
                                                                <KeyRound className="size-3.5" /> Password
                                                            </ActionButton>
                                                            <button
                                                                type="button"
                                                                onClick={() => toggleStatus(user)}
                                                                className={`inline-flex items-center gap-1 rounded-lg border px-2.5 py-2 text-xs font-bold transition ${
                                                                    user.is_active
                                                                        ? 'border-rose-200 bg-white text-rose-700 hover:bg-rose-50'
                                                                        : 'border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50'
                                                                }`}
                                                            >
                                                                {user.is_active ? <Ban className="size-3.5" /> : <CheckCircle2 className="size-3.5" />}
                                                                {user.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                            </button>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}

                                    {filteredUsers.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-3 py-12 text-center text-sm text-slate-500">
                                                Tidak ada pengguna yang cocok dengan pencarian.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>

            {editingUser && (
                <Modal title={`Edit ${editingUser.name}`} onClose={() => setEditingUser(null)}>
                    <form onSubmit={submitEdit} className="space-y-4">
                        <Field label="Nama lengkap" error={editForm.errors.name}>
                            <TextInput value={editForm.data.name} onChange={(value) => editForm.setData('name', value)} />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Gelar akademik" error={editForm.errors.academic_title}>
                                <TextInput value={editForm.data.academic_title} onChange={(value) => editForm.setData('academic_title', value)} />
                            </Field>
                            <Field label="NIDN" error={editForm.errors.nidn}>
                                <TextInput value={editForm.data.nidn} onChange={(value) => editForm.setData('nidn', value)} />
                            </Field>
                        </div>
                        <Field label="Email login" error={editForm.errors.email}>
                            <TextInput type="email" value={editForm.data.email} onChange={(value) => editForm.setData('email', value)} />
                        </Field>
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={() => setEditingUser(null)} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600">
                                Batal
                            </button>
                            <button type="submit" disabled={editForm.processing} className="rounded-xl bg-teal-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                                {editForm.processing ? 'Menyimpan…' : 'Simpan Perubahan'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}

            {passwordUser && (
                <Modal title={`Reset Password ${passwordUser.name}`} onClose={() => setPasswordUser(null)}>
                    <div className="mb-4 rounded-xl bg-amber-50 p-3 text-sm leading-6 text-amber-800">
                        Setelah password diubah, semua sesi login dosen ini akan dihentikan dan dosen harus login kembali menggunakan password baru.
                    </div>
                    <form onSubmit={submitPassword} className="space-y-4">
                        <Field label="Password baru" error={passwordForm.errors.password}>
                            <TextInput
                                type="password"
                                value={passwordForm.data.password}
                                onChange={(value) => passwordForm.setData('password', value)}
                                placeholder="Minimal 8 karakter"
                            />
                        </Field>
                        <Field label="Ulangi password baru" error={passwordForm.errors.password_confirmation}>
                            <TextInput
                                type="password"
                                value={passwordForm.data.password_confirmation}
                                onChange={(value) => passwordForm.setData('password_confirmation', value)}
                                placeholder="Ulangi password"
                            />
                        </Field>
                        <div className="flex justify-end gap-2 pt-2">
                            <button type="button" onClick={() => setPasswordUser(null)} className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600">
                                Batal
                            </button>
                            <button type="submit" disabled={passwordForm.processing} className="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-50">
                                <KeyRound className="size-4" />
                                {passwordForm.processing ? 'Mereset…' : 'Reset Password'}
                            </button>
                        </div>
                    </form>
                </Modal>
            )}
        </>
    );
}

function MiniStat({ label, value }: { label: string; value: number }) {
    return (
        <div className="min-w-24 rounded-xl border border-white/15 bg-white/10 px-3 py-2 backdrop-blur-sm">
            <div className="text-xl font-black">{value}</div>
            <div className="text-[11px] font-semibold text-cyan-100/80">{label}</div>
        </div>
    );
}

function ActionButton({ title, onClick, children }: { title: string; onClick: () => void; children: ReactNode }) {
    return (
        <button
            type="button"
            title={title}
            onClick={onClick}
            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs font-bold text-slate-600 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-700"
        >
            {children}
        </button>
    );
}

function TextInput({
    value,
    onChange,
    type = 'text',
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    type?: string;
    placeholder?: string;
}) {
    return (
        <input
            type={type}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            placeholder={placeholder}
            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none transition focus:border-teal-400 focus:ring-2 focus:ring-teal-100"
        />
    );
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1.5 block text-sm font-semibold text-slate-700">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-rose-600">{error}</span>}
        </label>
    );
}

function Modal({ title, onClose, children }: { title: string; onClose: () => void; children: ReactNode }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm" role="presentation">
            <div className="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl" role="dialog" aria-modal="true" aria-label={title}>
                <div className="flex items-start justify-between gap-4 border-b border-slate-100 pb-4">
                    <div>
                        <h2 className="text-lg font-black text-slate-900">{title}</h2>
                        <p className="mt-1 text-sm text-slate-500">Perubahan diterapkan langsung ke akun dosen.</p>
                    </div>
                    <button type="button" onClick={onClose} className="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <X className="size-4" />
                    </button>
                </div>
                <div className="pt-4">{children}</div>
            </div>
        </div>
    );
}

Page.layout = {
    breadcrumbs: [{ title: 'Pengguna', href: '/admin/pengguna' }],
};
