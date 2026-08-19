# Project: WCM 3 - Version 2 — "Tentakel 3" untuk struktur PBN — brand: Bola Bola Bola

> **WAJIB DIBACA DI AWAL SESI, SEBELUM AKSI APAPUN.** File ini adalah
> instruksi proyek, bukan sekadar catatan — ikuti isinya. Setelah baca
> file ini, baca juga `docs/HANDOFF.md` (checklist detail) dan
> `docs/ROADMAP.md` (status per fase) sebelum mulai kerja. Kalau ada
> keputusan besar yang masih kosong di bawah (permalink, kategori final,
> akun Cloudflare, akun cPanel/Git repo asli), TANYA operator dulu lewat
> chat — jangan asumsi atau eksekusi sendiri duluan.

## Konteks & tujuan

Situs ini adalah "Tentakel 3" dalam struktur link-building operator,
level **V.2** (bukan V.1). **WCM 3 V.2 SEJAJAR dengan WCM 2 V.2**
(arenasport77.com), sama-sama cabang langsung dari WCM 1 V.2
(bravosport77.com, `wcm1_version2`), **BUKAN turunan dari WCM 2 V.2**.
Lihat `skema-tentakel.html` di `cc-wpm/skema-1/` (Command Center) untuk
posisi lengkap di struktur gurita.

Arah backlink: **WCM 3 V.2 → WCM 1 V.2 (bravosport77.com)** — BUKAN ke
WCM 2 V.2 (arenasport77.com) — supaya pola link konsisten dengan diagram
gurita Skema 1 (Sagagoal.com → bravosport77.com → dst).

**Domain: bolabolabola.com.** **Nama brand kerja: "Bola Bola Bola"** —
ini ASUMSI penamaan sesi ini (mengikuti pola brand = variasi nama domain,
sama seperti WCM 3 V.1/BolaUpdateIndonesia → "Bola Update Indonesia").
**Belum dikonfirmasi operator** — kalau ada nama brand lain yang
diinginkan, ganti `CMS_ADMIN_TAGLINE` (`cms-admin/config/app.php`) dan
`WPM_SITE_NAME`/`WPM_SITE_TAGLINE` (`includes/site-bootstrap.php`).
Niche: bola & seputar olahraga umum, konsisten dengan gurita Skema 1.

## Sumber folder ini — PENTING, baca dulu

Folder `wcm3_version2` ini **bukan folder kosong** — isinya adalah COPY
literal dari folder kerja **WCM 3 V.1** (`wcm3_version1` /
BolaUpdateIndonesia.com), termasuk riwayat git-nya, docs-nya, dan semua
progress log yang tercatat di sana. Sesi ini (19 Agu 2026) melakukan
rename brand/domain dari Bola Update Indonesia → Bola Bola Bola dan
menyesuaikan referensi struktur gurita dari level V.1 ke V.2 sesuai
**Work Order 005**. Konsekuensinya:

- Semua "progress" yang sebelumnya tercatat di `docs/HANDOFF.md` /
  `docs/ROADMAP.md` versi lama (git sudah di-push ke GitHub, cPanel
  sudah dikonfigurasi, artikel seed sudah masuk, dst.) itu progress
  **punya WCM 3 V.1**, BUKAN progress WCM 3 V.2. Dokumen sudah ditulis
  ulang sesi ini supaya tidak mengklaim progress palsu — lihat
  `docs/HANDOFF.md`/`docs/ROADMAP.md` versi baru untuk status
  sebenarnya (mayoritas "belum mulai").
- **`git remote origin` folder ini masih menunjuk ke repo GitHub asli
  WCM 3 V.1** (`jalijali-dev/wcm-bolaupdateindonesia.com.git`). Sesi ini
  MENCOBA menghapusnya (`git remote remove origin`) tapi gagal karena
  ada file lock (`.git/packed-refs.lock`, `.git/refs/remotes/origin/
  main.lock`) yang tidak bisa dihapus lewat tool ini (permission
  ditolak). **JANGAN `git push` dari folder ini sebelum remote ini
  dibersihkan/diganti** — kalau ke-push, isinya akan masuk ke repo WCM 3
  V.1, bukan repo WCM 3 V.2. Operator perlu hapus manual lock file itu
  (lewat terminal biasa di komputer, bukan lewat Cowork) lalu jalankan
  `git remote remove origin` lagi, atau `git remote set-url origin
  <url-repo-wcm3-v2-yang-benar>` begitu repo GitHub WCM 3 V.2 sudah
  dibuat.
- **`.cpanel.yml` juga masih warisan WCM 3 V.1** — sudah ditulis ulang
  jadi placeholder (`DEPLOYPATH` di-blank ke `GANTI_DENGAN_AKUN_CPANEL_
  BOLABOLABOLA`) supaya tidak accidental deploy ke akun cPanel situs
  lain. JANGAN diisi sampai operator kasih akun cPanel & path docroot
  yang benar untuk bolabolabola.com.

## Sumber clone (per Work Order 005)

`cms-admin/` (dan pola frontend publik) di-clone dari **WCM 2 V.1
(biangolahraga.com)** — sesuai arahan Work Order 005. Karena source-nya
bukan situs kosong (dan folder ini sendiri adalah copy dari WCM 3 V.1
yang juga sudah pernah di-clone+diedit dari WCM 2 V.1), ada beberapa hal
warisan yang HARUS diaudit ulang, bukan cuma nama brand:

1. **Kategori & nav** — `includes/site-bootstrap.php` dan
   `includes/site-header.php` masih pakai kategori final WCM 3 V.1 (Liga
   Indonesia/Liga Eropa/Timnas/Transfer). **Belum dikonfirmasi apakah WCM
   3 V.2 pakai kategori yang sama atau beda** — tanya operator.
2. **Frontend/mockup — SUDAH DIKERJAKAN & FINAL sesi ini**
   (`docs/homepage-mockup-v2.html`): header solid MERAH + logo asli dari
   operator (`assets/img/logo-red.png`, lambang kepala elang dalam
   lingkaran, di-recolor merah), judul brand "BOLA BOLA BOLA" penuh,
   widget list card ala jadwal pertandingan, sidebar kategori/populer,
   grid Instagram, footer gelap wordmark raksasa. Font: Anton (display/
   wordmark), Rajdhani (nav/label/badge), Inter (body) — beda total dari
   WCM 3 V.1 (Fraunces/Space Grotesk/Manrope, tema light). **Mockup ini
   HTML statis mandiri, BELUM diimplementasikan ke kode PHP asli** — itu
   kerjaan tim dev lewat Claude Code (lihat bagian "Brief buat Dev" di
   bawah untuk detail lengkap).
3. **Kategori final SUDAH DIKONFIRMASI operator (19 Agu 2026):
   Sepak Bola, Basket, Voli, Bursa Transfer** (lintas cabang — sepak
   bola/basket/voli sekaligus). Ini genuinely beda dari WCM 3 V.1 yang
   liga-sentris (Liga Indonesia/Liga Eropa/Timnas/Transfer) — sekaligus
   mainin nama domain secara harfiah ("bola bola bola" = beberapa cabang
   olahraga berbeda yang sama-sama pakai bola). `wpm_site_nav_categories()`
   dan `wpm_category_color_class()` di `includes/site-bootstrap.php`
   SUDAH diupdate ke slug baru (`sepak-bola`, `basket`, `voli`,
   `bursa-transfer`). **TAPI `index.php` dan `includes/site-header.php`
   MASIH hardcode slug kategori LAMA** (`liga-indonesia`, `timnas`,
   `transfer`) di beberapa tempat (band homepage, promo strip, link
   "Lihat Lebih Banyak") — ini akan mismatch/pecah kalau dijalankan apa
   adanya. Perlu ditulis ulang oleh tim dev sekalian dengan implementasi
   mockup v2, BUKAN sekadar cari-ganti slug.
3. Modul cms-admin (livescore/football/basketball/F1, `sport_key`) sudah
   bersih dari sononya, otomatis ikut bersih di sini juga.

## Risiko penting yang harus diingat (jangan skip)

1. **Database HARUS terpisah** dari SEMUA database gurita Skema 1 yang
   sudah ada (`wpm_cms_goal`, `wpm_cms_olahraga77`,
   `wpm_cms_wcm1_version2`, `wpm_cms_wcm2_version1`, DB WCM 2 V.2/
   arenasport77, DAN DB WCM 3 V.1/`wcm3_version1`). Sesi ini mengganti
   `cms-admin/config/database.php` → `DB_NAME=wcm3_version2` (beda dari
   `wcm3_version1`). **Belum dikonfirmasi apakah database
   `wcm3_version2` sudah dibuat kosong di MySQL dev lokal** — cek ke
   operator / phpMyAdmin dulu. **Jangan pakai fitur "Copy database" di
   phpMyAdmin** (insiden ini pernah kejadian di WCM 2 — database ter-copy
   penuh dari sumbernya, bukan kosong).
2. **`CMS_AI_ENC_SECRET` sudah digenerate ulang** sesi ini (di
   `cms-admin/config/app.php`) — genuinely baru, bukan reuse dari WCM 3
   V.1 atau situs manapun. `GROWTH_AGENT_DIGEST_TOKEN` masih placeholder
   warisan, belum diisi token asli.
3. **Hindari duplicate content & PBN footprint** — konten harus
   digenerate/ditulis terpisah (bukan copy-paste dari situs manapun
   termasuk WCM 3 V.1), tema visual harus beda dari SEMUA tentakel lain
   di gurita — terutama WCM 2 V.2 (arenasport77, sesama anak WCM 1 V.2)
   dan WCM 1 V.2 sendiri — dan struktur permalink juga harus beda (detail
   masih menyusul dari operator).
4. **Isolasi infrastruktur** (IP hosting, akun Cloudflare, akun cPanel,
   Git repo, GSC) — SEMUANYA masih perlu disiapkan baru untuk WCM 3 V.2,
   JANGAN reuse punya WCM 3 V.1 (lihat bagian "Sumber folder ini" di
   atas soal git remote & `.cpanel.yml` warisan yang harus dibersihkan
   dulu).

## Yang SUDAH dikerjakan

**19 Agu 2026:**
- Command Center (JCC) menerbitkan Work Order 005: bootstrap WCM 3 V.2
  (bolabolabola.com).
- Operator connect folder `wcm3_version2` ke Cowork. Ditemukan folder ini
  isinya copy literal dari `wcm3_version1` (WCM 3 V.1) — bukan folder
  baru/kosong.
- Dikonfirmasi ke operator: sesuaikan semua referensi struktur gurita
  (backlink, parent/sibling tentakel) ke level V.2 sesuai Work Order 005,
  bukan sekadar ganti nama domain.
- **Rename brand/domain** dari Bola Update Indonesia/bolaupdateindonesia.com
  → Bola Bola Bola/bolabolabola.com di: `cms-admin/login.php`,
  `cms-admin/config/app.php` (`CMS_ADMIN_TAGLINE`, `CMS_AI_ENC_SECRET`
  digenerate ulang), `cms-admin/config/app.php.example`,
  `includes/site-header.php` (brand mark, jadi placeholder teks),
  `includes/site-bootstrap.php` (`WPM_SITE_NAME`, komentar header),
  `docs/roadmap-progress.html`, `docs/homepage-mockup-v1.html`,
  `assets/css/site.css` (komentar header).
- `cms-admin/config/database.php` — `DB_NAME` diganti `wcm3_version1` →
  `wcm3_version2` (isolasi dari WCM 3 V.1).
- `.cpanel.yml` ditulis ulang jadi placeholder (DEPLOYPATH warisan WCM 3
  V.1 yang berbahaya kalau ke-deploy dihapus).
- `docs/HANDOFF.md` dan `docs/ROADMAP.md` ditulis ulang total mencerminkan
  status WCM 3 V.2 yang sebenarnya (bukan copy progress WCM 3 V.1).

## Yang BELUM dikerjakan — task list buat lanjut

1. **Bersihkan git remote & lock file warisan** — `.git/packed-refs.lock`
   dan `.git/refs/remotes/origin/main.lock` perlu dihapus manual di luar
   Cowork (tool ini gagal karena permission), lalu `git remote remove
   origin` (atau ganti ke repo GitHub WCM 3 V.2 yang benar begitu sudah
   dibuat). **JANGAN git push sebelum ini dibereskan.**
2. **Konfirmasi ke operator:** database `wcm3_version2` sudah dibuat
   kosong di MySQL dev lokal atau belum?
3. **Konfirmasi nama brand final** — sesi ini pakai "Bola Bola Bola"
   sebagai asumsi kerja, belum final dari operator.
4. **Kategori & nav final WCM 3 V.2** — pakai yang sama dengan WCM 3 V.1
   (Liga Indonesia/Liga Eropa/Timnas/Transfer) atau beda? Perlu keputusan
   operator.
5. **Mockup frontend baru** — per arahan operator, ini dikerjakan
   terpisah lewat tim dev (Claude Code), bukan di sesi Cowork ini. Brand
   mark saat ini masih placeholder teks sementara.
6. Detail struktur permalink final (beda dari `/artikel/{slug}` punya
   tentakel lain).
7. Jalanin schema migration di database `wcm3_version2` begitu database
   dikonfirmasi kosong.
8. Audit ulang modul cms-admin warisan clone.
9. Logo — masih placeholder/warisan, belum disesuaikan ke identitas Bola
   Bola Bola.
10. Isolasi infrastruktur: IP hosting baru, akun Cloudflare (perlu
    didiskusikan operator — sama dengan WCM 2 V.2 atau baru), akun
    cPanel baru, repo Git baru, GSC property baru.
11. Setelah online, lapor ke Command Center biar status di
    `skema-tentakel.html` diupdate.

## Brief buat Dev (Claude Code) — implementasi mockup ke PHP asli

Mockup frontend WCM 3 V.2 sudah final & disetujui operator (19 Agu 2026).
Tugas dev: implementasikan `docs/homepage-mockup-v2.html` ke kode PHP
sungguhan (`index.php`, `includes/site-header.php`,
`includes/site-footer.php`, `assets/css/site.css`, `kategori.php`,
`artikel.php`), data nyambung ke database real via fungsi di
`includes/site-bootstrap.php` — TIDAK ada konten hardcode, sama seperti
pola WCM 3 V.1.

**Aset yang sudah siap:**
- `docs/homepage-mockup-v2.html` — mockup homepage final, HTML+CSS+SVG
  mandiri. Palet merah + gold (`--red #d81922`, `--red-dark #a5120f`,
  `--gold #e0a53c`), font Anton (display/wordmark) + Rajdhani (nav/
  label/badge) + Inter (body) via Google Fonts.
- `assets/img/logo-red.png` — logo asli dari operator (lambang kepala
  elang dalam lingkaran), sudah di-recolor merah. Dipakai di header
  (kecil, ~56px) dan footer (besar, ~76px) mockup. **Belum ada versi
  vector/SVG** — kalau butuh ukuran sangat besar tanpa pecah atau varian
  warna lain, minta file master ke operator.
- `includes/site-bootstrap.php` — `wpm_site_nav_categories()` dan
  `wpm_category_color_class()` SUDAH diupdate ke kategori final:
  `sepak-bola` (Sepak Bola), `basket` (Basket), `voli` (Voli),
  `bursa-transfer` (Bursa Transfer, lintas cabang). Bug fallback ke
  kategori `'tips'` (sudah gak ada) juga sudah diperbaiki.

**Yang HARUS diperbaiki pas implementasi (jangan cuma cari-ganti teks):**
- `index.php` dan `includes/site-header.php` MASIH hardcode slug
  kategori LAMA warisan WCM 3 V.1 (`liga-indonesia`, `timnas`,
  `transfer`) di beberapa tempat: `$bandSlugs` array, `wpm_category_url()`
  calls di promo strip, dan link "Lihat Lebih Banyak". Ini WAJIB ditulis
  ulang mengikuti struktur baru (4 kategori: sepak-bola/basket/voli/
  bursa-transfer) sekalian dengan implementasi mockup v2 — kalau dijalanin
  apa adanya bakal mismatch/pecah (kategori gak ketemu → list kosong).
- Struktur mockup beda dari struktur lama: sekarang pakai
  header-utility-bar + header-solid-merah + sub-bar breadcrumb/CTA +
  hero-banner + hero-strip kategori + main-layout 2 kolom (news-list ala
  card + sidebar) + grid Instagram + footer wordmark besar — BUKAN
  struktur band-warna-per-kategori yang dipakai `index.php` sekarang.
  Perlu refactor struktur HTML/CSS, bukan cuma ganti warna.
- Kategori nav di `includes/site-header.php` sudah otomatis ambil dari
  `wpm_site_nav_categories()` (loop dinamis) — TIDAK perlu hardcode
  ulang di situ, tapi markup-nya (topbar/nav/search) perlu disesuaikan ke
  struktur mockup baru.

**Belum diputuskan/dikerjakan (di luar scope implementasi visual):**
- Struktur permalink URL final.
- Database `wcm3_version2` — belum dikonfirmasi kosong di MySQL dev
  lokal, schema migration belum jalan, belum ada artikel sama sekali.
- Git remote & lock file warisan WCM 3 V.1 (lihat bagian "Sumber folder
  ini" di atas) — HARUS dibereskan manual sebelum push apapun.
- Favicon publik & logo vector master masih perlu diminta ke operator.

## Cara lanjut sesi ini

Baca file ini dulu di awal sesi, lalu `docs/HANDOFF.md` buat detail
checklist, dan `docs/ROADMAP.md` buat status per fase. Kalau ada
keputusan besar yang belum jelas (kategori final, permalink, akun
Cloudflare/cPanel, nama brand final), TANYA operator dulu — jangan asumsi
sendiri.
