from pathlib import Path

content = r"""# Revisi Sistem Operasional PT Indosakti Sejati
## Dokumen Kebutuhan Client dan Prompt Implementasi untuk AI Coding Agent

**Nama sistem:** PT Indosakti Sejati — Integrated Event & Activity Management System  
**Tujuan dokumen:** Menjadi acuan revisi sistem KKP berdasarkan diskusi client, kondisi project saat ini, dan referensi tampilan pada `Tampilan.zip`.  
**Teknologi project yang teridentifikasi:** Laravel 13.36, PHP 8.4, Inertia v3, React 19, TypeScript, Tailwind CSS v4, Ziggy, dan database relasional yang digunakan project saat ini.  
**Bahasa antarmuka:** Bahasa Indonesia.  
**Status:** Spesifikasi kebutuhan dan instruksi kerja agent; bukan pernyataan bahwa semua fitur sudah tersedia.

---

## 1. Instruksi Utama untuk AI Coding Agent

Anda bertindak sebagai senior Laravel engineer, system analyst, dan UI/UX engineer yang melanjutkan **project yang sudah ada**, bukan membuat aplikasi baru dari nol.

Lakukan audit kode aktual terlebih dahulu, lalu implementasikan revisi secara bertahap. Jangan mengasumsikan fitur sudah tersedia hanya karena route, controller, atau halaman dengan nama serupa ditemukan. Telusuri alur lengkap dari UI → route → middleware/permission → controller → service/model → database → respons UI.

### Aturan wajib
1. **Jangan menghapus atau mengganti arsitektur project secara besar-besaran.** Pertahankan Laravel + Inertia + React + TypeScript + Tailwind yang sudah digunakan, kecuali ada alasan teknis yang kuat dan dijelaskan terlebih dahulu.
2. Sebelum mengubah kode, periksa `routes/web.php`, `routes/auth.php`, `bootstrap/app.php`, `app/Http/Controllers`, `app/Models`, `app/Enums`, `app/Services`, `app/Support`, migration, seeder, `resources/js/Pages`, `resources/js/Components`, dan `tests`.
3. Buat rencana perubahan dan daftar file yang akan disentuh. Hindari refactor yang tidak berkaitan dengan kebutuhan client.
4. Jangan membuat halaman dummy atau tombol yang hanya terlihat aktif. Setiap fitur harus terhubung ke validasi, database, otorisasi, status workflow, dan umpan balik UI yang nyata.
5. Jangan membuat tabel/model/route duplikat apabila entitas terkait sudah tersedia. Gunakan dan perluas entitas yang ada setelah memverifikasi struktur serta relasinya.
6. Pertahankan data yang sudah ada. Migration baru harus aman, reversible jika memungkinkan, dan tidak menghapus data produksi.
7. Jangan menampilkan atau mencatat password, token, isi `.env`, atau data sensitif. Jangan mengubah kredensial tanpa instruksi.
8. Jangan menganggap integrasi Dapodik selalu tersedia. Sediakan penanganan gagal, status sinkronisasi, dan input manual yang jelas.
9. Terapkan permission server-side. Menyembunyikan tombol di frontend saja bukan pengamanan.
10. Setelah setiap kelompok perubahan, jalankan test relevan, pemeriksaan TypeScript/build, dan verifikasi route. Laporkan test yang gagal; jangan mengklaim lulus jika belum dijalankan.
11. Jangan melakukan deploy, mengirim data ke layanan eksternal, atau menjalankan operasi destruktif tanpa persetujuan.
12. Bila kebutuhan bisnis masih ambigu, gunakan perilaku yang paling aman dan dokumentasikan asumsi. Jangan mengarang aturan gaji, target, atau approval yang belum disepakati.

---

## 2. Konteks dan Tujuan Bisnis

PT Indosakti Sejati membutuhkan sistem operasional untuk mengelola event/aktivasi dari tahap perencanaan dan perizinan, pembagian tim, permintaan barang, persiapan kitchen bila diperlukan, pelaksanaan di lapangan, hingga verifikasi laporan oleh Admin per Event.

Masalah yang hendak dikurangi:
- Informasi rencana event, status sekolah/lokasi, pembagian tim, kebutuhan barang, serta laporan tersebar dan sulit dipantau.
- Status event yang dibatalkan atau ditolak harus disertai alasan yang dapat ditelusuri.
- Data jumlah siswa perlu dapat dibandingkan antara input sekolah dan Dapodik jika integrasi tersedia.
- Target per shift, target cups per kuartal, dan realisasi event harus dapat dipantau.
- Permintaan dan pergerakan stok warehouse perlu memiliki jejak audit.
- Foto bukti pelaksanaan, foto barang masuk/keluar, foto proses kitchen, dan surat izin perlu tersimpan serta hanya dapat diakses oleh pihak berwenang.
- Rekap shift tim perlu membantu proses rekapitulasi payroll, tetapi nilai gaji harus mengikuti aturan resmi perusahaan dan tidak boleh dimanipulasi melalui input waktu sembarangan.

### Prinsip utama
- **FJP adalah perencanaan/permohonan kegiatan dan izin.**
- **Event adalah pelaksanaan operasional yang dibuat dari FJP yang telah disetujui**, sesuai workflow project saat ini.
- Status, target, penugasan, stok, bukti foto, dan laporan harus terhubung pada event yang tepat.
- Sistem harus mendukung event yang membutuhkan jug/kitchen maupun event yang tidak membutuhkan jug/kitchen.

---

## 3. Alur Operasional yang Diminta Client

Alur tingkat tinggi yang disebutkan client:

**Permitter → PIC → Tim dan Warehouse → Kitchen → Admin per Event**

Implementasikan alur ini dengan tetap memperhatikan dependensi operasional yang nyata. Warehouse dan Kitchen dapat berjalan sebagai proses paralel/bersyarat setelah PIC menyiapkan event, bukan memaksa semua event melewati kitchen.

### Alur yang direkomendasikan untuk diverifikasi terhadap sistem sekarang
1. **Permitter**
   - Membuat FJP untuk event yang direncanakan.
   - Mengisi data sekolah/lokasi, tanggal, shift, target, produk/brand, dan informasi yang memang diperlukan.
   - Menyimpan FJP sebagai draft, mengajukan persetujuan, atau mencatat penolakan/pembatalan dengan alasan.
   - Jika disetujui, FJP dapat menjadi dasar pembuatan Event.
   - Surat izin dapat diunggah sebagai lampiran dan diteruskan secara aman ke area Admin per Event.

2. **PIC**
   - Melihat event yang menjadi tanggung jawabnya.
   - Mengatur pembagian personel/tim pada event.
   - Mengatur jadwal dan shift, target/realisasi event, serta kebutuhan material.
   - Membuat MRV (Material Requisition Voucher) untuk Warehouse.
   - Memantau kesiapan izin, tim, barang, dan kitchen bila dibutuhkan.
   - Mengirim laporan event kepada Admin per Event.

3. **Warehouse**
   - Menerima MRV dari PIC.
   - Memeriksa permintaan dan ketersediaan stok.
   - Menyetujui/menolak atau meminta revisi sesuai kewenangan yang berlaku.
   - Mencatat barang keluar saat barang benar-benar diserahkan; persetujuan MRV saja tidak boleh mengurangi stok.
   - Mencatat penerimaan barang masuk, foto bukti, barang keluar, foto bukti, serta saldo stok.
   - Menyediakan rekap mutasi dan stok tersedia.

4. **Kitchen**
   - Melihat jadwal/event yang memerlukan persiapan kitchen.
   - Mencatat tahapan persiapan produk/jug.
   - Mengunggah foto proses masak/persiapan dan foto cuci jug sesuai proses aktual.
   - Memperbarui status kesiapan dan menyerahkan hasil sesuai alur yang berlaku.
   - Jika event tidak membutuhkan jug/kitchen, modul ini tidak boleh menghambat event.

5. **Tim lapangan**
   - Melihat jadwal event dan shift yang ditugaskan PIC.
   - Mengisi kehadiran/check-in/check-out sesuai kebijakan perusahaan.
   - Mengirim Quick Report dan laporan aktivitas event dengan foto bukti.
   - Laporan harus terhubung ke event dan tanggal/shift yang benar.

6. **Admin per Event**
   - Melihat event yang direncanakan dan event yang sudah berjalan.
   - Menerima laporan yang dikirim PIC/tim sesuai alur yang diputuskan perusahaan.
   - Memeriksa data, foto, dokumen, target/realisasi, dan kelengkapan laporan.
   - Memverifikasi atau mengembalikan laporan untuk revisi dengan catatan yang jelas.
   - Menyelesaikan proses event sesuai aturan penutupan yang berlaku.

**Catatan:** Client menyebut urutan “Permitter → PIC → Tim dan gudang/warehouse → Kitchen → Admin per event”. Pastikan UI menampilkan alur ini secara mudah dipahami, namun jangan membuat dependency yang tidak masuk akal. Misalnya, kitchen hanya wajib ketika `jug_required`/kebutuhan sejenis aktif.

---

## 4. Kebutuhan Fitur per Role

### 4.1 Permitter

#### A. FJP / perencanaan sekolah atau lokasi
- Menampilkan daftar FJP dengan pencarian, filter tanggal, status, lokasi/sekolah, brand, dan PIC bila relevan.
- Membuat, melihat, dan mengedit FJP sesuai status dan permission.
- FJP mencakup rencana yang telah disetujui atau masih direncanakan; status harus dibedakan secara eksplisit.
- Status FJP/izin harus dapat menunjukkan setidaknya kondisi draft, diajukan, disetujui, ditolak, dibatalkan, atau status setara yang sudah dipakai project.
- Jika event/sekolah ditolak atau dibatalkan, alasan wajib dicatat dan tampil pada detail serta riwayat aktivitas.
- Perubahan status penting harus memiliki waktu dan aktor yang tercatat.
- Hindari membolehkan submit ulang atau membatalkan persetujuan secara bebas jika transisi tersebut tidak diizinkan workflow.

#### B. Data sekolah dan Dapodik
- Gunakan integrasi Dapodik yang sudah ada bila tersedia dan valid.
- Tampilkan dengan jelas **jumlah siswa versi sekolah/manual** dan **jumlah siswa versi Dapodik** sebagai dua sumber data berbeda.
- Simpan sumber data, waktu sinkronisasi, status berhasil/gagal, dan pesan kegagalan yang aman.
- Tombol sinkronisasi tidak boleh menimpa nilai manual secara diam-diam.
- Sediakan input manual/fallback saat Dapodik tidak tersedia, dengan label sumber data.
- Tampilkan selisih dua nilai jika keduanya tersedia.
- Jangan mengklaim integrasi real-time jika sebenarnya berupa permintaan sinkronisasi berkala/manual.
- Jangan melakukan scraping atau melewati autentikasi/pembatasan Dapodik. Gunakan API resmi dan konfigurasi server yang aman.

#### C. Target
- Simpan target per shift untuk event yang relevan.
- Dukung target cups per kuartal/quarter sesuai definisi bisnis perusahaan.
- Tampilkan satuan target secara eksplisit (misalnya cups, bukan angka tanpa satuan).
- Bedakan target rencana dengan realisasi aktual.
- Jangan menjumlahkan target event dan target kuartal secara keliru atau menggandakan nilai ketika event memiliki beberapa shift.

#### D. Surat izin
- Upload surat izin sebagai file lampiran jika diperlukan.
- Validasi tipe file, ukuran maksimum, dan nama file.
- Simpan dokumen legal pada storage privat, bukan URL publik yang dapat diakses tanpa login.
- Admin per Event dapat melihat/mengunduh hanya jika memiliki permission dan scope yang sesuai.
- Tampilkan nama file, tanggal upload, pengunggah, dan status dokumen.

### 4.2 PIC

#### A. Pembagian tim
- PIC dapat menetapkan anggota/personel yang akan menjalankan event dari data personel/user yang tersedia.
- Daftar personel pada FJP yang dimasukkan manual oleh Permitter perlu ditampilkan sebagai informasi rencana; PIC dapat melakukan penugasan aktual sesuai permission.
- Tampilkan peran/posisi setiap anggota, event, shift, dan status penugasan.
- Cegah penugasan duplikat yang tidak diperbolehkan.
- PIC tidak boleh mengubah status, anggota, atau data event milik PIC lain tanpa permission eksplisit.
- Semua perubahan penugasan harus tercatat dalam audit log.

#### B. Jadwal seluruh event
- Sediakan tampilan daftar dan kalender/jadwal event yang relevan.
- Filter berdasarkan tanggal/periode, status, lokasi, brand, tim, dan shift.
- PIC hanya melihat/mengelola event sesuai scope aksesnya; role lain mengikuti permission masing-masing.
- Bedakan event terencana, berjalan, tertunda, dibatalkan, dan selesai.

#### C. Laporan event harian
- PIC dapat melihat event yang berjalan pada hari tersebut dan kelengkapan laporan tim.
- Tampilkan target, realisasi, jumlah cups bila berlaku, status laporan, bukti foto, dan catatan.
- Laporan yang belum lengkap harus mudah dikenali.
- Sediakan ringkasan harian dan riwayat per event.

#### D. MRV untuk Warehouse
- PIC membuat MRV yang terhubung ke event.
- Item MRV mencakup produk/barang, jumlah, satuan, dan catatan bila diperlukan.
- Validasi stok dan aturan jumlah saat permintaan diproses.
- Tampilkan status permintaan: diajukan, disetujui, ditolak/perlu revisi, dikeluarkan, dan status pengembalian bila tersedia pada sistem.
- Approval MRV tidak otomatis memotong stok; stok berkurang saat barang benar-benar dikeluarkan.
- PIC dapat melihat riwayat MRV milik event yang diizinkan.

#### E. Form shift dan rekap payroll
- Jadwal/shift harus dapat direkap otomatis berdasarkan data kehadiran yang valid.
- Simpan waktu check-in/check-out, event, personel, shift, status validasi, dan sumber koreksi jika ada.
- Buat ringkasan jumlah shift yang memenuhi syarat untuk proses payroll.
- Aturan durasi minimum, toleransi keterlambatan, lembur, rate, pembulatan, dan persetujuan koreksi harus mengikuti kebijakan perusahaan yang dikonfirmasi; jangan mengarang angka.
- PIC tidak boleh mengubah data kehadiran tanpa jejak audit.
- Cegah check-in/check-out tidak wajar, check-out sebelum check-in, shift duplikat, dan durasi nol/tidak masuk akal.
- Sediakan koreksi oleh role berwenang dengan alasan dan audit trail.

#### F. Summary/target per event
- Tampilkan target dan realisasi per event, per shift, dan bila diperlukan per kuartal.
- Tampilkan progres dan selisih dengan satuan yang konsisten.
- Jika data realisasi belum tersedia, tampilkan “Belum ada data”, bukan angka nol yang menyesatkan.
- Jelaskan sumber data dan waktu pembaruan jika informasi berasal dari modul lain.

### 4.3 Tim Lapangan

- Melihat jadwal yang diberikan PIC, termasuk tanggal, lokasi, shift, anggota/tim, dan informasi operasional yang memang dibutuhkan.
- Tidak dapat mengakses event yang tidak ditugaskan kecuali diberi permission.
- Membuat laporan aktivitas pada event dan tanggal yang benar.
- Upload foto aktivitas sebagai bukti; dukung beberapa foto bila diperlukan.
- Quick Report harus sederhana untuk penggunaan di lapangan, responsif di ponsel, dan memberi konfirmasi setelah tersimpan.
- Validasi ukuran dan tipe file; gunakan penyimpanan privat serta kontrol akses.
- Tampilkan status draft/terkirim/revisi/terverifikasi sesuai workflow.
- Hindari meminta tim memasukkan ulang informasi yang sudah ada pada event jika dapat ditampilkan otomatis.
- Jika koneksi gagal, tampilkan pesan kegagalan yang jelas; jangan memberi kesan laporan tersimpan jika transaksi gagal.

### 4.4 Warehouse / Gudang

#### A. MRV masuk
- Daftar permintaan dari PIC dengan filter status, event, tanggal, dan pemohon.
- Detail MRV memperlihatkan item, kuantitas, satuan, stok tersedia, serta catatan.
- Aksi approve/reject/issue dibatasi permission yang sesuai.
- Penolakan atau permintaan revisi harus memiliki alasan.
- Perubahan status dan pergerakan stok dicatat pada audit trail.

#### B. Barang masuk
- Form penerimaan barang dengan produk, jumlah, satuan, tanggal, sumber/pemasok/referensi bila tersedia, catatan, dan foto bukti.
- Setelah penerimaan sah disimpan, stok dan ledger mutasi diperbarui secara transaksional.
- Cegah penerimaan ganda dari submit ulang.
- Foto barang masuk terhubung dengan transaksi penerimaan yang benar.

#### C. Barang keluar
- Barang keluar dapat berasal dari MRV yang sah atau transaksi lain yang memang diizinkan.
- Catat item, jumlah, satuan, penerima, event/MRV terkait, tanggal, catatan, dan foto bukti barang keluar.
- Pengurangan stok harus atomik dan konsisten; tidak boleh menyebabkan stok negatif.
- Cegah transaksi issue ganda akibat klik ganda/request bersamaan dengan locking/idempotency yang sesuai.
- Persetujuan MRV tidak sama dengan pengeluaran barang.

#### D. Stok dan laporan
- Ringkasan stok tersedia per produk/kategori/satuan.
- Tampilkan stok awal, barang masuk, barang keluar, pengembalian layak pakai, kerusakan, dan saldo saat ini bila datanya tersedia.
- Sediakan riwayat mutasi yang dapat ditelusuri ke transaksi sumber.
- Barang rusak tidak boleh ditambahkan sebagai stok layak pakai.
- Sediakan filter dan ekspor jika fitur ekspor sudah ada atau memang disepakati sebagai tambahan.
- Foto transaksi hanya dapat diakses oleh pengguna berwenang.

### 4.5 Kitchen / Dapur

- Menampilkan jadwal/event yang relevan bagi Kitchen.
- Hanya event dengan kebutuhan kitchen/jug yang wajib menjalani proses ini.
- Menampilkan detail kebutuhan, tanggal, shift, dan status persiapan.
- Mendukung upload foto proses masak/persiapan produk.
- Mendukung upload foto cuci jug.
- Catat pengunggah, waktu, event, jenis bukti, dan catatan.
- Status persiapan harus mengikuti transisi yang valid dan tidak boleh dilompati tanpa izin.
- Tampilkan apakah persiapan belum dimulai, sedang diproses, siap, diserahkan, atau status setara yang sudah ada di aplikasi.
- Jika event tidak memerlukan kitchen, tampilkan “Tidak diperlukan” dan jangan memblokir penyelesaian event.

### 4.6 Admin per Event

- Menjadi area pemeriksaan akhir untuk event terencana dan event yang telah berjalan.
- Menampilkan daftar laporan yang dikirim PIC/tim, status verifikasi, tanggal event, lokasi, target/realisasi, lampiran foto, surat izin, MRV, dan status kitchen jika relevan.
- Menyediakan detail laporan per event dengan informasi yang terkelompok dan mudah diperiksa.
- Aksi verifikasi dan permintaan revisi hanya untuk role yang berwenang.
- Permintaan revisi wajib berisi catatan yang spesifik dan ditampilkan kepada pengirim.
- Menampilkan riwayat status, aktor, waktu, dan catatan perubahan.
- Jangan mengizinkan event selesai jika persyaratan wajib belum terpenuhi; aturan pengecualian harus eksplisit dan diaudit.
- Jangan mencampur peran Admin per Event dengan System Admin jika kewenangannya berbeda.

### 4.7 System Admin

- Mengelola akun, role/permission, personel, master data, konfigurasi, target kuartal, dan audit log sesuai fungsi yang sudah ada.
- Pembuatan akun dilakukan oleh admin; registrasi publik tetap nonaktif kecuali ada keputusan bisnis baru.
- Admin dapat menonaktifkan akun dan tindakan ini harus benar-benar memblokir login baru serta menangani sesi aktif sesuai kebijakan.
- Semua perubahan penting pada akun, permission, master data, target, dan transaksi sensitif perlu tercatat.
- Hak akses administrator harus dibatasi dan tidak otomatis diberikan kepada semua role operasional.

---

## 5. Aturan Workflow dan Status

Gunakan enum/state machine yang sudah ada jika sesuai. Jangan menambah status duplikat dengan makna yang sama.

### FJP dan perizinan
- Draft → diajukan → disetujui atau ditolak.
- Pembatalan harus mengikuti aturan status yang valid.
- Status ditolak/dibatalkan wajib menyimpan alasan.
- Transisi dari status terminal seperti dibatalkan tidak boleh kembali ke diajukan secara bebas; jika perusahaan memerlukan pengajuan ulang, definisikan aksi dan riwayat yang jelas.

### Event
- Event dibuat dari FJP yang telah disetujui sesuai aturan project saat ini.
- Penugasan tim, kesiapan izin, kesiapan warehouse, dan kitchen bila diperlukan harus dapat dipantau.
- Event hanya dapat ditutup setelah seluruh persyaratan wajib terpenuhi atau pengecualian resmi disetujui dan tercatat.
- Hindari perubahan status oleh PIC terhadap event milik PIC lain.

### MRV dan stok
- Status MRV harus terpisah jelas dari status pergerakan stok.
- Approve tidak memotong stok.
- Issue/pengeluaran mengurangi stok dalam transaksi database dan hanya sekali.
- Return barang baik menambah stok; barang rusak dicatat sebagai rusak dan tidak menjadi stok tersedia.
- Operasi bersamaan harus aman terhadap double-submit/race condition.

### Laporan
- Draft → submit → verified atau revision sesuai state machine yang berlaku.
- Laporan hanya boleh diubah pemilik/pihak berwenang dan event harus tetap berada dalam scope aksesnya.
- Perubahan event yang dikaitkan pada laporan tidak boleh dipakai untuk memalsukan kelengkapan event lain.
- Revisi harus menyimpan catatan dan riwayat.

### Kehadiran/payroll
- Waktu kehadiran harus valid, terhubung pada penugasan/shift yang sah, dan tidak dapat dimanipulasi tanpa jejak.
- Perhitungan payroll harus berasal dari data yang memenuhi kebijakan resmi.
- Koreksi harus memiliki pelaku, alasan, waktu, nilai sebelum/sesudah bila relevan, dan permission.

---

## 6. Dashboard, Summary, dan Pelaporan

Dashboard harus berbeda sesuai role, bukan menampilkan semua data perusahaan kepada semua pengguna.

### Permitter
- Ringkasan FJP draft/diajukan/disetujui/ditolak/dibatalkan.
- Jadwal mendatang dan data sekolah yang memerlukan tindak lanjut.
- Status sinkronisasi Dapodik dan selisih data siswa bila tersedia.

### PIC
- Event hari ini dan mendatang.
- Kesiapan per event: izin, tim, warehouse, kitchen jika diperlukan, laporan.
- Target versus realisasi per event/shift.
- MRV yang menunggu proses dan laporan yang belum lengkap.

### Tim
- Jadwal/shift saya.
- Event hari ini.
- Quick Report/laporan yang perlu dibuat atau direvisi.

### Warehouse
- Ringkasan stok, item stok rendah jika batas minimum sudah dikonfigurasi, MRV menunggu proses, barang masuk/keluar terbaru.

### Kitchen
- Jadwal persiapan hari ini, event yang membutuhkan kitchen, persiapan tertunda, dan bukti foto yang belum lengkap.

### Admin per Event
- Event yang menunggu verifikasi, laporan revisi, event terencana/berjalan, dan kelengkapan dokumen.

### System Admin
- Ringkasan operasional lintas modul sesuai kewenangan.
- User/role, audit, master data, dan target kuartal.
- Data dashboard harus mengikuti scope permission dan ownership, bukan sekadar menyembunyikan menu.

### Prinsip metrik
- Bedakan “target”, “realisasi”, “sisa”, “persentase”, dan “status”.
- Setiap angka harus memiliki definisi dan sumber data.
- Hindari duplikasi hitung saat event mempunyai beberapa shift atau laporan.
- Filter tanggal/periode harus konsisten antar dashboard dan laporan.

---

## 7. Referensi Tampilan dari `Tampilan.zip`

Arsip referensi berisi screenshot untuk area:
- `Tampilan/Admin/`
- `Tampilan/Permitter/`
- `Tampilan/PIC/`
- `Tampilan/Tim/`
- `Tampilan/Gudang/`
- `Tampilan/Dapur/`

Gunakan screenshot sesuai role sebagai referensi visual utama. Pertahankan pola visual yang sudah ditunjukkan:
- Sidebar kiri berwarna navy/dark dengan logo/nama sistem dan menu berdasarkan role.
- Area konten utama berlatar terang dengan header yang jelas.
- Layout dashboard operasional dengan kartu ringkasan, tabel/list, status badge, form, dan panel detail.
- Warna aksen biru untuk aksi utama dan status berwarna untuk membedakan kondisi.
- Form dibuat berkelompok berdasarkan konteks, bukan satu form panjang tanpa hierarki.
- Tabel menyediakan filter, status, aksi yang relevan, dan detail event.
- Gunakan Bahasa Indonesia yang konsisten; istilah teknis seperti FJP, MRV, Dapodik, Quick Report boleh dipertahankan dan diberi keterangan bila perlu.
- Pertahankan desain yang bersih, profesional, mudah dipakai pada layar desktop, serta responsif untuk tim lapangan menggunakan ponsel.

### Aturan visual
1. Jangan menyalin screenshot sebagai gambar statis. Implementasikan UI asli dengan data aplikasi.
2. Jangan mengubah semua halaman ke desain baru yang tidak terkait. Selaraskan halaman yang ada dengan referensi dan kebutuhan.
3. Konsistenkan sidebar, header, breadcrumb/judul halaman, tombol utama, form field, tabel, dialog, status badge, empty state, loading state, dan pesan error/sukses.
4. Jangan menampilkan menu yang tidak boleh diakses role; route backend tetap harus diamankan.
5. Pada layar mobile, tabel boleh memiliki overflow horizontal atau layout alternatif yang tetap dapat digunakan; form upload foto harus nyaman di ponsel.
6. Sediakan empty state yang menjelaskan tindakan berikutnya, bukan layar kosong.
7. Hindari data dummy statis pada dashboard production. Jika seeder demo digunakan untuk development, beri label dan jangan mencampurkannya dengan data real.
8. Pertahankan komponen UI bersama bila sudah ada; jangan membuat versi berbeda dari tombol, modal, badge, atau input di setiap halaman.

---

## 8. Kondisi Project Saat Ini yang Perlu Dipertimbangkan

Berdasarkan laporan audit project yang disertakan, project saat ini telah memiliki Laravel + Inertia/React, sekitar 55 halaman Inertia, 33 controller, 32 model, 17 migration yang membentuk sekitar 41 tabel, 7 role, 40 permission, 5 service, dan test suite. Angka ini adalah informasi dari laporan audit yang diberikan dan harus diverifikasi ulang terhadap repository saat implementasi dimulai.

Entitas/fitur yang sudah teridentifikasi antara lain:
- FJP dan Event.
- Permit request dan dokumen.
- Penugasan tim/personel.
- MRV, produk, inventory, mutasi stok, penerimaan, dan pengembalian.
- Kitchen request dan laporan kitchen.
- Attendance, payroll service, Quick Report, dan Event Report.
- Dapodik service/controller.
- Target kuartal, audit log, master data, dan pengelolaan user/permission.

**Prioritasnya adalah memperbaiki, menghubungkan, dan melengkapi fitur yang sudah ada terlebih dahulu.** Jangan membuat implementasi kedua dari modul yang sudah tersedia.

---

## 9. Temuan Audit Keamanan yang Harus Diprioritaskan

Laporan audit project sebelumnya menandai beberapa masalah. Verifikasi ulang setiap temuan di kode aktif sebelum mengubahnya, tetapi jangan mengabaikan risiko berikut.

### P0 — Kritis
1. **Dokumen legal disimpan di public storage.** FJP/surat izin/foto/dokumen yang bersifat internal tidak boleh dapat dibuka hanya dengan URL publik. Pindahkan dokumen sensitif ke private disk dan sediakan endpoint download yang memeriksa autentikasi, permission, dan scope objek. Buat migrasi file yang aman, verifikasi hasil, dan jangan menghapus file sumber sebelum sukses.
2. **IDOR pada perubahan event.** Audit sebelumnya menemukan endpoint update status, assign team, dan remove team tidak memeriksa scope `EventAccess`. Pastikan semua operasi baca/tulis sensitif memeriksa ownership/scope server-side.
3. **Akun nonaktif masih dapat login.** Pastikan proses login memeriksa status aktif. Putuskan dan implementasikan kebijakan untuk sesi aktif setelah akun dinonaktifkan; minimal, sesi tidak boleh terus memberi akses yang seharusnya sudah dicabut.

### P1 — Tinggi
4. **Laporan dapat dipindahkan ke event yang tidak berada dalam scope pengguna.** Validasi event harus berdasarkan daftar event yang boleh diakses pengguna, bukan hanya `exists:events,id`.
5. **Double issue MRV/double return.** Kunci record yang relevan di dalam transaksi, validasi ulang status di dalam transaksi, dan buat operasi idempoten agar stok tidak terpotong/bertambah dua kali.
6. **Payroll/attendance tidak memiliki validasi durasi yang memadai.** Terapkan aturan waktu sesuai kebijakan perusahaan yang dikonfirmasi dan cegah manipulasi check-in/check-out.

### P2 — Sedang
7. **Transisi status FJP/permit tidak ketat.** Terapkan state machine yang membatasi transisi invalid, termasuk submit dari status terminal atau pembatalan setelah persetujuan tanpa proses resmi.
8. **Dashboard tidak cukup dibatasi berdasarkan role/scope.** Semua agregasi dan detail harus sesuai permission serta ownership.
9. **Permission pada route download/transition perlu diperiksa.** Cocokkan slug permission, cek scope objek, dan hindari route sensitif tanpa middleware.
10. **Konfigurasi environment.** Verifikasi `APP_DEBUG`, cookie/session security, kredensial seed, dan konfigurasi mail. Jangan menyalin nilai `.env` ke output/log. Untuk production, debug harus dimatikan dan secret tidak boleh memakai nilai default.

### P3 — Performa dan integritas data
11. Kurangi query berulang/N+1 pada pemeriksaan permission dan target kuartal.
12. Perkuat pembuatan nomor FJP agar aman dari race condition.
13. Periksa unique constraint target kuartal ketika `brand_id` nullable.
14. Tinjau index pada tabel laporan dan kolom yang tidak lagi dipakai.
15. Pastikan route debug/log/browser yang tidak diperlukan tidak terbuka tanpa middleware pada environment yang tidak sesuai.

Jangan mengklaim keamanan telah selesai hanya dari perubahan UI. Tambahkan regression test untuk setiap celah akses dan transaksi yang diperbaiki.

---

## 10. Keamanan Upload dan Dokumen

Semua upload harus:
- Memvalidasi MIME type, ekstensi yang diizinkan, dan ukuran file di server.
- Menggunakan nama file yang dibuat server, bukan mempercayai nama/path dari pengguna.
- Disimpan pada private storage jika dokumen internal/legal.
- Memiliki relasi jelas dengan FJP, permit, event, MRV, receipt, return, laporan, atau proses kitchen.
- Memiliki akses download yang memeriksa autentikasi, permission, dan scope data.
- Mencatat uploader, timestamp, dan jenis bukti.
- Tidak mengandalkan `public/storage` untuk dokumen sensitif.
- Tidak memperlihatkan path internal server.
- Memiliki penanganan file gagal/invalid dan rollback yang benar.

Kategori bukti minimal sesuai kebutuhan client:
1. Surat izin FJP/permit.
2. Foto aktivitas tim.
3. Foto barang masuk.
4. Foto barang keluar.
5. Foto persiapan/masak kitchen.
6. Foto cuci jug.
7. Foto pendukung laporan event/Quick Report.

Gunakan struktur model/dokumen yang ada jika sesuai. Jangan membuat satu kolom foto yang tidak dapat membedakan banyak bukti atau jenis bukti.

---

## 11. Data, Validasi, dan Integritas

- Semua input divalidasi server-side dengan pesan Bahasa Indonesia yang jelas.
- Gunakan transaksi database untuk operasi multi-tabel yang harus atomik.
- Hindari mass assignment yang tidak dibatasi.
- Pastikan relasi foreign key dan delete behavior tidak menghilangkan data historis secara tak sengaja.
- Gunakan pagination untuk tabel besar.
- Pastikan timezone konsisten dan tampilan waktu jelas.
- Gunakan soft delete jika diperlukan oleh pola project; jangan menghapus histori event, stok, laporan, atau audit yang dibutuhkan.
- Simpan angka kuantitas dengan tipe yang tepat dan satuan eksplisit.
- Jangan menghitung ulang stok dari tampilan frontend; database/service adalah sumber kebenaran.
- Gunakan ledger mutasi stok sebagai riwayat transaksi yang dapat diaudit.
- Jangan menimpa data sekolah manual ketika sinkronisasi Dapodik dilakukan.
- Hindari duplikasi target atau laporan ketika tombol diklik berkali-kali.
- Perubahan status, izin, tim, MRV, stok, kehadiran, laporan, target, dan akun harus tercatat sesuai tingkat sensitivitas.

---

## 12. Daftar Acceptance Criteria

Fitur dianggap selesai hanya jika kriteria terkait dapat dibuktikan.

### Permitter/FJP
- [ ] FJP dapat dibuat, disimpan, diedit sesuai status, diajukan, disetujui/ditolak, dan dibatalkan hanya melalui transisi yang valid.
- [ ] Penolakan/pembatalan menyimpan alasan dan tampil pada riwayat.
- [ ] Jumlah siswa manual dan Dapodik dibedakan dengan jelas.
- [ ] Kegagalan Dapodik tidak merusak data manual.
- [ ] Target per shift dan target cups per kuartal tampil dengan satuan benar.
- [ ] Surat izin dapat diunggah dan hanya dapat diakses oleh pihak berwenang.

### PIC
- [ ] PIC melihat dan mengelola event sesuai scope.
- [ ] PIC dapat menugaskan tim dan melihat jadwal event.
- [ ] PIC dapat membuat MRV yang terkait ke event.
- [ ] Target/realisasi per event dan shift ditampilkan tanpa double count.
- [ ] Rekap shift berasal dari attendance valid dan tidak dapat dimanipulasi tanpa audit.
- [ ] Laporan event harian dapat diperiksa sebelum dikirim ke Admin per Event.

### Tim
- [ ] Tim hanya melihat penugasan yang diizinkan.
- [ ] Jadwal dan shift dapat dilihat dari tampilan desktop dan mobile.
- [ ] Quick Report/laporan dapat dikirim dengan foto dan terkait ke event yang benar.
- [ ] Status laporan dan pesan error/sukses akurat.

### Warehouse
- [ ] MRV dapat diterima dan diproses sesuai permission.
- [ ] Approve MRV tidak memotong stok.
- [ ] Issue memotong stok sekali dan menolak stok negatif.
- [ ] Barang masuk menambah stok sekali setelah transaksi sah.
- [ ] Return barang baik dan rusak diproses secara berbeda.
- [ ] Foto bukti barang masuk/keluar terhubung dengan transaksi.
- [ ] Laporan saldo dan mutasi dapat ditelusuri.

### Kitchen
- [ ] Kitchen melihat jadwal yang relevan.
- [ ] Foto masak/persiapan dan foto cuci jug dapat diunggah dan ditelusuri.
- [ ] Status mengikuti transisi valid.
- [ ] Event tanpa kebutuhan kitchen tidak terblokir oleh modul ini.

### Admin per Event
- [ ] Admin melihat laporan terencana/berjalan/selesai sesuai permission.
- [ ] Admin dapat memverifikasi atau meminta revisi dengan catatan.
- [ ] Foto, surat izin, status stok, status kitchen, dan target/realisasi ditampilkan sesuai relevansi.
- [ ] Event tidak dapat ditutup saat persyaratan wajib belum terpenuhi kecuali ada pengecualian resmi yang diaudit.

### Keamanan dan kualitas
- [ ] Pengguna tanpa permission tidak dapat mengakses endpoint langsung.
- [ ] PIC tidak dapat memodifikasi event milik PIC lain.
- [ ] Pengguna nonaktif tidak dapat login/melanjutkan akses yang dicabut.
- [ ] Dokumen privat tidak dapat diakses melalui URL publik.
- [ ] Race/double-submit tidak menggandakan mutasi stok atau laporan.
- [ ] Test terkait lulus; hasil build/type-check dicatat.
- [ ] Tidak ada secret yang ditampilkan dalam log, output, atau dokumentasi.

---

## 13. Rencana Implementasi Bertahap

### Tahap 0 — Baseline dan backup
1. Periksa status Git dan perubahan lokal sebelum mengedit.
2. Pastikan ada backup database/file yang sesuai.
3. Jalankan test yang tersedia dan simpan hasil baseline.
4. Catat route, permission, model, migration, serta halaman terkait tiap kebutuhan.
5. Jangan reset database atau menjalankan migration destruktif.

### Tahap 1 — Keamanan kritis
1. Perbaiki akses dokumen privat.
2. Perbaiki object-level authorization/IDOR.
3. Perbaiki login dan sesi akun nonaktif.
4. Perbaiki scope laporan.
5. Tambahkan test regresi untuk setiap perbaikan.

### Tahap 2 — FJP, Permit, Dapodik, dan target
1. Cocokkan form FJP dengan kebutuhan client.
2. Pastikan alasan penolakan/pembatalan dan riwayat status.
3. Perjelas jumlah siswa manual versus Dapodik.
4. Verifikasi target per shift dan target cups per kuartal.
5. Uji lampiran surat izin dan akses Admin per Event.

### Tahap 3 — PIC, jadwal, tim, attendance, payroll
1. Verifikasi assignment dan scope PIC.
2. Lengkapi jadwal/kalender event.
3. Hubungkan shift dengan attendance dan rekap payroll.
4. Terapkan validasi waktu dan audit koreksi.
5. Lengkapi summary target/realisasi per event.

### Tahap 4 — Warehouse/MRV
1. Verifikasi alur request → approval → issue → return.
2. Perbaiki race condition dan idempotensi.
3. Lengkapi penerimaan barang masuk/keluar dan bukti foto.
4. Verifikasi saldo stok, ledger, barang rusak, dan laporan.

### Tahap 5 — Kitchen dan laporan lapangan
1. Pastikan kitchen hanya wajib untuk event yang memerlukannya.
2. Lengkapi foto masak/persiapan dan cuci jug.
3. Lengkapi Quick Report dan laporan tim.
4. Verifikasi submit, revision, dan status laporan.

### Tahap 6 — Admin per Event dan dashboard
1. Lengkapi inbox laporan dan verifikasi/revisi.
2. Tampilkan kelengkapan event dalam satu halaman detail.
3. Batasi dashboard berdasarkan role/scope.
4. Pastikan metrik tidak double count.

### Tahap 7 — UI/UX dan regression
1. Selaraskan semua area dengan screenshot pada `Tampilan.zip`.
2. Uji responsif desktop dan mobile.
3. Periksa loading, empty state, error, success, konfirmasi, dan validasi.
4. Jalankan test, lint/type-check/build yang tersedia.
5. Buat laporan hasil: fitur selesai, file berubah, migration baru, test, risiko tersisa, dan langkah manual.

Urutan dapat disesuaikan jika hasil audit repository menunjukkan dependensi lain, tetapi keamanan kritis tidak boleh ditunda demi kosmetik UI.

---

## 14. Perintah Pemeriksaan yang Disarankan untuk Agent

Sebelum mengubah kode, jalankan perintah yang sesuai dengan environment project dan laporkan hasilnya:

```bash
php artisan about
php artisan route:list
php artisan test
npm run
```

Kemudian jalankan script yang benar-benar tersedia pada `package.json`, misalnya build, lint, atau type-check. Jangan mengasumsikan nama script jika belum diperiksa.

Gunakan pencarian kode untuk menelusuri:
- `EventAccess`, `ReportAccess`, `EnsurePermission`, `hasPermission`
- `FjpController`, `EventController`, `PermitController`, `ReportController`
- `MrvController`, `StockService`, `StockReturnController`
- `AttendanceController`, `PayrollService`, `KitchenController`
- `DocumentController`, konfigurasi filesystem dan endpoint download
- `DapoApiService`, `DapodikController`
- `QuarterlyTargetController`, dashboard controller, dan seeder role/permission

Jangan menampilkan isi `.env` atau secret saat melakukan pemeriksaan.

---

## 15. Format Laporan Agent Setelah Bekerja

Setelah setiap tahap, laporkan dalam format berikut:

1. **Temuan awal:** kondisi aktual dan file yang telah diverifikasi.
2. **Kebutuhan client yang dicakup:** fitur mana yang dikerjakan.
3. **Perubahan kode:** file dan tujuan perubahan.
4. **Database/migration:** perubahan skema dan dampaknya.
5. **Permission dan keamanan:** akses yang ditambah/diperketat.
6. **Test:** perintah yang dijalankan dan hasil sebenarnya.
7. **Verifikasi UI:** halaman/role yang diperiksa.
8. **Belum selesai:** kebutuhan yang masih tertunda dan alasannya.
9. **Risiko/asumsi:** hal yang perlu dikonfirmasi kepada client.
10. **Cara uji manual:** langkah singkat untuk memverifikasi fitur di localhost.

Jangan hanya memberi ringkasan atau mockup. Implementasikan kode yang benar-benar terhubung ke sistem, tetapi kerjakan bertahap agar mudah ditinjau dan tidak merusak fitur lain.

---

## 16. Pertanyaan Bisnis yang Perlu Dikonfirmasi

Jangan mengarang jawaban untuk pertanyaan berikut. Implementasikan hanya setelah aturan yang diperlukan dikonfirmasi atau gunakan perilaku konservatif yang telah dijelaskan sebagai asumsi.

1. Apakah istilah resmi role adalah **Permitter**, **Permiter**, atau **PIC Permitter**?
2. Siapa yang menyetujui FJP/surat izin dan siapa yang boleh menolak atau membatalkannya?
3. Apakah alasan penolakan/pembatalan wajib pada semua status, dan siapa yang boleh mengubah keputusan?
4. Apa definisi “cups per kuartal”: target per brand, per event, per sekolah, per PIC, atau target perusahaan?
5. Bagaimana target per shift dihitung jika satu event memiliki beberapa shift atau beberapa brand?
6. Apakah daftar nama tim yang dimasukkan Permitter bersifat usulan atau penugasan final oleh PIC?
7. Siapa yang boleh approve MRV, siapa yang boleh issue barang, dan siapa yang menyetujui return?
8. Apakah barang masuk memerlukan persetujuan atau cukup dicatat oleh Warehouse?
9. Foto “cuci jug” dilakukan sebelum atau sesudah event, dan bukti apa yang dianggap lengkap?
10. Apakah Tim mengirim laporan langsung ke Admin per Event, atau melalui PIC terlebih dahulu? Client menyebut laporan Tim dan laporan PIC; jalur final perlu ditetapkan.
11. Aturan payroll apa yang berlaku untuk durasi minimum, keterlambatan, lembur, rate, koreksi, dan persetujuan?
12. Apakah event dapat ditutup jika ada item laporan yang tidak relevan, misalnya kitchen untuk event tanpa jug?
13. Apakah ekspor Excel/PDF diperlukan untuk rekap stok, shift, target, atau laporan event?
14. Apa kebijakan retensi foto dan dokumen legal, serta siapa yang boleh mengunduhnya?

---

## 17. Instruksi Penutup untuk AI Coding Agent

Mulai dari **audit baseline repository dan pemetaan kebutuhan ke fitur yang sudah ada**. Setelah itu, prioritaskan perbaikan keamanan P0/P1, lalu implementasikan kebutuhan client per modul. Gunakan screenshot dalam `Tampilan.zip` sebagai referensi UI untuk role terkait.

Pada akhir pekerjaan, sistem harus memiliki alur operasional yang konsisten dari FJP/perizinan sampai laporan diverifikasi, data yang benar-benar tersimpan di database, kontrol akses server-side, foto/dokumen yang aman, mutasi stok yang dapat diaudit, dan tampilan yang selaras dengan referensi.

**Jangan menandai seluruh pekerjaan selesai hanya karena halaman berhasil dibuka.** Setiap acceptance criteria harus diuji melalui alur pengguna yang sesuai dan, untuk fungsi sensitif, melalui automated test.
"""
out = Path("/mnt/data/PRD_Revisi_Sistem_PT_Indosakti_Sejati.md")
out.write_text(content, encoding="utf-8")
print(f"File dibuat: {out}\nUkuran: {out.stat().st_size:,} bytes\nBaris: {len(content.splitlines())}")
