-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 27, 2026 at 09:40 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `central_college_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `name`, `email`, `contact`, `password`, `created_at`) VALUES
(1, 'admin', 'System Administrator', 'admin1@gmail.com', '0771234567', 'admin1', '2026-09-15 05:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `student_id`, `date`, `status`, `class_id`, `created_at`) VALUES
(1, 1, '2026-09-15', 'present', 1, '2026-09-15 05:13:03'),
(2, 2, '2026-09-15', 'present', 1, '2026-09-15 05:13:03'),
(3, 3, '2026-09-15', 'absent', 1, '2026-09-15 05:13:03'),
(4, 4, '2026-09-15', 'late', 1, '2026-09-15 05:13:03'),
(5, 5, '2026-09-15', 'present', 1, '2026-09-15 05:13:03'),
(6, 6, '2026-09-15', 'present', 1, '2026-09-15 05:13:03'),
(7, 7, '2026-09-15', 'present', 1, '2026-09-15 05:13:03'),
(8, 8, '2026-09-15', 'present', 1, '2026-09-15 05:13:03'),
(9, 1, '2026-09-22', 'absent', 1, '2026-09-22 10:53:21'),
(10, 2, '2026-09-22', 'present', 1, '2026-09-22 10:53:29'),
(11, 3, '2026-09-22', 'present', 1, '2026-09-22 10:53:32'),
(12, 4, '2026-09-22', 'late', 1, '2026-09-22 10:53:37'),
(13, 5, '2026-09-22', 'present', 1, '2026-09-22 10:53:40'),
(14, 6, '2026-09-22', 'absent', 1, '2026-09-22 10:53:46'),
(15, 7, '2026-09-22', 'present', 1, '2026-09-22 10:53:48'),
(16, 8, '2026-09-22', 'present', 1, '2026-09-22 10:53:50');

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

CREATE TABLE `class` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `grade` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`class_id`, `class_name`, `grade`, `created_at`) VALUES
(1, 'A', '6', '2026-09-15 05:13:03'),
(2, 'B', '6', '2026-09-15 05:13:03'),
(3, 'A', '7', '2026-09-15 05:13:03'),
(4, 'B', '7', '2026-09-15 05:13:03'),
(5, 'A', '8', '2026-09-15 05:13:03'),
(6, 'A', '9', '2026-09-15 05:13:03'),
(7, 'A', '10', '2026-09-15 05:13:03'),
(8, 'B', '10', '2026-09-15 05:13:03'),
(9, 'A', '11', '2026-09-15 05:13:03'),
(10, 'B', '11', '2026-09-15 05:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `holidays`
--

CREATE TABLE `holidays` (
  `holiday_id` int(11) NOT NULL,
  `holiday_date` date NOT NULL,
  `description` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `holidays`
--

INSERT INTO `holidays` (`holiday_id`, `holiday_date`, `description`, `created_at`) VALUES
(2, '2026-01-15', 'Tamil Thai Pongal Day', '2026-09-15 05:13:03'),
(3, '2026-02-04', 'National Day', '2026-09-15 05:13:03'),
(4, '2026-04-13', 'Day prior to Sinhala & Tamil New Year', '2026-09-15 05:13:03'),
(5, '2026-04-14', 'Sinhala & Tamil New Year', '2026-09-15 05:13:03'),
(6, '2026-05-01', 'May Day', '2026-09-15 05:13:03'),
(7, '2026-12-25', 'Christmas Day', '2026-09-15 05:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `principal`
--

CREATE TABLE `principal` (
  `p_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(30) DEFAULT 'principal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `principal`
--

INSERT INTO `principal` (`p_id`, `username`, `name`, `email`, `contact`, `password`, `role`, `created_at`) VALUES
(1, 'principal', 'Mr. Kamal Perera', 'principal1@gmail.com', '0771111111', 'p123', 'principal', '2026-09-15 05:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `school_settings`
--

CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `school_settings`
--

INSERT INTO `school_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'working_days', '1,2,3,4,5', '2026-09-16 13:19:36');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT 'Male',
  `contact` varchar(30) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `name`, `gender`, `contact`, `date_of_birth`, `address`, `class_id`, `password`, `created_at`) VALUES
(1, 'Ahamed Rizwan', 'Male', '0711111111', '2010-03-15', 'No. 12, Main Street, Colombo', 1, '$2y$10$q7UAX/QzbGFGjwrgmVP0hObFPcMXT5LlEoQdnpnQMKyOuk9QzR.Aa', '2026-09-15 05:13:03'),
(2, 'Buddhika Perera', 'Male', '0712222222', '2010-05-22', 'No. 45, Temple Road, Kandy', 1, 'student123', '2026-09-15 05:13:03'),
(3, 'Chamodi Fernando', 'Female', '0713333333', '2010-07-10', 'No. 8, Lake Road, Galle', 1, 'student123', '2026-09-15 05:13:03'),
(4, 'Dinesh Kumar', 'Male', '0714444444', '2010-01-30', 'No. 23, Hill Street, Nuwara', 1, 'student123', '2026-09-15 05:13:03'),
(5, 'Erandi Silva', 'Female', '0715555555', '2010-11-05', 'No. 67, Park Avenue, Negombo', 1, 'student123', '2026-09-15 05:13:03'),
(6, 'Fathima Nazeer', 'Female', '0716666666', '2010-09-18', 'No. 90, Beach Road, Moratuwa', 1, 'student123', '2026-09-15 05:13:03'),
(7, 'Gayan Jayasuriya', 'Male', '0717777777', '2010-04-25', 'No. 15, Flower Road, Colombo', 1, 'student123', '2026-09-15 05:13:03'),
(8, 'Hasini Wickrama', 'Female', '0718888888', '2010-12-08', 'No. 32, Station Road, Kurunegala', 1, 'student123', '2026-09-15 05:13:03'),
(9, 'shehan sumith', 'Male', '0711111111', '2005-07-10', 'No. 12, Main Street, Colombo', 4, NULL, '2026-09-22 10:59:07');

-- --------------------------------------------------------

--
-- Table structure for table `subject`
--

CREATE TABLE `subject` (
  `subject_id` int(11) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subject`
--

INSERT INTO `subject` (`subject_id`, `subject_name`, `code`, `class_id`, `created_at`) VALUES
(1, 'Mathematics', 'MAT101', 1, '2026-09-15 05:13:03'),
(2, 'Science', 'SCI101', 1, '2026-09-15 05:13:03'),
(3, 'English', 'ENG101', 1, '2026-09-15 05:13:03'),
(4, 'Sinhala', 'SIN101', 1, '2026-09-15 05:13:03'),
(5, 'History', 'HIS101', 1, '2026-09-15 05:13:03'),
(6, 'ICT', 'ICT101', NULL, '2026-09-15 05:13:03'),
(7, 'Religion', 'REL101', NULL, '2026-09-15 05:13:03'),
(8, 'Health Science', 'HLT101', NULL, '2026-09-15 05:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `teacher`
--

CREATE TABLE `teacher` (
  `teacher_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `username` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher`
--

INSERT INTO `teacher` (`teacher_id`, `name`, `email`, `contact`, `subject`, `password`, `class_id`, `username`, `created_at`) VALUES
(1, 'Mrs. Nimali Silva', 'teacher1@centralcollege.lk', '0772222222', 'Mathematics', 'teacher123', 1, 'teacher1@centralcollege.lk', '2026-09-15 05:13:03'),
(2, 'Mr. Suresh Fernando', 'teacher2@centralcollege.lk', '0773333333', 'Science', 'teacher123', 3, 'teacher2@centralcollege.lk', '2026-09-15 05:13:03'),
(3, 'Mrs. Dilhani Jaya', 'teacher3@centralcollege.lk', '0774444444', 'English', 'teacher123', 5, 'teacher3@centralcollege.lk', '2026-09-15 05:13:03'),
(4, 'chathu', 'chathu@gmail.com', '0711111111', 'Dancing', '$2y$10$Mr920NsaCiPb.h/G2lE1FO7KeVZU78OxRPmI0Qn9rD9qJSgaOwBsa', 4, 'chathu@gmail.com', '2026-09-22 11:00:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `unique_student_date` (`student_id`,`date`),
  ADD KEY `fk_attendance_class` (`class_id`),
  ADD KEY `idx_attendance_date` (`date`),
  ADD KEY `idx_attendance_student` (`student_id`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`class_id`);

--
-- Indexes for table `holidays`
--
ALTER TABLE `holidays`
  ADD PRIMARY KEY (`holiday_id`),
  ADD UNIQUE KEY `holiday_date` (`holiday_date`);

--
-- Indexes for table `principal`
--
ALTER TABLE `principal`
  ADD PRIMARY KEY (`p_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `school_settings`
--
ALTER TABLE `school_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_key` (`setting_key`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`),
  ADD KEY `idx_student_class` (`class_id`);

--
-- Indexes for table `subject`
--
ALTER TABLE `subject`
  ADD PRIMARY KEY (`subject_id`),
  ADD KEY `idx_subject_class` (`class_id`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_teacher_class` (`class_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `holidays`
--
ALTER TABLE `holidays`
  MODIFY `holiday_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `principal`
--
ALTER TABLE `principal`
  MODIFY `p_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `school_settings`
--
ALTER TABLE `school_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student`
--
ALTER TABLE `student`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `subject`
--
ALTER TABLE `subject`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `teacher`
--
ALTER TABLE `teacher`
  MODIFY `teacher_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `fk_student_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `subject`
--
ALTER TABLE `subject`
  ADD CONSTRAINT `fk_subject_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `fk_teacher_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
