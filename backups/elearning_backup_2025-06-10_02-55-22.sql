-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: elearning
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `backup_data`
--

DROP TABLE IF EXISTS `backup_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_data` (
  `id` varchar(20) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `ukuran_file` int(11) NOT NULL,
  `dibuat_oleh` varchar(20) NOT NULL,
  `tanggal_dibuat` timestamp NOT NULL DEFAULT current_timestamp(),
  `deskripsi` text DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  PRIMARY KEY (`id`),
  KEY `dibuat_oleh` (`dibuat_oleh`),
  CONSTRAINT `backup_data_ibfk_1` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_data`
--

LOCK TABLES `backup_data` WRITE;
/*!40000 ALTER TABLE `backup_data` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_data` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jawaban_kuesioner`
--

DROP TABLE IF EXISTS `jawaban_kuesioner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jawaban_kuesioner` (
  `id` varchar(20) NOT NULL,
  `pertanyaan_id` varchar(20) NOT NULL,
  `siswa_id` varchar(20) NOT NULL,
  `jawaban` text NOT NULL,
  `tanggal_jawab` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `pertanyaan_id` (`pertanyaan_id`),
  KEY `siswa_id` (`siswa_id`),
  CONSTRAINT `jawaban_kuesioner_ibfk_1` FOREIGN KEY (`pertanyaan_id`) REFERENCES `pertanyaan_kuesioner` (`id`),
  CONSTRAINT `jawaban_kuesioner_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jawaban_kuesioner`
--

LOCK TABLES `jawaban_kuesioner` WRITE;
/*!40000 ALTER TABLE `jawaban_kuesioner` DISABLE KEYS */;
INSERT INTO `jawaban_kuesioner` VALUES ('JWK68452e5222afc4c86','PRT68452e52236bc622f','Ubb1d3b20','1','2025-06-08'),('JWK68452e529e0a6a82f','PRT68452e5246c354af9','Ubb1d3b20','bener semua nih bu','2025-06-08'),('JWK68452e52abcc055f5','PRT68452e52acd359586','Ubb1d3b20','5','2025-06-08'),('JWK6846c1b211e2d99e2','PRT6846c1b2a6b8ac198','Ubb1d3b20','2','2025-06-09'),('JWK6846c1b245680551e','PRT6846c1b2aef3dbdd0','Ubb1d3b20','oke','2025-06-09'),('JWK6846c1b2acf4e800a','PRT6846c1b2819dd2228','Ubb1d3b20','2','2025-06-09');
/*!40000 ALTER TABLE `jawaban_kuesioner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jawaban_siswa`
--

DROP TABLE IF EXISTS `jawaban_siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jawaban_siswa` (
  `id` varchar(20) NOT NULL,
  `nilai_tugas_id` varchar(20) NOT NULL,
  `soal_id` varchar(20) NOT NULL,
  `jawaban` text DEFAULT NULL,
  `pilihan_id` varchar(20) DEFAULT NULL,
  `nilai_per_soal` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nilai_tugas_id` (`nilai_tugas_id`),
  KEY `soal_id` (`soal_id`),
  KEY `pilihan_id` (`pilihan_id`),
  CONSTRAINT `jawaban_siswa_ibfk_1` FOREIGN KEY (`nilai_tugas_id`) REFERENCES `nilai_tugas` (`id`),
  CONSTRAINT `jawaban_siswa_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `soal_quiz` (`id`),
  CONSTRAINT `jawaban_siswa_ibfk_3` FOREIGN KEY (`pilihan_id`) REFERENCES `pilihan_jawaban` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jawaban_siswa`
--

LOCK TABLES `jawaban_siswa` WRITE;
/*!40000 ALTER TABLE `jawaban_siswa` DISABLE KEYS */;
INSERT INTO `jawaban_siswa` VALUES ('JWB68452e412c93d3acf','NT68452e416cff8e18c6','QST68452df509e0160ea',NULL,'OPT68452df5cb18e2f57',1),('JWB68452e418fc3b5efd','NT68452e416cff8e18c6','QST68452d60da8a13f22',NULL,'OPT68452d603447f6747',1),('JWB6846c1a6e56316c23','NT6846c1a6b848a1c4a3','QST6846c157616a18482',NULL,'OPT6846c157c3f1ec93e',3);
/*!40000 ALTER TABLE `jawaban_siswa` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kelas`
--

DROP TABLE IF EXISTS `kelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kelas` (
  `id` varchar(10) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `wali_kelas_id` varchar(20) DEFAULT NULL,
  `status` enum('pending','aktif','nonaktif') DEFAULT 'aktif',
  `tanggal_dibuat` date DEFAULT curdate(),
  PRIMARY KEY (`id`),
  KEY `wali_kelas_id` (`wali_kelas_id`),
  CONSTRAINT `kelas_ibfk_1` FOREIGN KEY (`wali_kelas_id`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kelas`
--

LOCK TABLES `kelas` WRITE;
/*!40000 ALTER TABLE `kelas` DISABLE KEYS */;
INSERT INTO `kelas` VALUES ('CLS377b01f','X-O','2025','U6a8988b5','aktif','2025-06-08');
/*!40000 ALTER TABLE `kelas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kuesioner`
--

DROP TABLE IF EXISTS `kuesioner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kuesioner` (
  `id` varchar(20) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kelas_id` varchar(10) NOT NULL,
  `dibuat_oleh` varchar(20) NOT NULL,
  `tanggal_dibuat` date NOT NULL,
  `status` enum('draft','published','closed') DEFAULT 'published',
  PRIMARY KEY (`id`),
  KEY `kelas_id` (`kelas_id`),
  KEY `dibuat_oleh` (`dibuat_oleh`),
  CONSTRAINT `kuesioner_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`),
  CONSTRAINT `kuesioner_ibfk_2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kuesioner`
--

LOCK TABLES `kuesioner` WRITE;
/*!40000 ALTER TABLE `kuesioner` DISABLE KEYS */;
INSERT INTO `kuesioner` VALUES ('KSN68452e52257632616','Kuesioner Quiz Ini Quiz','Kuesioner evaluasi quiz','CLS377b01f','Ubb1d3b20','2025-06-08','published'),('KSN6846c1b22f019d959','Kuesioner Quiz syntax javascript','Kuesioner evaluasi quiz','CLS377b01f','Ubb1d3b20','2025-06-09','published');
/*!40000 ALTER TABLE `kuesioner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan_aktivitas`
--

DROP TABLE IF EXISTS `laporan_aktivitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `laporan_aktivitas` (
  `id` varchar(20) NOT NULL,
  `pengguna_id` varchar(20) NOT NULL,
  `tipe_aktivitas` enum('login','logout','view_materi','submit_tugas','nilai_tugas','tambah_materi','edit_materi','hapus_materi','verifikasi','create_backup','isi_kuesioner','publish_quiz','close_quiz','view_grade') NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp(),
  `referensi_id` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pengguna_id` (`pengguna_id`),
  CONSTRAINT `laporan_aktivitas_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan_aktivitas`
--

LOCK TABLES `laporan_aktivitas` WRITE;
/*!40000 ALTER TABLE `laporan_aktivitas` DISABLE KEYS */;
INSERT INTO `laporan_aktivitas` VALUES ('ACT6845267c93aeb1423','ADMIN001','tambah_materi','Admin menambahkan pengguna baru: rudy','2025-06-08 05:58:20',NULL),('ACT684526a61a160dc57','ADMIN001','tambah_materi','Admin menambahkan pengguna baru: pani','2025-06-08 05:59:02',NULL),('ACT684529ac860a4e0e1','ADMIN001','tambah_materi','Admin menambahkan pengguna baru: dalia','2025-06-08 06:11:56',NULL),('ACT684529b1eb1e04ed8','ADMIN001','logout','Logout berhasil','2025-06-08 06:12:01',NULL),('ACT684529df9a9ce1cdf','U6a8988b5','login','Login berhasil','2025-06-08 06:12:47',NULL),('ACT684529f5ea17dfb1d','U6a8988b5','tambah_materi','Guru menambahkan materi coding baru: Php Pemula','2025-06-08 06:13:09',NULL),('ACT68452a57ae1a92c86','U6a8988b5','logout','Logout berhasil','2025-06-08 06:14:47',NULL),('ACT68452a64a2f2d9252','U6a8988b5','login','Login berhasil','2025-06-08 06:15:00',NULL),('ACT68452a6883b9099e5','U6a8988b5','logout','Logout berhasil','2025-06-08 06:15:04',NULL),('ACT68452aa562ced8d11','U6a8988b5','login','Login berhasil','2025-06-08 06:16:05',NULL),('ACT68452ac8a1581ee92','U6a8988b5','tambah_materi','Guru menambahkan quiz baru: Ini Quiz','2025-06-08 06:16:40',NULL),('ACT68452df5e84ac9418','U6a8988b5','tambah_materi','Guru menambahkan soal baru pada quiz: Ini Quiz','2025-06-08 06:30:13',NULL),('ACT68452e22913bcfad6','Ubb1d3b20','login','Login berhasil','2025-06-08 06:30:58',NULL),('ACT68452e3684b21f45e','U6a8988b5','publish_quiz','Guru mempublikasikan quiz: Ini Quiz','2025-06-08 06:31:18','QZ68452ac8b3336136c2'),('ACT68452e41589decfd1','Ubb1d3b20','submit_tugas','Siswa mengumpulkan jawaban quiz: Ini Quiz','2025-06-08 06:31:29','QZ68452ac8b3336136c2'),('ACT68452e527b12fd19a','Ubb1d3b20','isi_kuesioner','Siswa mengisi kuesioner evaluasi quiz: Ini Quiz','2025-06-08 06:31:46','QZ68452ac8b3336136c2'),('ACT68452e934943b536f','Ubb1d3b20','logout','Logout berhasil','2025-06-08 06:32:51',NULL),('ACT68452edc13431d93a','U6a8988b5','nilai_tugas','Guru menilai quiz Ini Quiz untuk siswa dengan ID Ubb1d3b20','2025-06-08 06:34:04',NULL),('ACT68452fae3ec1ee3ea','U6a8988b5','nilai_tugas','Guru menilai quiz Ini Quiz untuk siswa dengan ID Ubb1d3b20','2025-06-08 06:37:34',NULL),('ACT68452fc6c29ab5838','U6a8988b5','close_quiz','Guru menutup quiz: Ini Quiz','2025-06-08 06:37:58','QZ68452ac8b3336136c2'),('ACT68452fe151d5b4299','U6a8988b5','logout','Logout berhasil','2025-06-08 06:38:25',NULL),('ACT6845300607359b0fe','ADMIN001','login','Login berhasil','2025-06-08 06:39:02',NULL),('ACT684535fc7dee1027f','ADMIN001','logout','Logout berhasil','2025-06-08 07:04:28',NULL),('ACT6845361539481d65f','ADMIN001','login','Login berhasil','2025-06-08 07:04:53',NULL),('ACT68453623685ca1494','ADMIN001','verifikasi','Admin mengedit pengguna: pani','2025-06-08 07:05:07',NULL),('ACT68453625649be5572','ADMIN001','logout','Logout berhasil','2025-06-08 07:05:09',NULL),('ACT6845362ba66e05609','U1c235465','login','Login berhasil','2025-06-08 07:05:15',NULL),('ACT684536300115f25ab','U1c235465','view_grade','Kepala Sekolah melihat analisis nilai kelas: X-O (2025)','2025-06-08 07:05:20','CLS377b01f'),('ACT6845369e13f9ef5de','U1c235465','view_grade','Kepala Sekolah melihat analisis nilai kelas: X-O (2025)','2025-06-08 07:07:10','CLS377b01f'),('ACT684536a2b05d5e233','U1c235465','view_grade','Kepala Sekolah melihat analisis nilai kelas: X-O (2025)','2025-06-08 07:07:14','CLS377b01f'),('ACT68453742cbc6c0e2a','U1c235465','view_grade','Kepala Sekolah melihat analisis nilai kelas: X-O (2025)','2025-06-08 07:09:54','CLS377b01f'),('ACT684537e2ef434c984','U1c235465','logout','Logout berhasil','2025-06-08 07:12:34',NULL),('ACT6845380669549b671','U6a8988b5','login','Login berhasil','2025-06-08 07:13:10',NULL),('ACT6845382afb31b6ae1','U6a8988b5','logout','Logout berhasil','2025-06-08 07:13:46',NULL),('ACT68453831cdb89fc27','Ubb1d3b20','login','Login berhasil','2025-06-08 07:13:53',NULL),('ACT684538565afff9ee2','Ubb1d3b20','view_materi','Siswa melihat materi: Php Pemula','2025-06-08 07:14:30','MTR684529f550d6b07a7'),('ACT6845c983b5cef5225','U6a8988b5','login','Login berhasil','2025-06-08 17:33:55',NULL),('ACT6845c98ebb4f645a6','U6a8988b5','logout','Logout berhasil','2025-06-08 17:34:06',NULL),('ACT6845c994f869c0c5e','Ubb1d3b20','login','Login berhasil','2025-06-08 17:34:12',NULL),('ACT6845c9ad42b3c2d26','Ubb1d3b20','logout','Logout berhasil','2025-06-08 17:34:37',NULL),('ACT6845c9b3fcc009e73','ADMIN001','login','Login berhasil','2025-06-08 17:34:43',NULL),('ACT6846bcdf0b41bb8ce','ADMIN001','login','Login berhasil','2025-06-09 10:52:15',NULL),('ACT6846bcf2732537d30','ADMIN001','logout','Logout berhasil','2025-06-09 10:52:34',NULL),('ACT6846bd11e418992c7','U6a8988b5','login','Login berhasil','2025-06-09 10:53:05',NULL),('ACT6846bd3f3bcb5e1a8','U6a8988b5','logout','Logout berhasil','2025-06-09 10:53:51',NULL),('ACT6846bd435fa7cf0e2','Ubb1d3b20','login','Login berhasil','2025-06-09 10:53:55',NULL),('ACT6846bd492b036a115','Ubb1d3b20','view_materi','Siswa melihat materi: Php Pemula','2025-06-09 10:54:01','MTR684529f550d6b07a7'),('ACT6846bd5611a6e59e3','Ubb1d3b20','logout','Logout berhasil','2025-06-09 10:54:14',NULL),('ACT6846bd66ec1bcb85f','U6a8988b5','login','Login berhasil','2025-06-09 10:54:30',NULL),('ACT6846bd730b1f3e411','U6a8988b5','tambah_materi','Guru menambahkan materi coding baru: html','2025-06-09 10:54:43',NULL),('ACT6846bd8e75d0c0b3f','U6a8988b5','tambah_materi','Guru menambahkan quiz baru: html pemula','2025-06-09 10:55:10',NULL),('ACT6846bdaeeffaecce1','U6a8988b5','tambah_materi','Guru menambahkan soal baru pada quiz: html pemula','2025-06-09 10:55:42',NULL),('ACT6846bfcc265b847b6','U6a8988b5','tambah_materi','Guru menambahkan soal baru pada quiz: html pemula','2025-06-09 11:04:44',NULL),('ACT6846c10f14bd4480b','U6a8988b5','tambah_materi','Guru menambahkan materi coding baru: javascript','2025-06-09 11:10:07',NULL),('ACT6846c12b717105bfa','U6a8988b5','tambah_materi','Guru menambahkan quiz baru: syntax javascript','2025-06-09 11:10:35',NULL),('ACT6846c157ee570435d','U6a8988b5','tambah_materi','Guru menambahkan soal baru pada quiz: syntax javascript','2025-06-09 11:11:19',NULL),('ACT6846c165ede0360a1','U6a8988b5','logout','Logout berhasil','2025-06-09 11:11:33',NULL),('ACT6846c169aa44d8b8d','Ubb1d3b20','login','Login berhasil','2025-06-09 11:11:37',NULL),('ACT6846c16fc1fef4c5e','Ubb1d3b20','view_materi','Siswa melihat materi: javascript','2025-06-09 11:11:43','MTR6846c10f8d0bdbe47'),('ACT6846c17be36065bc4','Ubb1d3b20','logout','Logout berhasil','2025-06-09 11:11:55',NULL),('ACT6846c18290da2ca7a','U6a8988b5','login','Login berhasil','2025-06-09 11:12:02',NULL),('ACT6846c189886c32d04','U6a8988b5','publish_quiz','Guru mempublikasikan quiz: syntax javascript','2025-06-09 11:12:09','QZ6846c12bb481ee6210'),('ACT6846c18c4246e48dd','U6a8988b5','logout','Logout berhasil','2025-06-09 11:12:12',NULL),('ACT6846c1927bb350e41','Ubb1d3b20','login','Login berhasil','2025-06-09 11:12:18',NULL),('ACT6846c1956928a2816','Ubb1d3b20','view_materi','Siswa melihat materi: javascript','2025-06-09 11:12:21','MTR6846c10f8d0bdbe47'),('ACT6846c1a68fa4fea56','Ubb1d3b20','submit_tugas','Siswa mengumpulkan jawaban quiz: syntax javascript','2025-06-09 11:12:38','QZ6846c12bb481ee6210'),('ACT6846c1b23a619e60e','Ubb1d3b20','isi_kuesioner','Siswa mengisi kuesioner evaluasi quiz: syntax javascript','2025-06-09 11:12:50','QZ6846c12bb481ee6210'),('ACT6846c1c0973620336','Ubb1d3b20','logout','Logout berhasil','2025-06-09 11:13:04',NULL),('ACT6846c1c64599da14b','U6a8988b5','login','Login berhasil','2025-06-09 11:13:10',NULL),('ACT6846cb844a9f3f291','U6a8988b5','login','Login berhasil','2025-06-09 11:54:44',NULL),('ACT68470bc4caf5f79b0','U6a8988b5','logout','Logout berhasil','2025-06-09 16:28:52',NULL),('ACT68470bdb592245b80','ADMIN001','login','Login berhasil','2025-06-09 16:29:15',NULL),('ACT6847786ad63e2f53c','ADMIN001','login','Login berhasil','2025-06-10 00:12:26',NULL),('ACT684779c20457cf653','ADMIN001','logout','Logout berhasil','2025-06-10 00:18:10',NULL),('ACT684779d4aafe415b0','ADMIN001','login','Login berhasil','2025-06-10 00:18:28',NULL);
/*!40000 ALTER TABLE `laporan_aktivitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_sistem`
--

DROP TABLE IF EXISTS `log_sistem`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_sistem` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pengguna_id` varchar(20) DEFAULT NULL,
  `aksi` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pengguna_id` (`pengguna_id`),
  CONSTRAINT `log_sistem_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_sistem`
--

LOCK TABLES `log_sistem` WRITE;
/*!40000 ALTER TABLE `log_sistem` DISABLE KEYS */;
INSERT INTO `log_sistem` VALUES (1,'ADMIN001','tambah_pengguna','Admin menambahkan pengguna baru: rudy (ID: U6a8988b5)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0','2025-06-08 05:58:20'),(2,'ADMIN001','tambah_pengguna','Admin menambahkan pengguna baru: pani (ID: U1c235465)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0','2025-06-08 05:59:02'),(3,'ADMIN001','tambah_pengguna','Admin menambahkan pengguna baru: dalia (ID: Ubb1d3b20)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0','2025-06-08 06:11:56'),(4,'ADMIN001','edit_pengguna','Admin mengedit pengguna: pani (ID: U1c235465)','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0','2025-06-08 07:05:07');
/*!40000 ALTER TABLE `log_sistem` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materi_coding`
--

DROP TABLE IF EXISTS `materi_coding`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materi_coding` (
  `id` varchar(20) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text NOT NULL,
  `tingkat` varchar(50) NOT NULL,
  `tanggal_dibuat` date NOT NULL,
  `kelas_id` varchar(10) NOT NULL,
  `dibuat_oleh` varchar(20) NOT NULL,
  `status` enum('pending','aktif','nonaktif') DEFAULT 'aktif',
  PRIMARY KEY (`id`),
  KEY `kelas_id` (`kelas_id`),
  KEY `dibuat_oleh` (`dibuat_oleh`),
  CONSTRAINT `materi_coding_ibfk_1` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`),
  CONSTRAINT `materi_coding_ibfk_2` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materi_coding`
--

LOCK TABLES `materi_coding` WRITE;
/*!40000 ALTER TABLE `materi_coding` DISABLE KEYS */;
INSERT INTO `materi_coding` VALUES ('MTR684529f550d6b07a7','Php Pemula','Jadi ini tentang belajar PHP ya kids','Pemula','2025-06-08','CLS377b01f','U6a8988b5','aktif'),('MTR6846bd73319d5da97','html','ini htmtl','Pemula','2025-06-09','CLS377b01f','U6a8988b5','aktif'),('MTR6846c10f8d0bdbe47','javascript','javascript','Menengah','2025-06-09','CLS377b01f','U6a8988b5','aktif');
/*!40000 ALTER TABLE `materi_coding` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nilai_tugas`
--

DROP TABLE IF EXISTS `nilai_tugas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nilai_tugas` (
  `id` varchar(20) NOT NULL,
  `tugas_id` varchar(20) NOT NULL,
  `siswa_id` varchar(20) NOT NULL,
  `dinilai_oleh` varchar(20) DEFAULT NULL,
  `nilai` int(11) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `tanggal_dinilai` date DEFAULT NULL,
  `file_jawaban` varchar(255) DEFAULT NULL,
  `tanggal_pengumpulan` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tugas_id` (`tugas_id`),
  KEY `siswa_id` (`siswa_id`),
  KEY `dinilai_oleh` (`dinilai_oleh`),
  CONSTRAINT `nilai_tugas_ibfk_1` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`),
  CONSTRAINT `nilai_tugas_ibfk_2` FOREIGN KEY (`siswa_id`) REFERENCES `pengguna` (`id`),
  CONSTRAINT `nilai_tugas_ibfk_3` FOREIGN KEY (`dinilai_oleh`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nilai_tugas`
--

LOCK TABLES `nilai_tugas` WRITE;
/*!40000 ALTER TABLE `nilai_tugas` DISABLE KEYS */;
INSERT INTO `nilai_tugas` VALUES ('NT68452e416cff8e18c6','QZ68452ac8b3336136c2','Ubb1d3b20','U6a8988b5',100,'oke','2025-06-08',NULL,'2025-06-08'),('NT6846c1a6b848a1c4a3','QZ6846c12bb481ee6210','Ubb1d3b20',NULL,100,NULL,NULL,NULL,'2025-06-09');
/*!40000 ALTER TABLE `nilai_tugas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pengguna`
--

DROP TABLE IF EXISTS `pengguna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengguna` (
  `id` varchar(20) NOT NULL,
  `kelas_id` varchar(10) DEFAULT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `tipe_pengguna` enum('admin','guru','siswa','kepsek') NOT NULL,
  `nuptk` varchar(20) DEFAULT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `tanggal_daftar` date NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('pending','aktif','ditolak','nonaktif') DEFAULT 'aktif',
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pengguna_kelas` (`kelas_id`),
  CONSTRAINT `fk_pengguna_kelas` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengguna`
--

LOCK TABLES `pengguna` WRITE;
/*!40000 ALTER TABLE `pengguna` DISABLE KEYS */;
INSERT INTO `pengguna` VALUES ('ADMIN001',NULL,'Administrator','admin@elearning.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',NULL,NULL,'2025-06-08','2025-06-10 00:18:28','aktif',NULL),('U1c235465',NULL,'pani','pani@kepsek.com','$2y$10$Au4568nSM.gXnm7juZtT2.uBFTLzo8M4gY9sZliVpOg0JwfkDzQoW','kepsek','987654321','987654321','2025-06-08','2025-06-08 07:05:15','aktif',NULL),('U6a8988b5',NULL,'rudy','rudy@guru.com','$2y$10$qvIB5g6UYJWu71cSs0MS3.3kmkdUfTbYenhas3J0mhSssn/BaJJXe','guru','123456789',NULL,'2025-06-08','2025-06-09 11:54:44','aktif',NULL),('Ubb1d3b20','CLS377b01f','dalia','dali@siswa.com','$2y$10$WfAKMywOH5iQJVsog4ajVu5jQVQCMUiT/Vor4UY2pcg7qbft1fqv6','siswa',NULL,'221011401952','2025-06-08','2025-06-09 11:12:18','aktif',NULL);
/*!40000 ALTER TABLE `pengguna` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pertanyaan_kuesioner`
--

DROP TABLE IF EXISTS `pertanyaan_kuesioner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pertanyaan_kuesioner` (
  `id` varchar(20) NOT NULL,
  `kuesioner_id` varchar(20) NOT NULL,
  `pertanyaan` text NOT NULL,
  `jenis` enum('pilihan_ganda','skala','text') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kuesioner_id` (`kuesioner_id`),
  CONSTRAINT `pertanyaan_kuesioner_ibfk_1` FOREIGN KEY (`kuesioner_id`) REFERENCES `kuesioner` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pertanyaan_kuesioner`
--

LOCK TABLES `pertanyaan_kuesioner` WRITE;
/*!40000 ALTER TABLE `pertanyaan_kuesioner` DISABLE KEYS */;
INSERT INTO `pertanyaan_kuesioner` VALUES ('PRT68452e52236bc622f','KSN68452e52257632616','Tingkat kesulitan quiz','skala'),('PRT68452e5246c354af9','KSN68452e52257632616','Saran dan masukan','text'),('PRT68452e52acd359586','KSN68452e52257632616','Kejelasan materi dan soal','skala'),('PRT6846c1b2819dd2228','KSN6846c1b22f019d959','Tingkat kesulitan quiz','skala'),('PRT6846c1b2a6b8ac198','KSN6846c1b22f019d959','Kejelasan materi dan soal','skala'),('PRT6846c1b2aef3dbdd0','KSN6846c1b22f019d959','Saran dan masukan','text');
/*!40000 ALTER TABLE `pertanyaan_kuesioner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pilihan_jawaban`
--

DROP TABLE IF EXISTS `pilihan_jawaban`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pilihan_jawaban` (
  `id` varchar(20) NOT NULL,
  `soal_id` varchar(20) NOT NULL,
  `teks` text NOT NULL,
  `is_benar` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `soal_id` (`soal_id`),
  CONSTRAINT `pilihan_jawaban_ibfk_1` FOREIGN KEY (`soal_id`) REFERENCES `soal_quiz` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pilihan_jawaban`
--

LOCK TABLES `pilihan_jawaban` WRITE;
/*!40000 ALTER TABLE `pilihan_jawaban` DISABLE KEYS */;
INSERT INTO `pilihan_jawaban` VALUES ('OPT68452d601455785b2','QST68452d60da8a13f22','ngga',0),('OPT68452d603447f6747','QST68452d60da8a13f22','iya',1),('OPT68452df54ce68c377','QST68452df509e0160ea','ngga',0),('OPT68452df5cb18e2f57','QST68452df509e0160ea','iya',1),('OPT6846bdaec7ceb87ec','QST6846bdaed6fb8b2b6','apa ya',0),('OPT6846bdaec8b2d1aca','QST6846bdaed6fb8b2b6','gatau',1),('OPT6846bfcc5587deda0','QST6846bfcc9f5afb6a3','apa',0),('OPT6846bfcc9106e9e4e','QST6846bfcc9f5afb6a3','gatau',1),('OPT6846c15756af2f631','QST6846c157616a18482','gatau',0),('OPT6846c157c3f1ec93e','QST6846c157616a18482','apanya',1);
/*!40000 ALTER TABLE `pilihan_jawaban` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `soal_quiz`
--

DROP TABLE IF EXISTS `soal_quiz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `soal_quiz` (
  `id` varchar(20) NOT NULL,
  `tugas_id` varchar(20) NOT NULL,
  `pertanyaan` text NOT NULL,
  `jenis` enum('pilihan_ganda','essay','coding') NOT NULL,
  `bobot` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `tugas_id` (`tugas_id`),
  CONSTRAINT `soal_quiz_ibfk_1` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `soal_quiz`
--

LOCK TABLES `soal_quiz` WRITE;
/*!40000 ALTER TABLE `soal_quiz` DISABLE KEYS */;
INSERT INTO `soal_quiz` VALUES ('QST68452d60da8a13f22','QZ68452ac8b3336136c2','apakah kucing itu nyata?','pilihan_ganda',1),('QST68452df509e0160ea','QZ68452ac8b3336136c2','apakah kucing itu nyata?','pilihan_ganda',1),('QST6846bdaed6fb8b2b6','QZ6846bd8e0aeac2bb7d','apa yang dimaksud html?','pilihan_ganda',3),('QST6846bfcc9f5afb6a3','QZ6846bd8e0aeac2bb7d','apa yang di maksud php','pilihan_ganda',3),('QST6846c157616a18482','QZ6846c12bb481ee6210','apa yang di maksud dengan javascipt','pilihan_ganda',3);
/*!40000 ALTER TABLE `soal_quiz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tugas`
--

DROP TABLE IF EXISTS `tugas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tugas` (
  `id` varchar(20) NOT NULL,
  `materi_id` varchar(20) NOT NULL,
  `kelas_id` varchar(10) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `dibuat_oleh` varchar(20) NOT NULL,
  `deskripsi` text NOT NULL,
  `tanggal_dibuat` date NOT NULL,
  `tanggal_deadline` date DEFAULT NULL,
  `status` enum('draft','published','closed') DEFAULT 'draft',
  PRIMARY KEY (`id`),
  KEY `materi_id` (`materi_id`),
  KEY `kelas_id` (`kelas_id`),
  KEY `dibuat_oleh` (`dibuat_oleh`),
  CONSTRAINT `tugas_ibfk_1` FOREIGN KEY (`materi_id`) REFERENCES `materi_coding` (`id`),
  CONSTRAINT `tugas_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`),
  CONSTRAINT `tugas_ibfk_3` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tugas`
--

LOCK TABLES `tugas` WRITE;
/*!40000 ALTER TABLE `tugas` DISABLE KEYS */;
INSERT INTO `tugas` VALUES ('QZ68452ac8b3336136c2','MTR684529f550d6b07a7','CLS377b01f','Ini Quiz','U6a8988b5','hai','2025-06-08','2025-06-09','closed'),('QZ6846bd8e0aeac2bb7d','MTR6846bd73319d5da97','CLS377b01f','html pemula','U6a8988b5','quiz seputar html','2025-06-09','2025-06-10','draft'),('QZ6846c12bb481ee6210','MTR6846c10f8d0bdbe47','CLS377b01f','syntax javascript','U6a8988b5','membahas terkait syntax','2025-06-09','2025-06-10','published');
/*!40000 ALTER TABLE `tugas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-10  7:55:23
