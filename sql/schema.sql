CREATE DATABASE IF NOT EXISTS local_jobs
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE local_jobs;

-- Users (all roles)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('seeker','employer','admin') NOT NULL,
    status ENUM('pending','active','rejected') DEFAULT 'active',
    company_name VARCHAR(150) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Jobs
CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(80) NOT NULL,
    location VARCHAR(120) NOT NULL DEFAULT 'Nichlaul',
    salary VARCHAR(80) DEFAULT NULL,
    job_type ENUM('full-time','part-time','contract','daily-wage') DEFAULT 'full-time',
    contact_phone VARCHAR(15) DEFAULT NULL,
    status ENUM('pending','active','closed','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Applications
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    seeker_id INT NOT NULL,
    resume_file VARCHAR(255) NOT NULL,
    cover_letter TEXT DEFAULT NULL,
    status ENUM('pending','shortlisted','rejected','hired') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (seeker_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_app (job_id, seeker_id)
);

-- KYC (optional, for future use)
CREATE TABLE IF NOT EXISTS kyc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    doc_type VARCHAR(50) NOT NULL,
    doc_file VARCHAR(255) NOT NULL,
    status ENUM('pending','verified','rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default admin (password: admin123)
INSERT INTO users (name, email, phone, password, role, status)
SELECT 'Admin', 'admin@localjobs.com', '0000000000',
       '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlVxyw0T6t7B9b7lC1vK9q1H9L5vGa',
       'admin', 'active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE role='admin');