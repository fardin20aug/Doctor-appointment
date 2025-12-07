-- Database schema for Doctor Appointment System
-- You can import this into MySQL using phpMyAdmin or the mysql CLI.
-- Example:
--   CREATE DATABASE doctor_appointment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
--   USE doctor_appointment;
--   SOURCE schema.sql;

-- Drop existing tables if they exist (be careful in production)
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS doctors;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS users;

-- Core users table (covers doctors, patients, reception/admin)
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('doctor', 'patient', 'reception', 'admin') NOT NULL DEFAULT 'patient',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Doctors specific data (1:1 with users having role='doctor')
CREATE TABLE doctors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    chamber VARCHAR(190) DEFAULT NULL,
    experience_years INT UNSIGNED DEFAULT 0,
    consultation_fee DECIMAL(10,2) DEFAULT 0,
    available_from TIME DEFAULT NULL,
    available_to TIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_doctors_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Patients specific data (1:1 with users having role='patient')
CREATE TABLE patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    blood_group VARCHAR(5) DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_patients_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Appointments table
CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    appointment_date DATETIME NOT NULL,
    status ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_appointments_doctor (doctor_id),
    INDEX idx_appointments_patient (patient_id),
    INDEX idx_appointments_date (appointment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payments table (for paid appointments)
CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('cash','card','bkash','nagad','rocket','paypal','other') NOT NULL DEFAULT 'cash',
    status ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    transaction_ref VARCHAR(190) DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_payments_appointment (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Example seed data (optional)
INSERT INTO users (fullname, email, username, password_hash, role)
VALUES
('Demo Doctor', 'doctor@example.com', 'doctor1', 'CHANGE_ME_HASH', 'doctor'),
('Demo Patient', 'patient@example.com', 'patient1', 'CHANGE_ME_HASH', 'patient'),
('Reception User', 'reception@example.com', 'reception1', 'CHANGE_ME_HASH', 'reception');

-- After importing, you can update `password_hash` using PHP's password_hash()
-- or any hashing strategy you implement later in your PHP code.