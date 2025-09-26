CREATE DATABASE library_attendance_system;

Use library_attendance_system;


-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('Admin') NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    pincode VARCHAR(6) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Students table
CREATE TABLE `students` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_number` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `program` VARCHAR(100) NOT NULL,
    `year_level` VARCHAR(15) NOT NULL,
    `block` VARCHAR(15) NOT NULL,
    `picture` VARCHAR(255) DEFAULT NULL,
    `pin_code` VARCHAR(6) DEFAULT NULL,
    `registration_status` VARCHAR(20) NOT NULL DEFAULT 'Unregistered',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    'expiration_date' DATE NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `student_number` (`student_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Faculty table
CREATE TABLE IF NOT EXISTS `faculty` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `faculty_number` VARCHAR(20) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `program` VARCHAR(100) NOT NULL,
    `picture` VARCHAR(255) DEFAULT NULL,
    `pincode` VARCHAR(10) DEFAULT NULL,
    `registration_status` VARCHAR(20) NOT NULL DEFAULT 'Unregistered',
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    'expiration_date' DATE NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `faculty_number` (`faculty_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Staff table
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `staff_number` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `role` VARCHAR(100) NOT NULL,
    `picture` LONGTEXT NULL,
    `pincode` VARCHAR(10) NULL,
    `registration_status` VARCHAR(20) NOT NULL DEFAULT 'Unregistered',
    `date_created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    'expiration_date' DATE NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `staff_number` (`staff_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Student attendance
CREATE TABLE student_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_number VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    program VARCHAR(100) NOT NULL,
    block VARCHAR(10) NOT NULL,
    year VARCHAR(10) NOT NULL,
    time_in DATETIME,
    time_out DATETIME,
    log_date DATE NOT NULL,
    INDEX idx_student_number (student_number),
    INDEX idx_department (department),
    INDEX idx_program (program),
    INDEX idx_log_date (log_date)
);

-- Faculty attendance
CREATE TABLE faculty_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faculty_number VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    program VARCHAR(100),
    time_in DATETIME,
    time_out DATETIME,
    log_date DATE NOT NULL,
    INDEX idx_faculty_number (faculty_number),
    INDEX idx_department (department),
    INDEX idx_log_date (log_date)
);

-- Staff attendance
CREATE TABLE staff_attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_number VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    role VARCHAR(100) NOT NULL,
    time_in DATETIME,
    time_out DATETIME,
    log_date DATE NOT NULL,
    INDEX idx_staff_number (staff_number),
    INDEX idx_department (department),
    INDEX idx_role (role),
    INDEX idx_log_date (log_date)
);

-- Login logs
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time DATETIME NOT NULL,
    logout_time DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);


