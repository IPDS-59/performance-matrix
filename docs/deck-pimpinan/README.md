# Deck Kinetik untuk Pimpinan BPS Provinsi Sulawesi Tengah

Tiga perintah (jalankan di folder ini):

```bash
pnpm install           # 1. pasang dependensi (sekali saja)
pnpm run dev            # 2. presentasi: http://localhost:3030  (mode presenter: /presenter)
pnpm run export         # 3. ekspor PDF -> kinetik-pimpinan.pdf
```

Jika ekspor PDF gagal karena browser belum ada: `npx playwright-chromium install chromium`.
Versi web statis: `pnpm run build` (hasil di `dist/`).

Catatan pembicara ada di setiap slide (tampil di mode presenter).
