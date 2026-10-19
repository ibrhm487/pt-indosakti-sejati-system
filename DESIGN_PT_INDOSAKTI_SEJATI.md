# DESIGN.md — PT INDOSAKTI SEJATI
## Panduan UI/UX untuk Sistem Operasional Event Sampling

> Dokumen ini menjadi acuan desain bagi AI coding agent/developer saat memperbaiki UI/UX sistem PT Indosakti Sejati. Gunakan referensi visual dari [Dribbble — Ticket Booking Dashboard](https://dribbble.com/shots/24948248-Ticket-Booking-Dashboard) sebagai inspirasi tata letak dashboard, hierarki informasi, kartu ringkasan, tabel, filter, dan visualisasi data. **Jangan menyalin mentah** desain ticket booking: adaptasikan untuk operasional event sampling perusahaan.

---

## 1. Tujuan Desain

Membuat sistem internal yang terlihat profesional, rapi, modern, dan mudah digunakan untuk mengelola operasional event dari perencanaan hingga pelaporan. UI harus terasa seperti dashboard SaaS operasional yang benar-benar digunakan perusahaan, bukan landing page promosi atau template AI generik.

Prioritas:
1. Informasi operasional mudah dipindai.
2. Pengguna langsung melihat tugas, status, dan tindakan yang perlu dilakukan.
3. Setiap role hanya melihat menu dan data yang relevan dengan kewenangannya.
4. Form input tidak membingungkan dan memberikan validasi yang jelas.
5. Desain konsisten di semua modul tanpa mengubah business logic yang sudah berjalan.
6. Tetap responsif untuk desktop, tablet, dan ponsel.

## 2. Konteks Sistem dan Alur Kerja

Nama aplikasi: **PT INDOSAKTI SEJATI**  
Nama produk yang disarankan: **Event Operations Management System**  
Deskripsi: *Satu workspace untuk perencanaan, pelaksanaan, pengelolaan material, dan pelaporan event.*

Alur operasional utama:

**Permitter → PIC → Tim & Warehouse → Kitchen → Admin per Event**

Catatan: alur ini merupakan proses bisnis, bukan berarti setiap role harus memakai halaman dashboard yang sama. Gunakan navigasi dan ringkasan yang disesuaikan dengan role aktif.

### Role sistem

- `system_admin`: pengaturan sistem dan akses penuh.
- `permitter`: FJP, perencanaan event, data sekolah/lokasi jika relevan, target, dan dokumen perizinan.
- `pic`: penugasan tim, jadwal event, laporan harian, dan MRV.
- `team`: jadwal tugas dan laporan aktivitas/foto dari lapangan.
- `warehouse`: permintaan material, persetujuan MRV, transaksi barang, dan stok.
- `kitchen`: laporan kegiatan kitchen dan laporan foto sesuai proses event.
- `admin_event`: rekap dan laporan event serta dokumen yang diperlukan.

Sistem mendukung pengguna yang dapat memiliki lebih dari satu role. Role aktif menentukan workspace, menu, dan izin yang sedang digunakan. Sediakan kontrol **Ganti Peran** yang jelas; setelah pergantian, sidebar, judul workspace, dan hak akses harus langsung mengikuti role aktif.

## 3. Prinsip Visual

### Arah visual
- Modern enterprise SaaS.
- Bersih, terstruktur, berorientasi data.
- Gunakan whitespace secukupnya, bukan ruang kosong berlebihan.
- Gunakan border tipis dan shadow lembut; hindari efek glassmorphism berlebihan.
- Jangan menggunakan gradien mencolok pada setiap kartu.
- Hindari tampilan seperti website tiket konsumen: tidak ada pencarian tiket, harga tiket, kursi, checkout, atau elemen perjalanan.
- Hindari emoji sebagai ikon navigasi. Gunakan ikon konsisten dari library yang sudah terpasang di project.

### Palet warna yang disarankan
Gunakan token warna agar mudah disesuaikan dengan brand resmi. Jika project sudah memiliki token warna yang konsisten, pertahankan dan sesuaikan hanya bila diperlukan.

| Token | Warna | Penggunaan |
|---|---|---|
| `--color-primary` | `#1F3A5F` | Tombol utama, item navigasi aktif, tautan penting |
| `--color-primary-hover` | `#172C49` | Hover tombol utama |
| `--color-accent` | `#D9A441` | Highlight terbatas, indikator prioritas |
| `--color-page` | `#F5F7FA` | Latar belakang workspace |
| `--color-surface` | `#FFFFFF` | Kartu, tabel, panel, modal |
| `--color-text` | `#172033` | Teks utama |
| `--color-muted` | `#667085` | Deskripsi dan metadata |
| `--color-border` | `#E4E7EC` | Border dan pemisah |
| `--color-success` | `#16845B` | Selesai, disetujui, aktif |
| `--color-warning` | `#B7791F` | Menunggu, perlu perhatian |
| `--color-danger` | `#C93636` | Ditolak, dibatalkan, kesalahan |
| `--color-info` | `#2877B5` | Informasi dan status netral |

Warna status tidak boleh menjadi satu-satunya penanda. Sertakan label teks dan, bila sesuai, ikon.

### Tipografi
- Gunakan font yang sudah ada di project; jika belum ada, gunakan **Inter** atau font sans-serif sistem yang mudah dibaca.
- Judul halaman: 24–30 px, semibold/bold.
- Judul bagian/kartu: 15–18 px, semibold.
- Isi: 14–16 px.
- Label dan metadata: 12–13 px.
- Hindari terlalu banyak ukuran font dan penggunaan huruf kapital penuh.

### Bentuk dan jarak
- Radius kartu: 12–16 px.
- Radius input/tombol: 8–10 px.
- Tinggi kontrol input desktop: sekitar 40–44 px.
- Spacing dasar: kelipatan 4 atau 8 px.
- Gunakan border `1px solid` warna border untuk memisahkan panel.
- Shadow tipis hanya untuk elemen yang perlu memiliki kedalaman.

## 4. Struktur Layout Dashboard

### Desktop
Gunakan layout aplikasi dengan:
1. **Sidebar kiri** yang stabil, lebar sekitar 240–260 px.
2. **Topbar** berisi breadcrumb/judul konteks, pencarian jika memang berguna, notifikasi bila tersedia, profil pengguna, dan kontrol role aktif.
3. **Area konten utama** dengan lebar fleksibel dan padding 24–32 px.
4. Konten yang panjang dapat scroll tanpa membuat navigasi utama ikut bergerak.

Sidebar:
- Logo/wordmark PT INDOSAKTI SEJATI.
- Nama workspace atau role aktif.
- Menu dikelompokkan berdasarkan fungsi.
- Item aktif memiliki kontras jelas.
- Area profil dan aksi keluar ditempatkan di bagian bawah.
- Menu hanya tampil jika diizinkan oleh RBAC; jangan hanya menyembunyikan menu di frontend tanpa memeriksa izin backend.

### Mobile/tablet
- Sidebar berubah menjadi drawer atau navigasi ringkas.
- Tabel lebar dapat memakai horizontal scroll atau berubah menjadi daftar kartu.
- Tombol utama tetap mudah dijangkau.
- Form satu kolom pada layar sempit.
- Hindari elemen penting yang hanya dapat diakses melalui hover.

## 5. Dashboard Utama

Dashboard harus menampilkan ringkasan yang relevan dengan role aktif, bukan satu dashboard generik untuk semua orang.

### Komponen yang disarankan
- Sapaan/judul kontekstual, misalnya **Ringkasan Operasional**.
- Filter periode tanggal dan filter brand bila sesuai izin.
- Kartu metrik ringkas.
- Grafik tren atau progres yang membantu pengambilan keputusan.
- Daftar event terdekat/aktif.
- Daftar tugas atau pengajuan yang membutuhkan tindakan.
- Aktivitas terbaru bila datanya memang tersedia.

### Contoh metrik
Gunakan hanya metrik yang dapat dihitung dari data nyata. Jangan membuat angka dummy seolah-olah merupakan data produksi.

- Total event pada periode terpilih.
- Event terjadwal, berjalan, selesai, atau dibatalkan.
- FJP menunggu proses atau memerlukan tindak lanjut.
- Target kuartal dibandingkan realisasi.
- MRV menunggu persetujuan.
- Ringkasan stok atau peringatan stok rendah untuk role Warehouse.
- Laporan harian yang belum dikirim.
- Perbandingan jumlah siswa versi sekolah dan versi Dapodik, jika relevan untuk FJP.

Setiap kartu metrik idealnya berisi label, nilai, keterangan periode, dan indikator perubahan hanya jika perbandingan tersebut benar-benar dihitung.

### Grafik
- Gunakan grafik garis/batang untuk tren event, target, dan realisasi.
- Gunakan stacked bar/donut hanya jika kategori sedikit dan proporsinya memang membantu.
- Sertakan label, tooltip, periode, dan keadaan kosong.
- Hindari grafik dekoratif yang tidak membantu keputusan.
- Pastikan grafik menggunakan data backend, bukan angka statis yang tampak nyata.

## 6. Pola Halaman Modul

Setiap halaman modul sebaiknya memiliki pola konsisten:

1. Breadcrumb (jika struktur navigasi bertingkat).
2. Judul halaman.
3. Deskripsi singkat tujuan halaman.
4. Tombol aksi utama di area judul.
5. Filter/pencarian di atas tabel bila relevan.
6. Tabel atau daftar utama.
7. Pagination dan jumlah hasil.
8. State loading, empty, error, dan success yang jelas.

### Tabel data
- Header kolom jelas dan ringkas.
- Gunakan alignment yang sesuai: teks rata kiri, angka rata kanan bila membantu.
- Kolom status menggunakan badge dengan label.
- Aksi per baris diletakkan konsisten, misalnya Detail, Edit, atau menu aksi.
- Hindari terlalu banyak kolom; pindahkan detail tambahan ke halaman detail atau drawer.
- Filter harus bekerja dan dapat digabungkan tanpa merusak query.
- Pertahankan nilai filter saat pengguna berpindah halaman jika memungkinkan.
- Konfirmasi sebelum aksi destruktif.
- Tampilkan pesan jika data kosong dan jelaskan cara menambahkan data.

## 7. Panduan UI per Role dan Modul

### 7.1 Permitter
Fokus: perencanaan event dan FJP.

Tampilan:
- Ringkasan FJP berdasarkan status.
- Tabel FJP dengan pencarian, filter periode, brand, dan status.
- Aksi membuat/mengubah FJP sesuai izin.
- Status FJP yang mudah dibedakan: draft/direncanakan, menunggu, disetujui, ditolak, dibatalkan, atau status aktual yang sudah digunakan sistem.
- Jika FJP ditolak atau dibatalkan, tampilkan alasan/keterangan secara jelas.
- Tampilkan target per shift dan target cups per kuartal bila tersedia.
- Sediakan tempat untuk dokumen/foto surat izin dan status dokumennya.
- Data jumlah siswa menampilkan **versi sekolah**, **versi Dapodik**, dan **selisih** secara eksplisit.
- Tombol pengisian data Dapodik dan input manual harus memiliki label yang jelas; jangan menyatakan data terhubung otomatis jika integrasi belum benar-benar tersedia.
- Data lokasi/koordinat ditampilkan hanya jika ada sumber data valid.

### 7.2 PIC
Fokus: kesiapan dan pelaksanaan event.

Tampilan:
- Jadwal event aktif dan event mendatang.
- Penugasan personel/tim pada FJP.
- Status kesiapan setiap event.
- Form laporan harian.
- Form MRV untuk kebutuhan material.
- Ringkasan target/realisasi per event jika data tersedia.
- Indikator laporan yang belum lengkap.

### 7.3 Team
Fokus: tugas lapangan dan pelaporan aktivitas.

Tampilan:
- Jadwal event yang ditugaskan kepada pengguna.
- Detail lokasi, waktu/shift, PIC, dan instruksi yang diperlukan.
- Tombol aksi utama untuk membuka detail dan mengirim laporan.
- Upload foto aktivitas dengan preview, status unggah, dan validasi file.
- Status laporan: belum dikirim, terkirim, atau perlu revisi sesuai status sistem.
- Pada ponsel, prioritaskan jadwal dan pengiriman laporan.

### 7.4 Warehouse
Fokus: MRV dan akurasi persediaan.

Tampilan:
- Ringkasan permintaan material yang menunggu tindakan.
- Daftar MRV dengan pemohon, event, tanggal, item, kuantitas, dan status.
- Detail MRV sebelum disetujui/ditolak.
- Riwayat barang masuk dan keluar beserta dokumentasi foto jika tersedia.
- Ringkasan stok dan indikator stok rendah berdasarkan ambang yang dikonfigurasi.
- Persetujuan MRV harus memiliki konfirmasi yang jelas.
- Jika persetujuan mengurangi stok, perubahan stok harus berasal dari transaksi backend yang aman dan tercatat; UI tidak boleh mengurangi angka stok hanya di sisi browser.
- Tampilkan pesan jika stok tidak cukup dan jelaskan langkah berikutnya.

### 7.5 Kitchen
Fokus: kegiatan kitchen dan pelaporan.

Tampilan:
- Daftar pekerjaan/event yang relevan.
- Status proses kitchen.
- Form laporan harian dan unggah foto.
- Jika proses berbeda berdasarkan jenis event/produk, tampilkan form sesuai konfigurasi yang benar-benar didukung sistem.
- Jangan memaksa semua event menggunakan alur jug jika ada event yang menggunakan stok produk secara langsung.

### 7.6 Admin per Event
Fokus: kelengkapan administrasi dan rekap event.

Tampilan:
- Daftar event dan ringkasan status.
- Detail laporan dari tahap operasional yang relevan.
- Dokumen izin dan foto terkait event.
- Ringkasan pelaksanaan, target/realisasi, dan jumlah siswa jika relevan.
- Status kelengkapan dokumen/laporan.
- Filter berdasarkan periode, brand, dan status sesuai hak akses.

### 7.7 System Admin
Fokus: konfigurasi, akses, dan audit.

Kelompok menu yang disarankan:
- Dashboard sistem.
- Manajemen user dan role.
- Personel.
- Brand.
- Jenis event.
- Venue/lokasi.
- Kategori produk.
- Target kuartal per brand.
- Pengaturan, termasuk tarif shift.
- Audit log.
- Tautan portal Dapodik bila memang dibutuhkan.

Form master data harus konsisten: label, validasi, pesan error, status aktif/nonaktif, dan konfirmasi hapus. Audit log perlu mudah difilter dan, bila datanya tersedia, menampilkan pelaku, waktu, IP, tindakan, serta perubahan nilai lama/baru.

## 8. Form dan Validasi

- Kelompokkan input berdasarkan konteks, jangan menampilkan form panjang sebagai satu blok tanpa pembagian.
- Label harus tetap terlihat; jangan mengandalkan placeholder sebagai label.
- Tandai field wajib.
- Pesan validasi harus spesifik dan dekat dengan field terkait.
- Pertahankan nilai input ketika validasi gagal.
- Gunakan select/autocomplete untuk data master yang sudah tersedia.
- Untuk tanggal, gunakan format yang konsisten dengan aplikasi dan kebutuhan operasional Indonesia.
- Untuk angka, tampilkan pemisah ribuan secara konsisten, tetapi kirim nilai numerik yang benar ke backend.
- Untuk upload foto/dokumen, tampilkan format dan batas ukuran yang benar-benar diberlakukan backend.
- Tampilkan preview file, progress/loading, kegagalan unggah, dan kemampuan mencoba ulang jika didukung.
- Form create/edit menggunakan pola layout dan istilah yang sama.
- Hindari modal untuk form sangat panjang; gunakan halaman detail atau halaman form khusus.

## 9. Status, Notifikasi, dan Feedback

Gunakan pola status yang konsisten di seluruh aplikasi:
- **Menunggu**: warna warning.
- **Disetujui/Selesai**: warna success.
- **Ditolak/Dibatalkan**: warna danger.
- **Berjalan/Aktif**: warna info/primary.
- **Draft**: warna netral.

Sesuaikan istilah dengan status yang benar-benar tersedia dalam database. Jangan menambah status baru hanya demi tampilan.

Untuk setiap aksi:
- Beri loading state saat proses berjalan.
- Cegah double submit jika relevan.
- Tampilkan feedback sukses/gagal.
- Jelaskan dampak aksi penting, khususnya persetujuan MRV, pembatalan FJP, penghapusan data, dan pergantian role.
- Gunakan dialog konfirmasi untuk tindakan yang tidak mudah dibatalkan.

## 10. Komponen UI yang Perlu Konsisten

Buat atau rapikan komponen reusable sesuai pola project:
- App shell, sidebar, topbar, breadcrumb.
- Page header dan primary action.
- Stat card.
- Status badge.
- Filter bar dan search field.
- Data table, pagination, empty state.
- Form field, select, date picker, textarea.
- File uploader dan image preview.
- Confirmation dialog.
- Toast/alert.
- Loading skeleton.
- Error state.
- Detail panel/drawer.
- Chart container.
- Role switcher.

Jangan membuat komponen duplikat untuk fungsi yang sama jika komponen reusable sudah tersedia.

## 11. Aksesibilitas dan Responsivitas

- Kontras teks dan latar harus memadai.
- Semua kontrol dapat digunakan dengan keyboard.
- Input memiliki label yang terhubung.
- Fokus keyboard terlihat.
- Tombol ikon memiliki accessible label.
- Jangan menyampaikan status hanya melalui warna.
- Tabel dan grafik harus memiliki konteks teks yang dapat dipahami.
- Target sentuh di mobile cukup besar.
- Periksa layout pada lebar desktop, tablet, dan ponsel.
- Hindari overflow horizontal yang tidak disengaja.

## 12. Integrasi dengan Project Laravel

Project saat ini menggunakan **Laravel** dengan antarmuka **Inertia React** sesuai struktur implementasi yang sudah ada. Database dan lingkungan lokal menggunakan XAMPP/MySQL atau MariaDB sesuai konfigurasi project.

Aturan implementasi:
1. **Audit project terlebih dahulu** sebelum mengubah kode. Periksa route, controller, model, migration, middleware, policy/gate, komponen React, dan CSS yang sudah ada.
2. Pertahankan stack, struktur folder, routing, autentikasi, serta library UI yang sudah dipakai. Jangan memigrasikan framework.
3. Periksa file `package.json` dan dependensi sebelum menambah library.
4. Ikuti pola styling yang sudah ada. Jika Tailwind sudah digunakan, lanjutkan dengan pola tersebut.
5. Ambil data dari backend melalui pola Laravel/Inertia yang sudah ada.
6. Jangan menaruh data sensitif atau aturan otorisasi hanya di frontend.
7. RBAC harus ditegakkan pada route/controller/policy/middleware backend, bukan hanya dengan menyembunyikan menu.
8. Jangan mengganti nama kolom, tabel, route, atau status database hanya untuk menyamakan desain.
9. Jangan menghapus fitur yang sedang berjalan.
10. Jangan menggunakan mock data sebagai pengganti integrasi backend. Mock hanya boleh dipakai pada story/demo terpisah dan harus diberi label.
11. Hindari menulis ulang seluruh aplikasi jika perubahan komponen/layout sudah cukup.
12. Pastikan build, lint/test yang tersedia, dan alur utama tetap berfungsi setelah perubahan.

## 13. Referensi Visual dan Cara Mengadaptasi

Referensi: [Ticket Booking Dashboard — Dribbble](https://dribbble.com/shots/24948248-Ticket-Booking-Dashboard)

Ambil prinsip visual yang relevan:
- Hierarki dashboard yang jelas.
- Kartu ringkasan dengan angka utama yang mudah dibaca.
- Pengelompokan data yang rapi.
- Navigasi dashboard yang konsisten.
- Penggunaan tabel, filter, grafik, dan status yang terstruktur.
- Jarak antar elemen dan keseimbangan ruang.

Jangan mengambil konteks domain ticket booking secara literal. Ganti konsepnya menjadi:
- Booking/ticket list → daftar FJP/event.
- Ticket status → status FJP, event, MRV, atau laporan.
- Booking analytics → ringkasan event dan target/realisasi.
- Customer/attendee → personel, tim, atau data lokasi sesuai modul.
- Payment/order metrics → metrik operasional yang benar-benar relevan.

Gunakan referensi sebagai inspirasi, bukan sebagai sumber kebenaran untuk proses bisnis PT Indosakti Sejati.

## 14. Acceptance Criteria / Checklist Selesai

Desain dinyatakan selesai jika:

- [ ] Semua halaman utama menggunakan app shell dan pola layout yang konsisten.
- [ ] Sidebar/topbar menampilkan role aktif yang benar.
- [ ] Pergantian role memperbarui workspace dan menu dengan benar.
- [ ] Menu dan aksi dibatasi berdasarkan izin backend.
- [ ] Dashboard menampilkan metrik yang berasal dari data nyata.
- [ ] Setiap halaman tabel memiliki loading, empty, error, dan pagination state yang layak.
- [ ] Filter, pencarian, dan aksi tabel tetap berfungsi.
- [ ] Form memiliki label, validasi, dan feedback yang jelas.
- [ ] Status memakai label dan warna konsisten.
- [ ] Tampilan responsif di desktop, tablet, dan mobile.
- [ ] Upload foto/dokumen memberi feedback yang jelas.
- [ ] Tampilan data FJP menunjukkan jumlah siswa versi sekolah, versi Dapodik, dan selisih jika field tersedia.
- [ ] Alur MRV dan perubahan stok tetap konsisten dengan backend.
- [ ] Tidak ada tombol dekoratif yang terlihat berfungsi tetapi tidak melakukan aksi.
- [ ] Tidak ada data dummy yang ditampilkan sebagai data nyata.
- [ ] Tidak ada business logic, route, atau permission yang rusak akibat perubahan UI.
- [ ] Build dan test yang tersedia berhasil, atau masalah yang sudah ada didokumentasikan.

## 15. Instruksi untuk AI Coding Agent

Baca file ini sebelum mulai mengerjakan UI/UX.

Urutan kerja:
1. Audit struktur project dan catat framework, library UI, routing, role, dan halaman yang sudah ada.
2. Petakan halaman yang ada ke modul dan role di dokumen ini.
3. Identifikasi masalah visual dan inkonsistensi tanpa langsung mengubah business logic.
4. Susun rencana revisi singkat per layout/halaman.
5. Implementasikan secara bertahap dengan komponen reusable dan tetap mengikuti arsitektur project.
6. Hubungkan semua metrik dan tabel dengan data backend yang benar.
7. Uji semua role, pergantian role, validasi form, filter, upload, serta alur FJP → PIC → Tim/Warehouse → Kitchen → Admin per Event.
8. Perbaiki regression yang timbul akibat perubahan.
9. Laporkan file yang diubah, halaman yang diperbaiki, test yang dijalankan, dan hal yang belum dapat diselesaikan.

**Batasan penting:** Tugas ini adalah menyempurnakan desain dan usability sistem operasional yang sudah ada, bukan membuat aplikasi ticket booking baru dan bukan membangun ulang aplikasi dari nol.
