# SiMatRPS Deployment Policy

- Perubahan fungsi aplikasi dikirim sebagai satu commit source yang siap deploy.
- Commit administrasi, workflow, script patch, dokumentasi, atau trigger non-aplikasi wajib menggunakan penanda `[skip-vercel]` pada commit message.
- Dilarang membuat rangkaian commit `prepare -> trigger -> patch -> redeploy` untuk satu perubahan fungsi.
- Jika Vercel gagal karena rate limit, jangan membuat commit trigger berulang. Tunggu slot pulih lalu lakukan satu redeploy saja.
