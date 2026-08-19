# HANDOFF — WCM 3 - Version 2

Dokumen ini merekam proses bootstrap WCM 3 V.2 (bolabolabola.com) sesuai
**Work Order 005** dari Command Center (JCC, 18 Agu 2026).

**Catatan penting soal folder ini:** folder `wcm3_version2` ini adalah
COPY literal dari folder kerja WCM 3 V.1 (`wcm3_version1` /
BolaUpdateIndonesia.com) — bukan folder baru/kosong. Sesi 19 Agu 2026
mulai proses rename branding & penyesuaian struktur gurita ke level V.2.
Versi dokumen ini SEBELUMNYA (sebelum ditulis ulang) masih mencatat
progress milik WCM 3 V.1 (git sudah push ke GitHub, cPanel sudah
dikonfigurasi, 8 artikel seed sudah masuk, dll) — **progress itu BUKAN
progress WCM 3 V.2**, itu progress folder sumbernya. Dokumen ini ditulis
ulang total supaya checklist di bawah mencerminkan status WCM 3 V.2 yang
sebenarnya.

## Konteks proyek

- **Nama kerja:** WCM 3 - Version 2
- **Nama brand (asumsi kerja, belum final):** "Bola Bola Bola"
- **Domain:** bolabolabola.com
- **Peran dalam struktur PBN:** "Tentakel 3", level V.2 — SEJAJAR dengan
  WCM 2 V.2 (arenasport77.com), sama-sama cabang langsung dari WCM 1 V.2
  (bravosport77.com). **BUKAN turunan dari WCM 2 V.2**, walau
  `cms-admin/` di-clone dari WCM 2 V.1 (biangolahraga.com). Backlink WCM
  3 V.2 mengarah ke **WCM 1 V.2 (bravosport77.com)**, bukan ke WCM 2 V.2.
- **Topik/konten:** bola & seputar olahraga umum.
- **Kategori nav:** belum dikonfirmasi operator untuk WCM 3 V.2 —
  sementara masih pakai warisan kategori WCM 3 V.1 (Liga Indonesia/Liga
  Eropa/Timnas/Transfer) di kode, perlu dikonfirmasi ulang.
- **Tema visual / mockup frontend:** **belum dikerjakan untuk WCM 3
  V.2.** Per arahan operator, mockup baru akan dikerjakan terpisah lewat
  tim dev (Claude Code), bukan di sesi Cowork ini. Brand mark di
  `includes/site-header.php` untuk sementara diganti jadi wordmark teks
  placeholder ("BOLA"/"BOLA") murni supaya tidak ada sisa teks brand WCM
  3 V.1 yang salah — BUKAN desain final.

## Checklist HANDOFF-CMS-ADMIN — status eksekusi

### 1. Isolasi Database
- [x] `cms-admin/config/database.php` — `DB_NAME` diganti dari
      `wcm3_version1` (warisan) ke `wcm3_version2` — genuinely beda dari
      SEMUA database gurita Skema 1 yang sudah ada, termasuk beda dari
      WCM 3 V.1.
- [ ] **BELUM DIKONFIRMASI apakah database `wcm3_version2` sudah dibuat
      (dan kosong) di MySQL dev lokal.** Cek ke operator/phpMyAdmin dulu.
      **Jangan pakai fitur "Copy database" phpMyAdmin** (insiden WCM 2 —
      lihat CLAUDE.md).
- [ ] Belum ada tabel/data — perlu jalanin schema migration begitu
      database dikonfirmasi kosong.
- [ ] Kredensial production terpisah masih perlu disiapkan begitu hosting
      WCM 3 V.2 ada.

### 2. Isolasi Kredensial & Secret
- [x] `CMS_AI_ENC_SECRET` di `app.php` **di-generate ulang** sesi ini —
      genuinely baru, bukan reuse dari WCM 3 V.1 atau situs manapun.
- [ ] `GROWTH_AGENT_DIGEST_TOKEN` masih placeholder
      `GANTI_DENGAN_TOKEN_ACAK_ASLI` (ikut ter-clone) — wajib diisi token
      asli baru sebelum modul digest dipakai.
- [x] `config/app.php.example` sudah disesuaikan (contoh tagline diganti
      ke "Bola Bola Bola").
- [ ] `.gitignore` warisan clone belum diverifikasi ulang khusus untuk
      folder ini.

### 3. Audit Modul yang Gak Relevan
- [x] Modul livescore/football/basketball/F1 dan konsep `sport_key`
      sudah bersih sejak sumber.
- [ ] Belum diaudit ulang khusus untuk konteks WCM 3 V.2 — nunggu
      keputusan kategori final operator.

### 4. Branding & Konten Netral
- [x] **Rename brand (sesi ini)** — `CMS_ADMIN_NAME` tetap `'WCM'`,
      `CMS_ADMIN_TAGLINE` diganti dari `'Bola Update Indonesia'` (warisan
      WCM 3 V.1) ke `'Bola Bola Bola'` (asumsi kerja). Tagline di
      `login.php` (komentar identitas admin login) juga diganti.
- [ ] **Nama brand final belum dikonfirmasi operator.**
- [ ] Logo admin panel (`img/logo.png`, `img/logo-white.png`) masih
      warisan clone, belum diganti ke identitas Bola Bola Bola.
- [ ] System prompt AI di `growth-agent-service.php` belum dicek ulang
      sesi ini.
- [ ] **Konten (artikel, kategori) belum ada sama sekali** untuk WCM 3
      V.2. Kalau database yang di-clone/copy ternyata punya data warisan,
      WAJIB di-TRUNCATE, bukan dipakai langsung. Artikel wajib ditulis
      dari nol, TIDAK boleh copy-paste dari situs manapun (termasuk WCM 3
      V.1).

### 5. Frontend Publik
- [x] Kerangka frontend publik ikut ter-clone/copy dari WCM 3 V.1:
      `index.php`, `kategori.php`, `artikel.php`, `cari.php`,
      `ad-click.php`, `includes/site-bootstrap.php`,
      `includes/site-header.php`/`includes/site-footer.php`,
      `includes/TimeHelpers.php`, `assets/css/site.css`, `.htaccess`.
- [x] Rename brand teks di frontend (sesi ini) — `WPM_SITE_NAME` di
      `site-bootstrap.php`, brand mark placeholder di `site-header.php`,
      komentar header di `assets/css/site.css`.
- [x] **Kategori final dikonfirmasi & di-implementasikan** (19 Agu 2026):
      Sepak Bola, Basket, Voli, Bursa Transfer
      (`wpm_site_nav_categories()` di `includes/site-bootstrap.php`, slug
      `sepak-bola`/`basket`/`voli`/`bursa-transfer`).
- [x] **Mockup v2 diimplementasikan ke PHP asli** (19 Agu 2026, tim dev
      lewat Claude Code) — `docs/homepage-mockup-v2.html` (header solid
      merah + logo `assets/img/logo-red.png`, Anton/Rajdhani/Inter,
      hero banner + strip kategori, news-list card, sidebar
      kategori/populer, grid Instagram, footer wordmark) sudah ditulis
      ulang total ke `index.php`, `includes/site-header.php`,
      `includes/site-footer.php`, `assets/css/site.css`, `kategori.php`,
      `artikel.php`, `cari.php`. Slug kategori lama warisan WCM 3 V.1
      (`liga-indonesia`/`timnas`/`transfer`) yang tadinya hardcode di
      `index.php`/`site-header.php` (`$bandSlugs`, promo strip, link
      "Lihat Lebih Banyak") sudah dihapus total — semua nav/link kategori
      sekarang generate dari `wpm_site_nav_categories()`. Semua data
      (artikel terbaru, populer, per-kategori) tetap nyambung ke database
      real lewat fungsi di `includes/site-bootstrap.php` — TIDAK ada
      konten hardcode.
- [x] **Verifikasi manual di browser** (19 Agu 2026, via Docker container
      `php8_apache` + `http://localhost:8008/wcm3_version2/`): homepage
      (render dengan artikel real dari DB), kategori kosong (`basket` —
      empty state benar), kategori terisi (`sepak-bola`), halaman
      artikel, dan halaman cari — semua render sesuai mockup v2.
      Ditemukan & diperbaiki bug lama di `cari.php`: placeholder PDO
      `:q` dipakai 2x di satu query (`title LIKE :q OR excerpt LIKE :q`)
      menyebabkan `PDOException: Invalid parameter number` — bug ini
      SUDAH ADA sejak sebelum sesi ini (warisan WCM 3 V.1), diperbaiki
      jadi `:q1`/`:q2` sekalian karena filenya sedang disentuh.
- [ ] Struktur permalink URL masih pola warisan (`/artikel/{slug}`,
      `/kategori/{slug}`) — belum final untuk WCM 3 V.2, keputusan
      operator masih menyusul.
- [ ] Logo vector master (SVG/AI) belum diminta ke operator — saat ini
      cuma ada `assets/img/logo-red.png` (raster). Favicon publik
      (`assets/img/favicon.svg`/`.ico`) masih warisan lama, belum
      disesuaikan ke identitas Bola Bola Bola.
- [ ] Belum ada artikel BARU yang genuinely ditulis untuk WCM 3 V.2 —
      artikel yang tampil di homepage/kategori saat verifikasi browser
      adalah data yang SUDAH ADA di database `wcm3_version2` (sebagian
      meta_title-nya masih menyebut "Bola Update Indonesia", warisan
      konten lama) — perlu diaudit ulang: apakah harus di-TRUNCATE dan
      ditulis ulang dari nol per aturan no-copy-paste-content di
      CLAUDE.md, atau memang sudah artikel genuinely baru punya WCM 3
      V.2 yang tinggal diedit meta title-nya. TANYA operator.
- [ ] `cms-admin/` (admin panel) TIDAK disentuh sesi implementasi mockup
      ini — sesuai scope yang diminta (frontend publik saja).
- [x] **Revisi tampilan dari operator (19 Agu 2026, setelah review hasil
      implementasi)** — `docs/homepage-mockup-v2.html` diupdate operator
      jadi sumber kebenaran visual terbaru, disinkronkan ke kode PHP:
      1. Utility bar paling atas (baris "Update berita bola & olahraga
         terkini setiap hari" + link Redaksi/Kontak/Kirim Berita) DIHAPUS
         total dari `includes/site-header.php` beserta CSS `.util` di
         `assets/css/site.css`.
      2. Section grid Instagram (heading + 6 kotak placeholder) DIHAPUS
         total dari `includes/site-footer.php` beserta CSS
         `.social-section`/`.social-head`/`.social-grid`/`.social-cell`
         di `site.css`. Variabel `$showSocialGrid` (dulu di-set di
         `index.php` untuk mengaktifkan section ini) juga dihapus karena
         sudah tidak dipakai.
      3. Logo mark di header (`.brand__mark`, kecil) dan footer
         (`.logo-badge`, besar) diganti dari kotak `object-fit:contain`
         jadi BULAT PENUH (`border-radius:50%`) dengan border putih 3px +
         shadow tipis, `object-fit:cover` di dalam circle mask — kesan
         "bola" sesuai arahan operator.
      Diverifikasi ulang via DOM inspection di browser (homepage,
      kategori, artikel) setelah perubahan — tidak ada elemen kepotong/
      rusak akibat penghapusan section; `.util` dan `.social-section`
      sudah tidak ada di DOM manapun, logo mark border-radius 50% di
      kedua tempat.

### 6. Deploy Workflow
- [ ] **`.git` di folder ini masih riwayat warisan WCM 3 V.1** — remote
      `origin` menunjuk ke repo GitHub ASLI WCM 3 V.1
      (`jalijali-dev/wcm-bolaupdateindonesia.com.git`). Sesi ini mencoba
      `git remote remove origin` tapi GAGAL karena lock file
      (`.git/packed-refs.lock`, `.git/refs/remotes/origin/main.lock`)
      yang tidak bisa dihapus lewat tool Cowork (permission ditolak).
      **Operator perlu hapus lock file itu manual di terminal biasa**,
      lalu jalankan `git remote remove origin` atau arahkan ke repo GitHub
      WCM 3 V.2 yang baru. **JANGAN git push dari folder ini sebelum ini
      dibereskan** — riwayat commit juga masih riwayat WCM 3 V.1, idealnya
      di-reset bersih (pola sama seperti yang pernah dilakukan di WCM 3
      V.1 sendiri saat membersihkan riwayat WCM 2 warisan — pindahkan
      `.git` lama ke folder backup di luar proyek, lalu `git init` ulang).
- [x] `.cpanel.yml` ditulis ulang jadi placeholder (`DEPLOYPATH` warisan
      akun cPanel WCM 3 V.1 dihapus, diganti
      `GANTI_DENGAN_AKUN_CPANEL_BOLABOLABOLA`) — supaya tidak ada risiko
      accidental deploy ke docroot situs lain.
- [ ] Repo GitHub baru untuk WCM 3 V.2 belum dibuat.
- [ ] Akun cPanel & path docroot untuk bolabolabola.com belum
      dikonfirmasi operator.

### 7. SEO Dasar
- [ ] Belum dikerjakan — robots.txt, sitemap, favicon.

### 8. Isolasi Infrastruktur (per Work Order 005)
- [ ] **IP hosting** — belum ditentukan, usahakan beda dari tentakel
      lain.
- [ ] **Akun Cloudflare** — perlu didiskusikan operator: masuk akun sama
      dengan WCM 2 V.2 (sama level "Tentakel 2"/anak langsung WCM 1 V.2),
      atau akun baru. **Belum final.**
- [ ] **GSC** — property baru terpisah, belum dibuat.

### 9. Verifikasi Akhir
- [ ] Belum relevan — nunggu poin 1–8.

## Yang perlu dikonfirmasi/dikerjakan operator sebelum lanjut

1. Database `wcm3_version2` sudah dibuat kosong di MySQL dev lokal atau
   belum?
2. Nama brand final untuk bolabolabola.com — "Bola Bola Bola" (asumsi
   sesi ini) atau nama lain?
3. Kategori final WCM 3 V.2 — sama dengan WCM 3 V.1 (Liga
   Indonesia/Liga Eropa/Timnas/Transfer) atau beda?
4. Detail struktur permalink final.
5. Akun Cloudflare — sama dengan WCM 2 V.2, atau baru?
6. Akun cPanel & repo GitHub baru untuk WCM 3 V.2 (jangan pakai punya
   WCM 3 V.1 — lihat catatan git remote di atas).
7. Domain bolabolabola.com — sudah dibeli?
8. Mockup frontend baru untuk WCM 3 V.2 — siapa/kapan dikerjakan (per
   arahan sesi ini: tim dev lewat Claude Code, terpisah dari Cowork ini)?
