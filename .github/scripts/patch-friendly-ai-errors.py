from pathlib import Path

show = Path('resources/js/pages/rps/show.tsx')
text = show.read_text()

old = '''function firstError(errors: Record<string, any>) {
    const first = Object.values(errors ?? {}).flat()[0];
    const message = first ? String(first) : 'Aksi gagal diproses. Periksa kembali isian.';

    if (/Semua provider AI aktif gagal|Semua provider AI gagal/i.test(message)) {
        return message;
    }

    if (/tokens per day|TPD|rate limit reached|daily quota/i.test(message)) {
        return 'Provider utama sedang mencapai kuota. SiMatRPS akan mencoba provider backup aktif secara otomatis; jika pesan ini tetap muncul berarti provider yang tersedia juga belum berhasil.';
    }

    if (/tokens per minute|TPM/i.test(message)) {
        return 'Batas token per menit provider AI sedang tercapai. SiMatRPS akan mencoba provider backup atau melewati provider yang sedang cooldown.';
    }

    return message;
}
'''

new = '''function friendlyAiError(message: string): string | null {
    const normalized = String(message ?? '').trim();
    if (!normalized) return null;

    // Detail teknis provider hanya untuk log/admin. Pengguna cukup menerima
    // arahan operasional tanpa nama provider, kuota, billing, cooldown, atau timeout.
    if (/belum ada provider AI|provider AI.*(?:tidak dikonfigurasi|tidak tersedia)|invalid api key|unauthorized|HTTP 401|payment|billing|HTTP 402|access denied|denied access|forbidden/i.test(normalized)) {
        return 'Fitur AI sedang tidak tersedia. Silakan lanjutkan pengisian secara manual atau hubungi admin.';
    }

    if (/provider AI|GROQ|SAMBANOVA|MISTRAL|OPENROUTER|HUGGINGFACE|COHERE|tokens per day|tokens per minute|TPD|TPM|daily quota|rate limit|cooldown|timeout|timed out|HTTP 429|output JSON|invalid json|syntax error|request dihentikan dengan aman|kode diagnostik/i.test(normalized)) {
        return 'AI belum berhasil memproses permintaan. Silakan coba lagi beberapa saat. Jika masih belum berhasil, lanjutkan pengisian secara manual atau hubungi admin.';
    }

    return null;
}

function firstError(errors: Record<string, any>) {
    const first = Object.values(errors ?? {}).flat()[0];
    const message = first ? String(first) : 'Aksi gagal diproses. Periksa kembali isian.';
    return friendlyAiError(message) ?? message;
}
'''

if old not in text:
    if 'function friendlyAiError(message: string)' in text:
        raise SystemExit('friendly AI error patch already applied')
    raise SystemExit('firstError block marker not found')

show.write_text(text.replace(old, new, 1))
