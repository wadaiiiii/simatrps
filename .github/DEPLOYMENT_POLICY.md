# SiMatRPS Deployment Policy

## Tujuan
Mencegah Vercel Hobby terkena build-rate-limit akibat banyak commit/deployment untuk satu perubahan fungsi.

## Aturan wajib
- Semua perubahan dikerjakan di branch kerja: `work/*`, `fix/*`, atau `feat/*`.
- Vercel hanya boleh membuat deployment otomatis dari branch `main`.
- Branch kerja divalidasi dengan GitHub Actions, bukan Vercel.
- Setelah valid, perubahan di-**squash merge** ke `main`: satu perubahan fungsi = satu commit final = satu deployment Vercel.
- Jangan membuat rangkaian commit `prepare -> trigger -> patch -> redeploy` untuk satu perubahan.
- Jangan membuat empty/dummy commit hanya untuk memicu Vercel.
- Jangan membuat workflow/script patch satu-kali untuk perubahan yang bisa diedit langsung pada source.
- Jika Vercel terkena rate limit, jangan retry dengan commit baru. Tunggu slot pulih lalu lakukan satu redeploy dari deployment/commit final yang sama.

## CI branch kerja
PR ke `main` dan push pada `work/**`, `fix/**`, atau `feat/**` menjalankan lint PHP dan build frontend di GitHub Actions. Hanya perubahan yang sudah lolos validasi yang boleh masuk `main`.
