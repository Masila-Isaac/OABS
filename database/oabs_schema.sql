-- ============================================================
-- OABS: Online Appointment Booking System for Transcript
-- Collection at the Co-operative University of Kenya
-- Database Schema (MySQL 8.0)
-- ============================================================

CREATE DATABASE IF NOT EXISTS oabs_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oabs_db;

-- ------------------------------------------------------------
-- Table: users
-- Stores students, alumni, Records Office staff, and admins
-- ------------------------------------------------------------
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    registration_number VARCHAR(50) NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'alumni', 'staff', 'admin') NOT NULL DEFAULT 'student',
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: appointment_slots
-- Created by staff/admin. Students book against these.
-- ------------------------------------------------------------
CREATE TABLE appointment_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    slot_date DATE NOT NULL,
    slot_time TIME NOT NULL,
    capacity INT NOT NULL DEFAULT 1,
    booked_count INT NOT NULL DEFAULT 0,
    status ENUM('available', 'full', 'closed') NOT NULL DEFAULT 'available',
    created_by INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_slot (slot_date, slot_time)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: appointments
-- A booking made by a student/alumni against a slot.
-- ------------------------------------------------------------
CREATE TABLE appointments (
    appointment_id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(20) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    slot_id INT NOT NULL,
    purpose VARCHAR(255) NULL,
    status ENUM('pending', 'confirmed', 'cancelled', 'collected', 'missed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES appointment_slots(slot_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: notifications
-- Log of every SMS/email sent by the system
-- ------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    type ENUM('sms', 'email') NOT NULL,
    recipient VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') NOT NULL DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: audit_logs
-- Tracks key actions for accountability
-- ------------------------------------------------------------
CREATE TABLE audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- NOTE: The admin account is NOT seeded here with a hardcoded
-- password hash (that would be unsafe/unreliable). Instead, run
-- database/seed_admin.php ONCE in your browser after importing
-- this file - it creates the admin account using PHP's
-- password_hash() function properly. Delete that file afterwards.
-- ------------------------------------------------------------

-- A couple of sample available slots so the booking page isn't empty on first run
INSERT INTO appointment_slots (slot_date, slot_time, capacity, created_by)
VALUES
    (CURDATE() + INTERVAL 3 DAY, '09:00:00', 5, 1),
    (CURDATE() + INTERVAL 3 DAY, '11:00:00', 5, 1),
    (CURDATE() + INTERVAL 4 DAY, '09:00:00', 5, 1);
