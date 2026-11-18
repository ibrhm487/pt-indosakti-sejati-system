# Dokumentasi Sistem — PT INDOSAKTI SEJATI

**Nama sistem:** Event Operations Management System (Sistem Operasional Event)
**Perusahaan:** PT Indosakti Sejati
**Jenis:** Aplikasi internal berbasis web (multi-role)
**Tanggal dokumen:** 14 November 2026

Dokumen ini menjelaskan seluruh modul, menu per peran (role), fungsi tiap menu, alur kerja, status, serta fitur lintas sistem yang tersedia di aplikasi.

---

## 1. Ringkasan Sistem

Sistem ini mengelola operasional event secara menyeluruh, mulai dari pembuatan FJP (Formulir Jasa Pelaksanaan), perizinan, pembagian tim, penugasan lapangan, laporan cepat (quick report), absensi, penggajian shift, hingga pengelolaan gudang (stok, permintaan barang, barang masuk/keluar, retur) dan operasional dapur (kitchen untuk event JUG).

Alur operasional utama:

```
Permitter (FJP) → PIC (verifikasi, penugasan tim) → Tim Lapangan & Gudang
      → Kitchen (bila event JUG) → Admin Event (verifikasi laporan & tutup event)
```

Prinsip utama:

- **Berbasis peran (RBAC).** Setiap aksi sensitif diperiksa di server menggunakan slug izin (`permission`).
- **Multi-role.** Satu akun dapat memiliki beberapa peran. Saat login pengguna memilih peran aktif; navigasi dan ruang kerja mengikuti peran aktif tersebut.
- **Berbasis event.** Hampir seluruh data operasional (FJP, tim, laporan, absensi, gudang, kitchen) terhubung ke satu event.
- **Berbasis brand.** Data event, target kuartal, dan ringkasan dapat difilter/dikelompokkan per brand.
- **Audit trail.** Aksi penting dicatat pada audit log.

---

## 2. Teknologi

| Komponen | Teknologi |
|---|---|
| Backend | Laravel 13, PHP 8.3 |
| Frontend | React 19 + TypeScript, Inertia.js v3 |
| Styling | Tailwind CSS v4 |
| Build | Vite 8 |
| Database | Relasional (MySQL/PostgreSQL sesuai konfigurasi) |
| Penyimpanan berkas | Laravel Storage (dokumen, foto, lampiran; bersifat privat) |

---

## 3. Peran (Role) dan Hak Akses

### 3.1 Daftar Peran

| Slug | Nama | Fokus |
|---|---|---|
| `developer` | Developer | Akses teknis penuh seluruh modul dan administrasi |
| `system_admin` | System Administrator | Administrasi sistem: pengguna, master data, audit |
| `permitter` | Permitter | Membuat/mengelola FJP dan mengubah status FJP |
| `pic` | PIC | Koordinator FJP, penugasan tim, kesiapan operasional |
| `team` | Tim | Eksekutor lapangan: absensi dan laporan |
| `warehouse` | Gudang | Stok, MRV, barang masuk/keluar, retur |
| `kitchen` | Kitchen | Tugas dapur/JUG untuk event yang membutuhkan |
| `admin_event` | Admin Event | Verifikasi laporan dan penutupan event (bukan super admin) |

### 3.2 Konsep Peran Aktif

- Akun dapat memiliki lebih dari satu peran.
- Setelah kredensial valid, pengguna dengan beberapa peran memilih **peran aktif** (`/login/role`).
- Peran aktif menentukan **navigasi sidebar**, **ruang kerja**, dan sebagian **pembatasan data** (mis. PIC hanya melihat event yang menjadi tanggung jawabnya).
- Izin efektif pengguna adalah gabungan izin dari seluruh peran yang dimiliki, sehingga visibilitas menu juga difilter berdasarkan izin.

### 3.3 Matriks Izin per Peran

| Modul | slug izin | developer | system_admin | permitter | pic | team | warehouse | kitchen | admin_event |
|---|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| Dashboard | `dashboard.view` | v | v | v | v | v | v | v | v |
| Event | `events.view` | v | v | v | v | v | v | v | v |
| Event | `events.create` | v | v |  | v |  |  |  |  |
| Event | `events.update` | v | v |  | v |  |  |  |  |
| Event | `events.delete` | v | v |  | v |  |  |  |  |
| Event | `events.assign_team` | v | v |  | v |  |  |  |  |
| Event | `events.close` | v | v |  |  |  |  |  | v |
| FJP | `fjp.view` | v | v | v | v |  |  |  | v |
| FJP | `fjp.create` | v | v | v |  |  |  |  |  |
| FJP | `fjp.update` | v | v | v |  |  |  |  |  |
| FJP | `fjp.status.update` | v | v | v | v |  |  |  |  |
| FJP | `fjp.delete` | v | v | v |  |  |  |  |  |
| Lokasi | `venues.view` | v | v | v | v |  |  |  |  |
| Lokasi | `venues.approve` | v | v |  | v |  |  |  |  |
| Perizinan | `permits.view` | v | v |  |  |  |  |  |  |
| Perizinan | `permits.create` | v | v |  |  |  |  |  |  |
| Perizinan | `permits.update` | v | v |  |  |  |  |  |  |
| Perizinan | `permits.approve` | v | v |  |  |  |  |  |  |
| Perizinan | `permits.reject` | v | v |  |  |  |  |  |  |
| Target | `targets.manage` | v | v | v |  |  |  |  |  |
| Gudang | `warehouse.view` | v | v |  | v |  | v |  |  |
| Gudang | `warehouse.manage` | v | v |  |  |  | v |  |  |
| Gudang | `warehouse.request` | v | v | v | v |  | v |  |  |
| Gudang | `warehouse.approve` | v | v |  |  |  | v |  |  |
| Gudang | `warehouse.issue` | v | v |  |  |  | v |  |  |
| Gudang | `warehouse.return` | v | v |  |  |  | v |  |  |
| Kitchen | `kitchen.view` | v | v |  | v | v |  | v |  |
| Kitchen | `kitchen.manage` | v | v |  |  |  |  | v |  |
| Laporan | `reports.view` | v | v |  | v | v |  |  | v |
| Laporan | `reports.create` | v | v |  | v | v |  |  |  |
| Laporan | `reports.verify` | v | v |  |  |  |  |  | v |
| Laporan | `reports.revision` | v | v |  |  |  |  |  | v |
| Quick Report | `quick_reports.view` | v | v |  | v | v |  |  | v |
| Quick Report | `quick_reports.create` | v | v |  |  | v |  |  |  |
| Absensi | `attendance.view` | v | v |  | v | v |  |  | v |
| Absensi | `attendance.create` | v | v |  | v | v |  |  |  |
| Absensi | `attendance.manage` | v | v |  | v |  |  |  |  |
| Absensi | `attendance.payroll` | v | v |  | v |  |  |  | v |
| Admin | `admin.users.manage` | v | v |  |  |  |  |  |  |
| Admin | `admin.personnel.manage` | v | v |  |  |  |  |  |  |
| Admin | `admin.roles.manage` | v | v |  |  |  |  |  |  |
| Admin | `admin.settings.manage` | v | v |  |  |  |  |  |  |
| Admin | `admin.audit.view` | v | v |  |  |  |  |  |  |
| Admin | `admin.system.view` | v | v |  |  |  |  |  |  |
| Admin | `admin.logs.view` | v | v |  |  |  |  |  |  |

> `developer` dan `system_admin` memiliki wildcard `*` (semua izin).

---

## 4. Menu dan Fungsi per Peran

Nama menu di bawah mengikuti implementasi sidebar (`resources/js/lib/nav.tsx`).

### 4.1 Developer

| Grup | Menu | Fungsi |
|---|---|---|
| — | Dashboard | Ringkasan operasional sesuai filter brand & periode |
| Operasional | Event (submenu per brand) | Daftar, kalender, buat/ubah/hapus event, detail event |
| Operasional | FJP | Daftar & kelola FJP, ubah status, Dapodik, lampiran, pembagian tim |
| Operasional | Verifikasi | Verifikasi lokasi/venue dan perizinan |
| Operasional | Perizinan | Daftar, buat, ubah status, dokumen perizinan |
| Operasional | Target Quartal | Kelola target kuartal per brand |
| Operasional | Tim | Daftar event & pembagian tim |
| Operasional | Quick Report | Daftar laporan cepat seluruh tim |
| Operasional | Absensi | Absensi seluruh personel, koreksi/invalidasi |
| Operasional | Laporan | Riwayat laporan event (semua status) |
| Operasional | Rekap Harian | Rekap laporan per hari + ekspor CSV |
| Operasional | Gudang | Produk, MRV, MRS, OMF, retur, rekap stok, mutasi, MIOS, kartu stok |
| Operasional | Kitchen | Tugas dapur JUG, laporan washing/cooking/ready |
| Data & Sistem | Lokasi | Master venue/sekolah |
| Data & Sistem | Data Master | Brand, tipe event, venue, kategori produk |
| Data & Sistem | Target Quartal | Target kuartal (jalur admin) |
| Data & Sistem | Pengguna | Buat/ubah/hapus akun, atur peran |
| Data & Sistem | Personel | Data personel (nama, kontak, status) |
| Data & Sistem | Role & Izin | Kelola peran dan pemetaan izin |
| Data & Sistem | Pengaturan | Info perusahaan, tenggat laporan, ambang stok, tarif shift |
| Data & Sistem | Audit Log | Riwayat aksi penting sistem |
| Data & Sistem | Kesehatan Sistem | Status aplikasi/konfigurasi |
| Data & Sistem | Log Aplikasi | Penampil & unduh log |

### 4.2 System Administrator

Navigasi dan fungsi sama dengan Developer. Penekanan peran pada administrasi: pengguna, personel, role & izin, master data, pengaturan, audit log, kesehatan sistem, dan log aplikasi. Memiliki akses penuh ke modul operasional.

### 4.3 Permitter

| Grup | Menu | Fungsi |
|---|---|---|
| — | Dashboard | Ringkasan event dan target |
| Permitter | FJP | Membuat, mengubah, menghapus FJP, serta mengubah status FJP |
| Permitter | Lokasi | Melihat daftar venue/sekolah |
| Permitter | Target Quartal | Melihat/mengatur target kuartal per brand |
| Permitter | Permintaan Sampling | Membuat permintaan stok (MRV) untuk sampling event |

Fitur utama permitter pada FJP:
- Membuat FJP baru (event, brand, tipe event, venue, tanggal, target sampling, jumlah siswa, dsb).
- Mengubah data FJP dan catatan/lokasi secara inline.
- Mengubah status FJP: Plan, Confirm, Cancel, Reschedule.
- Mengunggah lampiran FJP.
- Sinkronisasi data sekolah dari Dapodik (cari nama sekolah / NPSN).
- Melihat pratinjau/ringkasan jadwal tim yang tersimpan (penyimpanan jadwal tim memerlukan izin `events.assign_team` yang dimiliki PIC/admin).

### 4.4 PIC

| Grup | Menu | Fungsi |
|---|---|---|
| — | Dashboard | Ringkasan event tanggung jawab PIC |
| Ruang Kerja PIC | FJP | Melihat FJP, mengubah status FJP |
| Ruang Kerja PIC | Lokasi | Melihat & memverifikasi venue/sekolah |
| Ruang Kerja PIC | Verifikasi | Antrian verifikasi venue/perizinan |
| Ruang Kerja PIC | Pembagian Tim | Daftar event, lihat detail, atur tim & jadwal |
| Ruang Kerja PIC | Form Permintaan | Membuat permintaan stok (MRV) |
| Ruang Kerja PIC | Shift & Gaji | Rekap penggajian shift personel |
| Gudang & Dapur | Rekap Stok | Ringkasan stok gudang |
| Gudang & Dapur | Kitchen | Memantau tugas dapur JUG |

Catatan: PIC **tidak** memiliki menu Quick Report. Laporan quick report tim dilihat melalui **Pembagian Tim → Lihat Detail** pada tab Quick Report di halaman detail event.

### 4.5 Tim (Team)

| Grup | Menu | Fungsi |
|---|---|---|
| — | Dashboard | Ringkasan tugas & jadwal pribadi |
| Jadwal Tim | Jadwal Saya | Daftar event/jadwal yang ditugaskan |
| Laporan & Absensi | Quick Report | Membuat laporan cepat harian |
| Laporan & Absensi | Riwayat Laporan Saya | Riwayat quick report yang dibuat |
| Laporan & Absensi | Absensi Saya | Absensi (check-in/check-out) |

Fitur tim:
- Melihat detail jadwal dan event yang ditugaskan.
- Membuat quick report: tanggal/jam aktivitas, target & realisasi sampling/penjualan, paket booth/toko, catatan pembelajaran, unggah foto/video.
- Absensi: unggah bukti foto pra-keberangkatan, check-in, check-out.
- Melihat detail riwayat laporan.

### 4.6 Warehouse (Gudang)

| Grup | Menu | Fungsi |
|---|---|---|
| — | Dashboard | Ringkasan stok, MRV, kitchen |
| Gudang / Warehouse | Produk | CRUD produk, SKU, kategori, satuan, stok minimum, konversi JUG |
| Gudang / Warehouse | Permintaan Masuk / MRV | Daftar & pembuatan permintaan stok, approval, issue |
| Gudang / Warehouse | Barang Masuk / MRS | Penerimaan barang (slip), nomor, foto |
| Gudang / Warehouse | Barang Keluar / OMF | Pengeluaran barang, nomor, detail |
| Gudang / Warehouse | MIOS | Monitoring stok/inventori |

Halaman tambahan yang dapat diakses (di luar sidebar): Rekap Stok (`warehouse.inventory.index`), Mutasi Stok (`warehouse.inventory.movements`), Kartu Stok per produk (`warehouse.inventory.stock-card`), Retur/Pengembalian (`warehouse.returns.*`), dan penyesuaian stok (`warehouse.inventory.adjust`).

### 4.7 Kitchen

| Grup | Menu | Fungsi |
|---|---|---|
| — | Dashboard | Ringkasan tugas kitchen |
| Dapur | Dapur & Higienitas | Daftar tugas kitchen, status, laporan washing/cooking/ready |

Fitur kitchen:
- Membuat tugas kitchen untuk event yang membutuhkan JUG.
- Mengubah status tugas: pending, confirmed, preparing, ready, dispatched, cancelled.
- Menambahkan laporan washing, cooking, dan ready beserta foto/media.
- Mengunduh foto laporan dan menghapus media.

### 4.8 Admin Event

| Grup | Menu | Fungsi |
|---|---|---|
| — | Dashboard | Ringkasan event & laporan |
| Admin Event | FJP | Melihat FJP |
| Admin Event | Event (submenu per brand) | Melihat event, menutup event |
| Admin Event | Rekap Harian | Rekap laporan per hari |
| Admin Event | Laporan per Event | Laporan dikelompokkan per event |
| Admin Event | Riwayat Laporan | Riwayat seluruh laporan |
| Admin Event | Tim | Melihat pembagian tim |
| Admin Event | Absensi | Melihat absensi |
| Admin Event | Shift & Gaji | Rekap penggajian |

Tugas utama: verifikasi laporan (verify), meminta revisi (revision), memantau kelengkapan laporan, dan menutup event (`events.close`).

---

## 5. Detail Modul dan Fitur

### 5.1 Autentikasi & Akun

- Login dua tahap: email + password, lalu pemilihan peran aktif (jika multi-role).
- Registrasi mandiri dinonaktifkan; akun dibuat oleh administrator.
- Lupa password & reset password melalui email.
- Verifikasi email, konfirmasi password, dan ganti password.
- Profil: ubah nama, email, nomor telepon, dan password; hapus akun.
- Logout.

### 5.2 Dashboard

- Filter brand dan rentang tanggal.
- Kartu statistik menyesuaikan peran:
  - Event: total, aktif, mendatang, selesai.
  - Laporan: menunggu verifikasi, perlu revisi.
  - Gudang: total SKU, stok menipis, permintaan stok.
  - Kitchen: menunggu, disiapkan, siap, dikirim.
  - Perizinan: menunggu, disetujui.
  - FJP: draft.
  - MRV: menunggu.
- Grafik tren bulanan (total event vs event selesai).
- Ringkasan target kuartal per brand (cups & shift) beserta persentase capaian.
- Daftar event mendatang dikelompokkan per brand.

### 5.3 Brand & Brand Hub

- Konteks brand aktif dapat diubah (disimpan pada sesi).
- Halaman Brand Hub menampilkan statistik dan ringkasan event per brand.
- Submenu Event per brand pada sidebar.

### 5.4 Event

- Daftar event dengan pencarian (nama/kode), filter status, venue, tipe event, brand.
- Tampilan kalender bulanan.
- Pengelompokan event per brand.
- Buat/ubah/hapus event (sesuai izin).
- Status event: draft, planning, permission, team_assignment, warehouse_preparation, kitchen_preparation, ready, ongoing, completed, cancelled, dengan transisi status yang dibatasi.
- Detail event memiliki tab kontekstual:
  - Ringkasan
  - FJP & Perizinan
  - Tim & Absensi
  - MRV & Barang
  - Kitchen (bila event JUG/butuh dapur)
  - Laporan PIC
  - Foto & Dokumen
  - Quick Report
  - Riwayat Aktivitas (audit)
- Penutupan event oleh Admin Event/berwenang.
- Penugasan tim: tambah, ubah, hapus anggota tim pada event (ketua tim tidak boleh sama dengan SPG).

### 5.5 FJP (Formulir Jasa Pelaksanaan)

- Daftar FJP dengan pencarian, filter periode, brand, dan status.
- Form FJP mencakup: event, brand, tipe event, venue, tanggal & waktu, target sampling, jumlah siswa, alamat/maps, catatan, lampiran.
- Status FJP: **Plan, Confirm, Cancel, Reschedule, Done**.
- `Done` diisi otomatis ketika quick report dan absensi telah lengkap.
- Perubahan status FJP (khususnya **Confirm**) memicu alur penugasan tim.
- Edit inline untuk catatan, alamat, dan tautan peta.
- Sinkronisasi Dapodik: pencarian sekolah berdasarkan nama/NPSN dan penarikan jumlah siswa.
- Perbandingan jumlah siswa (input vs Dapodik).
- Unggah lampiran FJP.
- Generator jadwal tim (tersedia bagi PIC/admin dengan izin penugasan):
  - Pratinjau jadwal tim dari FJP berstatus Confirm.
  - Simpan pembagian tim per tanggal.
  - Buat gambar jadwal (PNG) dan bagikan/unduh.
- Target kuartal pada konteks FJP.

### 5.6 Dapodik

- Pencarian sekolah berdasarkan nama atau NPSN.
- Pengambilan detail sekolah (jumlah siswa, sumber data).
- Digunakan pada form FJP untuk mengisi jumlah siswa secara otomatis.

### 5.7 Venue / Lokasi & Verifikasi

- Master venue/sekolah: nama, NPSN, status sekolah, alamat, kota, provinsi, kontak.
- Pengajuan venue baru.
- Approval/rejection venue oleh PIC/berwenang.
- Halaman Verifikasi sebagai antrian gabungan verifikasi venue dan perizinan.

### 5.8 Perizinan

- Daftar perizinan dengan filter status.
- Pembuatan perizinan: event, venue, tenggat, penanggung jawab, catatan; nomor izin dibuat otomatis.
- Status perizinan: draft, submitted, pending, approved, rejected, cancelled dengan transisi yang dibatasi.
- Unggah dan unduh dokumen perizinan (privat).
- Alasan penolakan/revisi.

### 5.9 Target Kuartal

- Target per brand: jumlah cups dan jumlah shift per kuartal.
- Dua jalur akses: halaman target kuartal operasional dan halaman admin.
- Ringkasan capaian (aktual vs target) ditampilkan di dashboard.

### 5.10 Tim & Pembagian Tim

- Halaman `team.index` bersifat role-aware:
  - **PIC**: "Pembagian Tim" — daftar event yang menjadi tanggung jawabnya, tombol **Lihat Detail**.
  - **Tim**: "Jadwal Saya" — daftar jadwal yang ditugaskan.
  - **Admin/Developer**: daftar seluruh event dan tim.
- Detail event (dari tombol Lihat Detail) menampilkan tab Tim & Absensi, Quick Report, Laporan PIC, dsb.
- Penambahan/penghapusan anggota tim, pengaturan jadwal (tanggal, jam, peran), serta ketua tim.
- Aturan: ketua tim tidak boleh sama dengan SPG.

### 5.11 Quick Report

- Dibuat oleh tim di lapangan.
- Data: tanggal & jam aktivitas, target dan realisasi sampling, target dan realisasi penjualan, paket booth/toko, pembelajaran/catatan, foto dan video.
- Daftar tugas/laporan, riwayat pribadi, dan halaman detail.
- Media dapat diunduh.
- Quick report yang lengkap bersama absensi berkontribusi pada status FJP menjadi **Done**.
- **Catatan implementasi:** menu Quick Report di sidebar hanya untuk peran Tim. PIC melihat quick report melalui detail event (Pembagian Tim → Lihat Detail → tab Quick Report).

### 5.12 Laporan Event (Report)

- Pembuatan laporan event: target sampling, realisasi, kendala, catatan, unggah dokumen.
- Alur status: **draft → submitted → verified**, dengan **revision** bila perlu perbaikan.
- Aksi: simpan draft, submit, verifikasi, minta revisi (sesuai izin).
- Daftar laporan, laporan per event, dan rekap harian.
- Ekspor rekap harian ke CSV.
- Tab "Laporan PIC" pada detail event.

### 5.13 Absensi & Penggajian

- Absensi per event/personel dengan status: **planned, in_progress, completed, invalid**.
- Alur: buat absensi (planned), unggah bukti foto pra-keberangkatan, check-in (in_progress), check-out (completed).
- Durasi kerja dihitung dari check-in ke check-out.
- Koreksi absensi (ubah jam) dan invalidasi (status invalid) oleh pengelola.
- Kelayakan bayar: harus lengkap (check-in & check-out) dan tidak invalid.
- **Shift & Gaji**: rekap penggajian berdasarkan tarif shift; dapat difilter per event/personel.
- Pengaturan tarif shift oleh admin.

### 5.14 Gudang (Warehouse)

Submodul:

1. **Produk**
   - CRUD produk: nama, deskripsi, SKU, package SKU, tipe produk, brand, kategori, satuan, stok minimum, konversi JUG (sachet per jug), status aktif.
   - Stok awal melalui lot code & kuantitas.

2. **MRV (Permintaan Stok)**
   - Daftar permintaan, pembuatan satuan maupun massal (bulk).
   - Status: pending, approved, rejected, issued, returned, cancelled.
   - Aksi: approve, reject, issue (keluarkan stok).
   - Foto pendukung dan unduhan foto.
   - Nomor MRV dapat diperbarui.
   - Pembuatan MRV oleh PIC, Permitter, dan Warehouse.

3. **MRS (Barang Masuk / Penerimaan)**
   - Slip penerimaan barang, nomor penerimaan, detail item, foto.
   - Daftar dan detail penerimaan.

4. **OMF (Barang Keluar)**
   - Pencatatan pengeluaran barang, nomor OMF, detail item.
   - Daftar dan detail.

5. **Retur / Pengembalian**
   - Pencatatan pengembalian stok dari lapangan, foto bukti.

6. **Inventori**
   - **Rekap Stok**: ringkasan stok per produk.
   - **Mutasi Stok**: riwayat pergerakan stok.
   - **Kartu Stok**: riwayat per produk (masuk/keluar/saldo).
   - **Penyesuaian Stok**: koreksi stok manual (dengan izin kelola).
   - **MIOS**: monitoring inventori operasional.

### 5.15 Kitchen (Dapur & Higienitas)

- Tugas kitchen untuk event yang membutuhkan JUG.
- Status: **not_required, pending, confirmed, preparing, ready, dispatched, cancelled**.
- Laporan bertahap:
  - **Washing** (pencucian) + foto.
  - **Cooking** (pemasakan) + foto.
  - **Ready** (siap) + foto.
- Media tambahan dapat diunggah/dihapus dan diunduh.
- Dapat dipantau oleh PIC, Tim, dan Admin melalui detail event.

### 5.16 Administrasi Sistem

| Modul | Fungsi |
|---|---|
| Pengguna | Daftar, buat, ubah, hapus akun; penetapan peran; status akun |
| Personel | Data personel: nama, kontak, dsb. (dipakai pada penugasan/absensi) |
| Role & Izin | CRUD peran dan pemetaan izin (khusus peran kustom) |
| Data Master | Brand, tipe event, venue, kategori produk |
| Pengaturan | Info perusahaan, tenggat laporan, ambang stok menipis |
| Tarif Shift | CRUD tarif shift untuk penggajian |
| Target Kuartal | Target cups & shift per brand |
| Audit Log | Riwayat aksi penting (aktor, aksi, entitas, perubahan) |
| Kesehatan Sistem | Status/konfigurasi aplikasi |
| Log Aplikasi | Penampil log dan unduh berkas log |

### 5.17 Dokumen & Lampiran

- Unggah dokumen ke penyimpanan privat.
- Pratinjau (preview) dan unduh dokumen.
- Hapus dokumen (sesuai izin).
- Unduh lampiran FJP, dokumen perizinan, foto absensi, foto MRV/MRS/retur, media kitchen, dan media quick report.
- Tersedia tab "Foto & Dokumen" pada detail event.

---

## 6. Status & Alur Kerja

### 6.1 Status Event

| Status | Arti |
|---|---|
| `draft` | Event baru/belum diproses |
| `planning` | Dalam perencanaan |
| `permission` | Proses perizinan |
| `team_assignment` | Penugasan tim |
| `warehouse_preparation` | Persiapan gudang |
| `kitchen_preparation` | Persiapan dapur |
| `ready` | Siap berjalan |
| `ongoing` | Sedang berjalan |
| `completed` | Selesai |
| `cancelled` | Dibatalkan |

Transisi dibatasi sesuai definisi `EventStatus::allowedTransitions`.

### 6.2 Status FJP

| Status | Arti |
|---|---|
| `plan` | Direncanakan |
| `confirm` | Dikonfirmasi (memicu penugasan tim) |
| `cancel` | Dibatalkan |
| `reschedule` | Dijadwalkan ulang |
| `done` | Selesai (otomatis setelah quick report + absensi lengkap) |

### 6.3 Status Perizinan

`draft → submitted → pending → approved / rejected / cancelled` (dengan aturan transisi).

### 6.4 Status Laporan

`draft → submitted → verified`, dengan `revision` sebagai jalur perbaikan.

### 6.5 Status MRV

`pending → approved → issued`, serta `rejected`, `returned`, `cancelled`.

### 6.6 Status Kitchen

`not_required`, `pending`, `confirmed`, `preparing`, `ready`, `dispatched`, `cancelled`.

### 6.7 Status Absensi

| Status | Arti |
|---|---|
| `planned` | Absensi dibuat, belum check-in |
| `in_progress` | Sudah check-in |
| `completed` | Sudah check-out |
| `invalid` | Dibatalkan/diinvalidasi |

---

## 7. Fitur Lintas Sistem

- **RBAC di server.** Setiap route dilindungi middleware `permission:<slug>`; UI hanya menyembunyikan menu, keputusan akhir tetap di server.
- **Multi-role & peran aktif.** Navigasi per peran; data dibatasi sesuai peran aktif (mis. PIC hanya event miliknya).
- **Konteks brand.** Filter brand global tersimpan pada sesi; dashboard, event, dan target mengikuti brand aktif.
- **Audit trail.** Pencatatan aksi create/update/delete/status penting (aktor, modul, entitas, nilai lama/baru).
- **Penyimpanan privat.** Semua berkas sensitif diakses melalui route ber-otorisasi (preview/download), bukan URL publik.
- **Validasi server-side.** Semua input divalidasi; aturan bisnis (mis. ketua tim ≠ SPG, transisi status) ditegakkan di backend.
- **Notifikasi & toast.** Umpan balik aksi (sukses/gagal) melalui toast di antarmuka.
- **Ekspor.** Rekap harian dapat diekspor CSV; gambar jadwal tim diekspor PNG; log dapat diunduh.
- **Responsif.** Antarmuka Tailwind responsif untuk desktop dan perangkat lapangan.
- **Pencarian & filter.** Tersedia pada event, FJP, perizinan, laporan, gudang, dan absensi.
- **Paginasi.** Daftar besar menggunakan paginasi dengan query string.
- **Integrasi Dapodik.** Pengambilan data sekolah via nama/NPSN untuk FJP.

---

## 8. Alur Bisnis End-to-End

1. **Permitter** membuat FJP (data event, venue, target, jumlah siswa, lampiran) dan mengubah status menjadi **Confirm**.
2. Setelah **Confirm**, **PIC**:
   - Memverifikasi venue/perizinan,
   - Menyusun **pembagian tim** (ketua, SPG, jadwal),
   - Membuat **permintaan stok (MRV)**, jika diperlukan.
3. **Gudang** memproses MRV (approve → issue), mencatat barang masuk (MRS), barang keluar (OMF), retur, dan memperbarui stok.
4. **Kitchen** menangani event JUG: status tugas dan laporan washing/cooking/ready.
5. **Tim** di lapangan:
   - Melakukan absensi (bukti pra-keberangkatan, check-in, check-out),
   - Mengisi **Quick Report** harian.
6. **PIC** memantau melalui detail event dan membuat **laporan event** (submit).
7. **Admin Event** memverifikasi laporan (atau meminta revisi) dan **menutup event** setelah lengkap.
8. FJP otomatis menjadi **Done** ketika quick report dan absensi lengkap. **Shift & Gaji** dapat direkap untuk penggajian.

---

## 9. Ringkasan Route (Referensi Teknis)

| Area | Prefix | Keterangan |
|---|---|---|
| Dashboard | `/dashboard` | Ringkasan per peran |
| Brand | `/brand-context`, `/brands/{brand}` | Konteks & hub brand |
| Workspace | `/permitter`, `/tim`, `/gudang`, `/dapur`, `/pic`, `/admin`, `/admin-event` | Titik masuk ruang kerja per peran |
| Target | `/quarterly-targets`, `/admin/quarterly-targets`, `/fjp/target-quartal` | Target kuartal |
| Venue | `/venues`, `/verifications` | Lokasi & verifikasi |
| Event | `/events` | CRUD, status, tim, tutup event |
| FJP | `/fjp` | CRUD, status, Dapodik, lampiran, jadwal tim |
| Dapodik | `/dapodik/search`, `/dapodik/{npsn}` | Pencarian sekolah |
| Perizinan | `/permits` | CRUD, transisi, dokumen |
| Gudang | `/warehouse/*` | Produk, MRV, MRS, OMF, retur, inventori, MIOS, kartu stok |
| Kitchen | `/kitchen`, `/kitchen-reports/*` | Tugas & laporan kitchen |
| Laporan | `/reports` | CRUD, submit, verify, revisi, rekap harian, ekspor |
| Quick Report | `/quick-reports` | Index, riwayat, create, detail, media |
| Absensi | `/attendance` | Absensi, bukti, check-in/out, koreksi, payroll |
| Tim | `/team` | Jadwal/pembagian tim |
| Dokumen | `/documents/*` | Unggah, preview, unduh, hapus |
| Admin | `/admin/*` | Pengguna, personel, role, master, pengaturan, audit, sistem, log |
| Profil | `/profile` | Ubah profil & password |

---

## 10. Catatan Implementasi Penting

- **Quick Report untuk PIC dihapus dari navigasi.** Laporan tim diakses PIC melalui **Pembagian Tim → Lihat Detail** (tab Quick Report pada detail event).
- Halaman **Pembagian Tim** dan **Jadwal Saya** menggunakan komponen yang sama (`team.index`) dengan tampilan berbeda menurut peran.
- Beberapa halaman gudang (rekap stok, mutasi, kartu stok, retur, penyesuaian) tidak selalu tampil di sidebar peran gudang, tetapi tetap dapat diakses melalui alur/detail terkait dan dilindungi izin.
- Status **FJP Done** bukan aksi manual: dihasilkan dari kelengkapan quick report dan absensi.
- Registrasi mandiri tidak tersedia; seluruh akun dibuat administrator dan login melalui pemilihan peran aktif.

---

## 11. Berkas Kode Referensi

| Kebutuhan | Lokasi |
|---|---|
| Pemetaan menu per peran | `resources/js/lib/nav.tsx` |
| Render sidebar | `resources/js/Components/SidebarNav.tsx` |
| Layout & injeksi nav | `resources/js/Layouts/AppLayout.tsx` |
| Daftar peran | `app/Enums/RoleSlug.php` |
| Katalog izin | `app/Support/Permissions.php` |
| Pemetaan izin per peran | `app/Support/RoleDefinitions.php` |
| Route web & auth | `routes/web.php`, `routes/auth.php` |
| Controller fitur | `app/Http/Controllers/*`, `app/Http/Controllers/Admin/*`, `app/Http/Controllers/Warehouse/*` |
| Status & enum | `app/Enums/*` |
| Model | `app/Models/*` |
| Dashboard | `resources/js/Pages/Dashboard.tsx` |
| Detail event (tab) | `resources/js/Pages/Events/Show.tsx` |
| Pembagian tim | `resources/js/Pages/Team/Index.tsx` |
