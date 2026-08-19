# Progress Roadmap — WCM 3 - Version 2

Status per 19 Agustus 2026.

Legenda: 🟢 Selesai · 🟠 Sebagian · ⚪ Belum mulai

> **Konteks:** WCM 3 V.2 (bolabolabola.com) adalah "Tentakel 3" level
> V.2, SEJAJAR dengan WCM 2 V.2 (arenasport77.com) — sama-sama cabang
> langsung dari WCM 1 V.2 (bravosport77.com), BUKAN turunan WCM 2 V.2.
> `cms-admin/` di-clone dari WCM 2 V.1 (biangolahraga.com) per Work Order
> 005, tapi backlink WCM 3 V.2 mengarah ke WCM 1 V.2 (bravosport77.com).
>
> **Catatan penting:** folder kerja proyek ini (`wcm3_version2`) adalah
> COPY literal dari folder WCM 3 V.1 (`wcm3_version1`/
> BolaUpdateIndonesia.com) — termasuk riwayat git, docs, dan semua
> progress yang tercatat sebelumnya (git sudah push ke GitHub, cPanel
> sudah dikonfigurasi, artikel seed sudah masuk, dst.). **Progress itu
> milik WCM 3 V.1**, bukan WCM 3 V.2. Roadmap ini ditulis ulang total
> sesi 19 Agu 2026 supaya mencerminkan status WCM 3 V.2 yang sebenarnya
> — hampir semua fase kembali ke "belum mulai", kecuali rename
> brand/domain dasar yang sudah dilakukan sesi ini.

## Fase 0 — Pondasi ⚪ Belum mulai

Domain bolabolabola.com — status pembelian belum dikonfirmasi. Git repo
GitHub baru untuk WCM 3 V.2 belum dibuat (repo warisan di folder ini
masih milik WCM 3 V.1 dan berbahaya kalau ke-push — lihat
`docs/HANDOFF.md`). Akun cPanel & hosting belum disiapkan. Status
database `wcm3_version2` di MySQL dev lokal belum dikonfirmasi operator.

## Fase 1 — Backend: Schema & Adaptasi CMS 🟠 Sebagian

Rename branding dasar sudah jalan sesi ini: `DB_NAME` → `wcm3_version2`
(beda dari `wcm3_version1`), `CMS_AI_ENC_SECRET` digenerate ulang,
`CMS_ADMIN_TAGLINE` → "Bola Bola Bola" (asumsi, belum final). Belum:
konfirmasi database benar-benar kosong di MySQL, schema migration, audit
modul cms-admin, `GROWTH_AGENT_DIGEST_TOKEN` masih placeholder.

## Fase 2 — Backend: Isi Konten Struktural ⚪ Belum mulai

Kategori nav final untuk WCM 3 V.2 belum dikonfirmasi operator (kode
masih pakai warisan kategori WCM 3 V.1: Liga Indonesia/Liga
Eropa/Timnas/Transfer). Belum ada satu kategori atau artikel pun untuk
WCM 3 V.2 di database.

## Fase 3 — Frontend: Mockup & Implementasi 🟢 Selesai (mockup → PHP)

Mockup `docs/homepage-mockup-v2.html` (final, disetujui operator) sudah
diimplementasikan ke kode PHP asli oleh tim dev (Claude Code, 19 Agu
2026): `index.php`, `includes/site-header.php`,
`includes/site-footer.php`, `assets/css/site.css`, `kategori.php`,
`artikel.php`, `cari.php`. Struktur baru: utility bar + header solid
merah (logo `assets/img/logo-red.png`) + nav dinamis dari
`wpm_site_nav_categories()`, sub-bar breadcrumb/CTA, hero banner + strip
kategori (homepage), layout 2 kolom (news-list card + sidebar
kategori/populer), grid Instagram (homepage), footer gelap wordmark
besar. Font Anton/Rajdhani/Inter via Google Fonts, palet merah+gold.
Slug kategori lama (`liga-indonesia`/`timnas`/`transfer`) yang tadinya
hardcode di `index.php`/`site-header.php` sudah dihapus total — semua
link kategori sekarang generate dari `wpm_site_nav_categories()` (4
kategori final: sepak-bola/basket/voli/bursa-transfer). Semua data
(artikel, populer, kategori) nyambung ke database real lewat
`includes/site-bootstrap.php`, tidak ada konten hardcode. Diverifikasi
manual di browser (homepage, kategori kosong & terisi, artikel, cari) —
lihat catatan verifikasi di `docs/HANDOFF.md`. Sekalian ditemukan &
diperbaiki bug lama di `cari.php` (placeholder PDO `:q` dipakai dua kali
→ `PDOException`, sekarang `:q1`/`:q2`).

Yang BELUM: struktur permalink URL final (masih pola warisan
`/artikel/{slug}`, `/kategori/{slug}`), logo vector master & favicon
publik (masih PNG dari operator + favicon lama), `cms-admin/` sama
sekali tidak disentuh (di luar scope perubahan ini).

## Fase 4 — Branding & Polish 🟠 Sebagian

Nama brand kerja & domain sudah ditentukan sementara: **"Bola Bola
Bola" / bolabolabola.com** — brand name masih asumsi sesi ini, belum
dikonfirmasi operator. Rename tagline/nama dasar di admin panel &
frontend sudah dilakukan (teks saja, bukan desain). Logo grafis, favicon,
dan tema visual final masih sepenuhnya belum dikerjakan — menunggu
mockup baru dari tim dev.

## Fase 5 — Pra-Launch ⚪ Belum mulai

Belum ada deploy prep genuinely milik WCM 3 V.2. `.git` folder ini masih
riwayat warisan WCM 3 V.1 dengan remote yang salah — perlu dibersihkan
manual oleh operator (lock file tidak bisa dihapus lewat Cowork, lihat
`docs/HANDOFF.md`) sebelum repo/riwayat WCM 3 V.2 yang genuinely baru
dibuat. `.cpanel.yml` sudah dikosongkan jadi placeholder supaya tidak
accidental deploy ke docroot WCM 3 V.1. Isolasi infrastruktur (IP
hosting, akun Cloudflare, akun cPanel, GSC) semuanya masih perlu
diputuskan/disiapkan operator dari nol.

## Fase 6 — AI Automation Layer ⚪ Belum mulai

Modul Growth Agent ikut ter-clone/copy (kode-nya ada, prompt netral dari
sononya) — belum aktif dipakai generate konten apapun untuk Bola Bola
Bola.

---

*Untuk checklist detail dan daftar lengkap hal yang perlu dikonfirmasi
ke operator, lihat `docs/HANDOFF.md`. Standar umum clone/handoff admin
panel ada di `wcm1_version1/docs/HANDOFF-CMS-ADMIN.md`.*
