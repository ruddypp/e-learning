-- Database for E-learning System

-- Create Database
CREATE DATABASE IF NOT EXISTS elearning;
USE elearning;

-- Table: pengguna (Users)
CREATE TABLE IF NOT EXISTS pengguna (
    id VARCHAR(20) PRIMARY KEY,
    kelas_id VARCHAR(10) NULL,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    tipe_pengguna ENUM('admin', 'guru', 'siswa', 'kepsek') NOT NULL,
    nuptk VARCHAR(20) NULL,
    nisn VARCHAR(20) NULL,
    tanggal_daftar DATE NOT NULL,
    last_login TIMESTAMP NULL,
    status ENUM('pending', 'aktif', 'ditolak', 'nonaktif') DEFAULT 'aktif',
    keterangan TEXT NULL
);

-- Table: kelas (Class)
CREATE TABLE IF NOT EXISTS kelas (
    id VARCHAR(10) PRIMARY KEY,
    nama VARCHAR(50) NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL,
    wali_kelas_id VARCHAR(20) NULL,
    status ENUM('pending', 'aktif', 'nonaktif') DEFAULT 'aktif',
    tanggal_dibuat DATE DEFAULT CURRENT_DATE,
    FOREIGN KEY (wali_kelas_id) REFERENCES pengguna(id)
);

-- Add foreign key constraint to pengguna table
ALTER TABLE pengguna
ADD CONSTRAINT fk_pengguna_kelas
FOREIGN KEY (kelas_id) REFERENCES kelas(id);

-- Table: materi_coding (Coding Materials)
CREATE TABLE IF NOT EXISTS materi_coding (
    id VARCHAR(20) PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NOT NULL,
    tingkat VARCHAR(50) NOT NULL,
    tanggal_dibuat DATE NOT NULL,
    kelas_id VARCHAR(10) NOT NULL,
    dibuat_oleh VARCHAR(20) NOT NULL,
    status ENUM('pending', 'aktif', 'nonaktif') DEFAULT 'aktif',
    FOREIGN KEY (kelas_id) REFERENCES kelas(id),
    FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
);

-- Table: tugas (Assignments/Quizzes)
CREATE TABLE IF NOT EXISTS tugas (
    id VARCHAR(20) PRIMARY KEY,
    materi_id VARCHAR(20) NOT NULL,
    kelas_id VARCHAR(10) NOT NULL,
    judul VARCHAR(255) NOT NULL,
    dibuat_oleh VARCHAR(20) NOT NULL,
    deskripsi TEXT NOT NULL,
    tanggal_dibuat DATE NOT NULL,
    tanggal_deadline DATE NULL,
    FOREIGN KEY (materi_id) REFERENCES materi_coding(id),
    FOREIGN KEY (kelas_id) REFERENCES kelas(id),
    FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
);

-- Table: soal_quiz (Quiz Questions)
CREATE TABLE IF NOT EXISTS soal_quiz (
    id VARCHAR(20) PRIMARY KEY,
    tugas_id VARCHAR(20) NOT NULL,
    pertanyaan TEXT NOT NULL,
    jenis ENUM('pilihan_ganda', 'essay', 'coding') NOT NULL,
    bobot INT NOT NULL DEFAULT 1,
    FOREIGN KEY (tugas_id) REFERENCES tugas(id)
);

-- Table: pilihan_jawaban (Answer Options for Multiple Choice)
CREATE TABLE IF NOT EXISTS pilihan_jawaban (
    id VARCHAR(20) PRIMARY KEY,
    soal_id VARCHAR(20) NOT NULL,
    teks TEXT NOT NULL,
    is_benar BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (soal_id) REFERENCES soal_quiz(id)
);

-- Table: nilai_tugas (Assignment/Quiz Scores)
CREATE TABLE IF NOT EXISTS nilai_tugas (
    id VARCHAR(20) PRIMARY KEY,
    tugas_id VARCHAR(20) NOT NULL,
    siswa_id VARCHAR(20) NOT NULL,
    dinilai_oleh VARCHAR(20) NULL,
    nilai INT NULL,
    feedback TEXT NULL,
    tanggal_dinilai DATE NULL,
    file_jawaban VARCHAR(255) NULL,
    tanggal_pengumpulan DATE NULL,
    FOREIGN KEY (tugas_id) REFERENCES tugas(id),
    FOREIGN KEY (siswa_id) REFERENCES pengguna(id),
    FOREIGN KEY (dinilai_oleh) REFERENCES pengguna(id)
);

-- Table: jawaban_siswa (Student Answers)
CREATE TABLE IF NOT EXISTS jawaban_siswa (
    id VARCHAR(20) PRIMARY KEY,
    nilai_tugas_id VARCHAR(20) NOT NULL,
    soal_id VARCHAR(20) NOT NULL,
    jawaban TEXT NULL,
    pilihan_id VARCHAR(20) NULL,
    nilai_per_soal INT NULL,
    FOREIGN KEY (nilai_tugas_id) REFERENCES nilai_tugas(id),
    FOREIGN KEY (soal_id) REFERENCES soal_quiz(id),
    FOREIGN KEY (pilihan_id) REFERENCES pilihan_jawaban(id)
);

-- Table: laporan_aktivitas (Activity Logs)
CREATE TABLE IF NOT EXISTS laporan_aktivitas (
    id VARCHAR(20) PRIMARY KEY,
    pengguna_id VARCHAR(20) NOT NULL,
    tipe_aktivitas ENUM('login', 'logout', 'view_materi', 'submit_tugas', 'nilai_tugas', 'tambah_materi', 'edit_materi', 'hapus_materi', 'verifikasi') NOT NULL,
    deskripsi TEXT NULL,
    waktu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    referensi_id VARCHAR(20) NULL,
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(id)
);

-- Table: kuesioner (Questionnaires)
CREATE TABLE IF NOT EXISTS kuesioner (
    id VARCHAR(20) PRIMARY KEY,
    judul VARCHAR(255) NOT NULL,
    deskripsi TEXT NULL,
    kelas_id VARCHAR(10) NOT NULL,
    dibuat_oleh VARCHAR(20) NOT NULL,
    tanggal_dibuat DATE NOT NULL,
    FOREIGN KEY (kelas_id) REFERENCES kelas(id),
    FOREIGN KEY (dibuat_oleh) REFERENCES pengguna(id)
);

-- Table: pertanyaan_kuesioner (Questionnaire Questions)
CREATE TABLE IF NOT EXISTS pertanyaan_kuesioner (
    id VARCHAR(20) PRIMARY KEY,
    kuesioner_id VARCHAR(20) NOT NULL,
    pertanyaan TEXT NOT NULL,
    jenis ENUM('pilihan_ganda', 'skala', 'text') NOT NULL,
    FOREIGN KEY (kuesioner_id) REFERENCES kuesioner(id)
);

-- Table: jawaban_kuesioner (Questionnaire Answers)
CREATE TABLE IF NOT EXISTS jawaban_kuesioner (
    id VARCHAR(20) PRIMARY KEY,
    pertanyaan_id VARCHAR(20) NOT NULL,
    siswa_id VARCHAR(20) NOT NULL,
    jawaban TEXT NOT NULL,
    tanggal_jawab DATE NOT NULL,
    FOREIGN KEY (pertanyaan_id) REFERENCES pertanyaan_kuesioner(id),
    FOREIGN KEY (siswa_id) REFERENCES pengguna(id)
);

-- Table: log_sistem (System Logs)
CREATE TABLE IF NOT EXISTS log_sistem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pengguna_id VARCHAR(20) NULL,
    aksi VARCHAR(255) NOT NULL,
    detail TEXT NULL,
    ip_address VARCHAR(50) NULL,
    user_agent TEXT NULL,
    waktu TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pengguna_id) REFERENCES pengguna(id)
);

-- Insert default admin account
INSERT INTO pengguna (id, nama, email, password, tipe_pengguna, tanggal_daftar, status) 
VALUES ('ADMIN001', 'Administrator', 'admin@elearning.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', CURDATE(), 'aktif'); 