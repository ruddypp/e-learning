# Portaldik

Portaldik adalah aplikasi e-learning berbasis web yang dirancang untuk memfasilitasi materi, quiz, dan kuisioner sebagai feedback dari perfoma dan soal yang diberikan. Ada empat jenis pengguna: Admin, Guru, Siswa, dan Kepala Sekolah. Aplikasi ini diterapkan pada SDN 05 Bintaro

## Fitur Utama

### Admin
- Kelola akun (guru, murid, kepala sekolah)
- Kelola data kelas dan struktur sistem
- Monitoring aktivitas pengguna
- Backup dan maintenance data dengan pencatatan riwayat backup
- Verifikasi data yang masuk jika diperlukan

### Siswa
- Login menggunakan NISN
- Membaca materi coding sesuai kelas
- Mengerjakan quiz yang telah dipublikasikan oleh guru
- Melihat nilai hasil quiz dan belajar mandiri
- Mengisi kuesioner sebagai umpan balik setelah mengerjakan quiz

### Guru
- Login menggunakan NUPTK
- Menambahkan materi coding untuk kelas yang diajar
- Menambahkan, mengedit, dan mempublikasikan quiz
- Mengelola status quiz (draft, publikasi, tutup)
- Melihat hasil quiz siswa dan memberikan nilai
- Menganalisis capaian belajar berdasarkan nilai siswa
- Melihat isi kuesioner untuk meningkatkan kualitas pengajaran

### Kepala Sekolah
- Melihat seluruh materi pembelajaran
- Melihat hasil dan perkembangan nilai siswa
- Memantau aktivitas belajar siswa
- Menganalisis efektivitas pengajaran guru berdasarkan data performa
- Melihat kuesioner tiap kelas untuk evaluasi mutu pembelajaran

## Persyaratan Sistem

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web server (Apache/Nginx)
- Browser modern (Chrome, Firefox, Safari, Edge)

## Instalasi

1. **Persiapan Database**
   - Buat database MySQL baru dengan nama `elearning`
   - Import file `database.sql` untuk membuat struktur tabel

2. **Konfigurasi**
   - Salin semua file ke direktori web server
   - Ubah konfigurasi database di `config/database.php` jika diperlukan
   - Jalankan `setup.php` untuk memastikan semua direktori yang diperlukan telah dibuat dengan benar

3. **Pengaturan Awal**
   - Login sebagai admin dengan kredensial default:
     - Email: admin@elearning.com
     - Password: password
   - Segera ubah password admin setelah login pertama

## Struktur Direktori

```
elearning/
├── assets/           # File statis (CSS, JS, gambar)
├── backups/          # Backup database
├── config/           # File konfigurasi
├── includes/         # File fungsi umum
├── pages/            # Halaman aplikasi
│   ├── admin/        # Halaman untuk admin
│   ├── guru/         # Halaman untuk guru
│   ├── siswa/        # Halaman untuk siswa
│   └── kepsek/       # Halaman untuk kepala sekolah
├── uploads/          # File yang diunggah
│   ├── materials/    # Materi pelajaran
│   ├── assignments/  # Tugas siswa
│   └── profiles/     # Foto profil
└── index.php         # Halaman utama
```

## Petunjuk Penggunaan

### Admin

1. **Mengelola Pengguna**
   - Buka halaman "Kelola Pengguna" untuk melihat, menambah, mengedit, atau menghapus pengguna
   - Untuk menambahkan guru, isi NUPTK; untuk siswa, isi NISN dan kelas

2. **Mengelola Kelas**
   - Buka halaman "Kelola Kelas" untuk mengelola struktur kelas
   - Setiap kelas dapat ditugaskan wali kelas dari guru yang tersedia

3. **Backup Data**
   - Buka halaman "Backup & Maintenance" untuk membuat atau mengembalikan backup database
   - Setiap backup dicatat dalam database untuk pelacakan yang lebih baik
   - Tambahkan deskripsi pada backup untuk memudahkan identifikasi
   - Backup otomatis dapat diatur melalui cron job

4. **Verifikasi Data**
   - Periksa halaman "Verifikasi" untuk menyetujui atau menolak data yang memerlukan verifikasi

### Guru

1. **Mengelola Materi**
   - Buat materi coding baru melalui halaman "Materi"
   - Unggah file pendukung seperti gambar atau kode contoh
   - Materi akan tersedia untuk kelas yang ditentukan

2. **Mengelola Quiz**
   - Buat quiz baru melalui halaman "Quiz" (status awal: draft)
   - Tambahkan berbagai jenis pertanyaan: pilihan ganda, essay, atau coding
   - Atur deadline dan bobot nilai
   - Publikasikan quiz saat siap untuk dikerjakan siswa
   - Tutup quiz ketika periode pengerjaan telah selesai

3. **Melihat Hasil**
   - Pantau nilai siswa melalui halaman "Laporan Nilai"
   - Beri nilai dan feedback untuk jawaban siswa
   - Lihat statistik performa kelas

4. **Kuesioner**
   - Buat kuesioner untuk mendapatkan umpan balik dari siswa
   - Analisis hasil kuesioner untuk meningkatkan pengajaran

### Siswa

1. **Belajar Materi**
   - Akses materi coding melalui halaman "Materi"
   - Filter materi berdasarkan tingkat kesulitan

2. **Mengerjakan Quiz**
   - Lihat quiz yang tersedia di dashboard (hanya quiz yang telah dipublikasikan)
   - Kerjakan quiz sebelum deadline dan selama statusnya masih terbuka
   - Lihat hasil dan feedback setelah dinilai oleh guru

3. **Melihat Nilai**
   - Pantau nilai dan kemajuan belajar di halaman "Nilai"
   - Lihat statistik dan perbandingan dengan rata-rata kelas

4. **Mengisi Kuesioner**
   - Isi kuesioner evaluasi setelah menyelesaikan quiz
   - Berikan umpan balik tentang materi dan pembelajaran
   - Lihat kuesioner lain yang tersedia untuk diisi

### Kepala Sekolah

1. **Monitoring Aktivitas**
   - Pantau aktivitas pembelajaran melalui dashboard
   - Lihat statistik penggunaan sistem
   - Analisis tren aktivitas belajar

2. **Analisis Performa**
   - Analisis nilai siswa berdasarkan kelas
   - Pantau efektivitas pengajaran guru melalui metrik:
     - Rata-rata nilai quiz
     - Kecepatan pemberian nilai
     - Tingkat penyelesaian penilaian
   - Identifikasi area yang memerlukan perhatian khusus

3. **Review Materi**
   - Lihat semua materi pembelajaran yang tersedia
   - Pastikan kualitas konten sesuai standar

4. **Evaluasi Kuesioner**
   - Lihat hasil kuesioner dari semua kelas
   - Gunakan data untuk evaluasi program pembelajaran

## Fitur Teknis

### Manajemen Quiz
- Status Quiz: Draft (persiapan), Published (tersedia untuk siswa), Closed (tidak tersedia)
- Auto-grading untuk soal pilihan ganda
- Dukungan untuk berbagai jenis soal (pilihan ganda, essay, coding)
- Analisis hasil quiz dengan visualisasi grafik

### Sistem Kuesioner
- Kuesioner evaluasi terintegrasi setelah quiz
- Dukungan berbagai jenis pertanyaan (skala, teks bebas, pilihan ganda)
- Analisis hasil kuesioner untuk guru dan kepala sekolah

### Pencatatan Aktivitas
- Pencatatan otomatis untuk login, logout, akses materi, pengerjaan quiz
- Pencatatan untuk aktivitas penilaian guru
- Pencatatan untuk aktivitas backup dan restore
- Visualisasi tren aktivitas untuk kepala sekolah

### Keamanan

Sistem ini memiliki beberapa fitur keamanan:
- Perlindungan terhadap SQL Injection dengan prepared statements
- Enkripsi password menggunakan algoritma hash modern
- Validasi input untuk semua form
- Pengelolaan sesi yang aman dengan logout otomatis
- Perlindungan direktori sensitif dengan .htaccess

## Pemecahan Masalah

### Masalah Login
- Pastikan kredensial login benar
- Periksa apakah akun masih aktif
- Hapus cache browser jika diperlukan

### Error 404
- Periksa URL yang diakses
- Pastikan file yang dimaksud ada di server

### Error 500
- Periksa log error server
- Pastikan semua tabel database ada dan sesuai struktur

### Masalah Quiz
- Jika siswa tidak dapat mengakses quiz, pastikan status quiz adalah "Published"
- Pastikan deadline quiz belum terlewati
- Verifikasi bahwa siswa terdaftar di kelas yang sesuai

## Kontak

Untuk bantuan atau pertanyaan lebih lanjut, hubungi admin di admin@elearning.com.

## Lisensi

MENTARI E-Learning dikembangkan sebagai proyek perangkat lunak pendidikan. 
