-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 03, 2026 at 06:25 AM
-- Server version: 10.11.19-MariaDB
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `semestalink_cendana`
--

-- --------------------------------------------------------

--
-- Table structure for table `coas`
--

CREATE TABLE `coas` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coas`
--

INSERT INTO `coas` (`id`, `code`, `name`, `type`, `category`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '4-1000', 'Pendapatan Jasa', 'income', 'pemasukan', 0, '2026-07-30 09:57:32', '2026-08-22 17:54:00'),
(3, '60100', 'Gaji', 'expense', 'pengeluaran', 1, '2026-07-30 09:57:32', '2026-07-31 03:40:35'),
(4, '60110', 'THR', 'expense', 'pengeluaran', 1, '2026-07-30 09:57:32', '2026-07-31 03:40:43'),
(5, '60300', 'Transport (BBM)', 'expense', 'pengeluaran', 1, '2026-07-30 09:57:32', '2026-07-31 03:41:09'),
(6, '60220', 'Makan Karyawan', 'expense', 'pengeluaran', 1, '2026-07-30 09:57:32', '2026-07-31 03:41:00'),
(7, '60130', 'BPJS', 'expense', 'pengeluaran', 1, '2026-07-30 09:57:32', '2026-07-31 03:40:52'),
(8, '10101', 'Kas Tunai Kantor', 'asset', 'transfer', 1, '2026-07-30 09:57:32', '2026-08-24 18:41:08'),
(9, '20100', 'Utang Usaha', 'liability', 'pengeluaran', 1, '2026-07-30 09:57:32', '2026-07-31 03:31:54'),
(10, '10201', 'Bank Mandiri', 'asset', 'transfer', 1, '2026-07-31 03:24:39', '2026-08-22 17:51:53'),
(11, '10202', 'Kas Gopay Kantor', 'asset', 'transfer', 1, '2026-07-31 03:25:00', '2026-08-22 17:52:26'),
(12, '10300', 'Piutang Usaha', 'asset', 'transfer', 1, '2026-07-31 03:26:19', '2026-08-22 17:53:10'),
(13, '10500', 'Persediaan Barang Cendana', 'asset', 'pengeluaran', 1, '2026-07-31 03:26:40', '2026-07-31 03:26:40'),
(14, '12300', 'Peralatan Kantor', 'asset', 'pengeluaran', 1, '2026-07-31 03:30:50', '2026-07-31 03:30:50'),
(15, '12400', 'Komputer & Laptop', 'asset', 'pengeluaran', 1, '2026-07-31 03:31:08', '2026-07-31 03:31:08'),
(16, '12500', 'Peralatan Jaringan', 'asset', 'pengeluaran', 1, '2026-07-31 03:31:32', '2026-07-31 03:31:32'),
(17, '30300', 'Prive', 'expense', 'pengeluaran', 1, '2026-07-31 03:32:32', '2026-07-31 03:32:32'),
(18, '40100', 'Pendapatan Kontrak Retail Bulanan', 'income', 'pemasukan', 1, '2026-07-31 03:32:53', '2026-08-23 12:51:14'),
(19, '40200', 'Pendapatan Kontrak Corporate Bulanan', 'income', 'pemasukan', 1, '2026-07-31 03:33:23', '2026-08-23 12:50:48'),
(20, '40300', 'Pendapatan CS Komputer( (Umum & Lain-lain)', 'income', 'pemasukan', 1, '2026-07-31 03:33:40', '2026-08-23 12:55:24'),
(21, '40400', 'Pendapatan Project Pemerintahan', 'income', 'pemasukan', 1, '2026-07-31 03:34:06', '2026-07-31 03:34:06'),
(22, '40500', 'Pendapatan Project Corporate (Non Bulanan)', 'income', 'pemasukan', 1, '2026-07-31 03:34:26', '2026-08-23 12:52:24'),
(23, '60120', 'Bonus', 'expense', 'pengeluaran', 1, '2026-07-31 03:40:22', '2026-07-31 03:40:22'),
(24, '60200', 'Listrik', 'expense', 'pengeluaran', 1, '2026-07-31 03:41:40', '2026-07-31 03:41:40'),
(25, '60210', 'Air', 'expense', 'pengeluaran', 1, '2026-07-31 03:42:01', '2026-07-31 03:42:01'),
(26, '60240', 'ATK', 'expense', 'pengeluaran', 1, '2026-07-31 03:42:52', '2026-07-31 03:42:52'),
(27, '60310', 'Service Kendaraan', 'expense', 'pengeluaran', 1, '2026-07-31 03:43:17', '2026-07-31 03:43:17'),
(28, '60400', 'Iklan', 'expense', 'pengeluaran', 1, '2026-07-31 03:43:35', '2026-07-31 03:43:35'),
(29, '60410', 'Marketing Freelance', 'expense', 'pengeluaran', 1, '2026-07-31 03:43:53', '2026-07-31 03:43:53'),
(30, '60500', 'Bandwith Internet', 'expense', 'pengeluaran', 1, '2026-07-31 03:44:37', '2026-07-31 03:44:37'),
(31, '60510', 'Maintenance Jaringan Internal', 'expense', 'pengeluaran', 1, '2026-07-31 03:44:56', '2026-07-31 03:44:56'),
(32, '60600', 'Perawatan Kantor', 'expense', 'pengeluaran', 1, '2026-07-31 03:45:13', '2026-07-31 03:45:13'),
(33, '60620', 'Software Berlangganan', 'expense', 'pengeluaran', 1, '2026-07-31 03:45:31', '2026-07-31 03:45:31'),
(34, '80100', 'PPH 21', 'tax', 'pengeluaran', 1, '2026-07-31 03:48:27', '2026-07-31 03:48:27'),
(35, '80200', 'PPH 22', 'tax', 'pengeluaran', 1, '2026-07-31 03:48:40', '2026-07-31 03:48:40'),
(36, '80300', 'PPH 23', 'tax', 'pengeluaran', 1, '2026-07-31 03:48:52', '2026-07-31 03:48:52'),
(37, '80400', 'PPH 25', 'tax', 'pengeluaran', 1, '2026-07-31 03:49:05', '2026-07-31 03:49:05'),
(38, '80500', 'PPH Tahunan', 'tax', 'pengeluaran', 1, '2026-07-31 03:49:37', '2026-07-31 03:49:37'),
(39, '80600', 'PPN Masukan', 'tax', 'pengeluaran', 1, '2026-07-31 03:49:53', '2026-07-31 03:49:53'),
(40, '80700', 'PPN Keluaran', 'tax', 'pengeluaran', 1, '2026-07-31 03:50:16', '2026-07-31 03:50:16'),
(41, '60403', 'Biaya Coaching & Pengembangan Karyawan', 'expense', 'pengeluaran', 1, '2026-08-12 14:45:30', '2026-08-12 14:45:30'),
(42, '60420', 'Beban Jamuan & Relasi Pelanggan', 'expense', 'pengeluaran', 1, '2026-08-12 19:11:47', '2026-08-12 19:11:47'),
(43, '10900', 'Ayat Silang', 'asset', 'pengeluaran', 1, '2026-08-12 19:39:32', '2026-08-12 19:39:32'),
(44, '60430', 'Beban Sumbangan & Sosial', 'expense', 'pengeluaran', 1, '2026-08-12 20:15:28', '2026-08-12 20:15:28'),
(46, '10901', 'Ayat Penyesuaian Debet', 'asset', 'pemasukan', 1, '2026-09-02 16:03:42', '2026-09-02 16:03:42'),
(47, '10902', 'Ayat Penyesuaian Kredit ', 'asset', 'pengeluaran', 1, '2026-09-02 16:04:06', '2026-09-02 16:08:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `coas`
--
ALTER TABLE `coas`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `coas`
--
ALTER TABLE `coas`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
