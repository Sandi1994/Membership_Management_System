-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 08, 2026 at 10:35 AM
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
-- Database: `membership_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `doc_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `doc_type` varchar(255) NOT NULL,
  `file_path` int(11) NOT NULL,
  `uploaded_date` datetime NOT NULL,
  `remarks` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_name` varchar(100) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_value` varchar(255) DEFAULT NULL,
  `new_value` varchar(255) DEFAULT NULL,
  `logged_at` datetime NOT NULL,
  `logged_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_batch_job`
--

CREATE TABLE `daily_batch_job` (
  `job_id` int(11) NOT NULL,
  `job_name` varchar(255) NOT NULL,
  `scheduled_run` datetime NOT NULL,
  `last_run` datetime NOT NULL,
  `status` enum('Active','Inactive','Running','Completed','Failed') NOT NULL,
  `run_by` int(11) NOT NULL,
  `status_message` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_archive`
--

CREATE TABLE `email_archive` (
  `email_id` int(11) NOT NULL,
  `et_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subscription_id` int(11) DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL,
  `recipeint_email` varchar(255) NOT NULL,
  `sent_at` datetime NOT NULL,
  `sent_by` int(11) NOT NULL,
  `delivery_status` enum('Sent','Failed','Pending','') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_template`
--

CREATE TABLE `email_template` (
  `et_id` int(11) NOT NULL,
  `et_title` int(11) NOT NULL,
  `et_description` varchar(255) NOT NULL,
  `et_body` varchar(300) NOT NULL,
  `et_type` enum('Member Application Submitted','Payment Success','Payment Declined','Renewal - 30 days','Renewal - 7 days','Renewal - 1 day','Membership Cancelled','Member Application Approved','Membership Reactivated','Membership Upgrade','Member Registration') NOT NULL,
  `created_at` datetime NOT NULL,
  `created_by` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `member_id` int(11) NOT NULL,
  `membership_no` int(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `f_name` varchar(255) NOT NULL,
  `l_name` varchar(255) NOT NULL,
  `dob` date NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `profession` varchar(255) NOT NULL,
  `organisation` varchar(255) NOT NULL,
  `joined_date` datetime NOT NULL,
  `gender` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_application`
--

CREATE TABLE `membership_application` (
  `application_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `m_category_id` int(11) NOT NULL,
  `m_status` enum('Active','Inactive','Pending','Deleted') NOT NULL,
  `qualification` varchar(255) NOT NULL,
  `industry_experience` varchar(255) NOT NULL,
  `submitted_date` datetime NOT NULL,
  `checked_by` int(11) NOT NULL,
  `checked_date` datetime NOT NULL,
  `approved_by` int(11) NOT NULL,
  `approved_date` datetime NOT NULL,
  `remarks` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_category`
--

CREATE TABLE `membership_category` (
  `category_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `annual_fee` decimal(10,2) NOT NULL,
  `elgibility_notes` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `membership_subscription`
--

CREATE TABLE `membership_subscription` (
  `subscription_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `s_type` enum('New','Renewal','Upgrade','Reactivation') NOT NULL,
  `s_status` enum('Active','Expired','Lapsed','Cancelled','Inactive','Pending') NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `record_created_date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `pm_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_no` varchar(50) NOT NULL,
  `slip_path` varchar(255) NOT NULL,
  `payemnt_date` datetime NOT NULL,
  `status` enum('Success','Pending','On Hold','Decline') NOT NULL,
  `verified_by` int(11) NOT NULL,
  `verified_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_method`
--

CREATE TABLE `payment_method` (
  `pm_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_receipts`
--

CREATE TABLE `payment_receipts` (
  `receipt_id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `receipt_no` varchar(30) NOT NULL,
  `issued_at` datetime NOT NULL,
  `issued_by` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `f_name` varchar(255) NOT NULL,
  `l_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL,
  `created_date` datetime NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_role`
--

CREATE TABLE `user_role` (
  `role_id` int(100) NOT NULL,
  `role_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `application_documents_ibfk_1` (`application_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `logged_by` (`logged_by`);

--
-- Indexes for table `daily_batch_job`
--
ALTER TABLE `daily_batch_job`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `run_by` (`run_by`);

--
-- Indexes for table `email_archive`
--
ALTER TABLE `email_archive`
  ADD PRIMARY KEY (`email_id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `et_id` (`et_id`),
  ADD KEY `email_archive_ibfk_3` (`subscription_id`),
  ADD KEY `email_archive_ibfk_4` (`user_id`),
  ADD KEY `sent_by` (`sent_by`);

--
-- Indexes for table `email_template`
--
ALTER TABLE `email_template`
  ADD PRIMARY KEY (`et_id`),
  ADD KEY `email_template_ibfk_1` (`created_by`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `members_ibfk_1` (`user_id`);

--
-- Indexes for table `membership_application`
--
ALTER TABLE `membership_application`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `membership_application_ibfk_1` (`user_id`),
  ADD KEY `membership_application_ibfk_2` (`member_id`),
  ADD KEY `membership_application_ibfk_3` (`checked_by`),
  ADD KEY `membership_application_ibfk_4` (`approved_by`);

--
-- Indexes for table `membership_category`
--
ALTER TABLE `membership_category`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `membership_subscription`
--
ALTER TABLE `membership_subscription`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `membership_subscription_ibfk_1` (`category_id`),
  ADD KEY `membership_subscription_ibfk_2` (`member_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `payments_ibfk_1` (`pm_id`),
  ADD KEY `payments_ibfk_2` (`subscription_id`),
  ADD KEY `payments_ibfk_3` (`verified_by`);

--
-- Indexes for table `payment_method`
--
ALTER TABLE `payment_method`
  ADD PRIMARY KEY (`pm_id`);

--
-- Indexes for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD PRIMARY KEY (`receipt_id`),
  ADD KEY `payment_receipts_ibfk_1` (`payment_id`),
  ADD KEY `payment_receipts_ibfk_2` (`issued_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `users_ibfk_1` (`role_id`);

--
-- Indexes for table `user_role`
--
ALTER TABLE `user_role`
  ADD PRIMARY KEY (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_batch_job`
--
ALTER TABLE `daily_batch_job`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_archive`
--
ALTER TABLE `email_archive`
  MODIFY `email_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_template`
--
ALTER TABLE `email_template`
  MODIFY `et_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `membership_application`
--
ALTER TABLE `membership_application`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `membership_category`
--
ALTER TABLE `membership_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `membership_subscription`
--
ALTER TABLE `membership_subscription`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_method`
--
ALTER TABLE `payment_method`
  MODIFY `pm_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  MODIFY `receipt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_role`
--
ALTER TABLE `user_role`
  MODIFY `role_id` int(100) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD CONSTRAINT `application_documents_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `membership_application` (`application_id`) ON UPDATE CASCADE;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `audit_log_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `daily_batch_job` (`job_id`),
  ADD CONSTRAINT `audit_log_ibfk_3` FOREIGN KEY (`logged_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `daily_batch_job`
--
ALTER TABLE `daily_batch_job`
  ADD CONSTRAINT `daily_batch_job_ibfk_1` FOREIGN KEY (`run_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `email_archive`
--
ALTER TABLE `email_archive`
  ADD CONSTRAINT `email_archive_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `email_archive_ibfk_2` FOREIGN KEY (`et_id`) REFERENCES `email_template` (`et_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `email_archive_ibfk_3` FOREIGN KEY (`subscription_id`) REFERENCES `membership_subscription` (`subscription_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `email_archive_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `email_archive_ibfk_5` FOREIGN KEY (`sent_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `email_template`
--
ALTER TABLE `email_template`
  ADD CONSTRAINT `email_template_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `membership_application`
--
ALTER TABLE `membership_application`
  ADD CONSTRAINT `membership_application_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `membership_application_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `membership_application_ibfk_3` FOREIGN KEY (`checked_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `membership_application_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `membership_subscription`
--
ALTER TABLE `membership_subscription`
  ADD CONSTRAINT `membership_subscription_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `membership_category` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `membership_subscription_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`pm_id`) REFERENCES `payment_method` (`pm_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `membership_subscription` (`subscription_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `payment_receipts`
--
ALTER TABLE `payment_receipts`
  ADD CONSTRAINT `payment_receipts_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `payment_receipts_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `user_role` (`role_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
