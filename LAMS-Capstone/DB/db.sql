
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    role ENUM('Admin') NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_number` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `program` varchar(100) NOT NULL,
  `year_level` varchar(15) NOT NULL,
  `block` varchar(15) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
 `picture` VARCHAR(255) DEFAULT NULL AFTER `block`,
 `pin_code` VARCHAR(6) DEFAULT NULL AFTER `picture`;
 `registration_status` varchar(20) NOT NULL DEFAULT 'Unregistered';
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create faculty table if it doesn't exist
CREATE TABLE IF NOT EXISTS `faculty` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `faculty_number` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `program` varchar(100) NOT NULL,
  `picture` varchar(255) DEFAULT NULL AFTER `program`,
  `pincode` varchar(10) DEFAULT NULL AFTER `picture`,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `registration_status` varchar(20) NOT NULL DEFAULT 'Unregistered';
  PRIMARY KEY (`id`),
  UNIQUE KEY `faculty_number` (`faculty_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_number` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `role` varchar(100) NOT NULL,
 `picture` LONGTEXT NULL AFTER `role`,
 `pincode` VARCHAR(10) NULL AFTER `picture`,
  `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `registration_status` varchar(20) NOT NULL DEFAULT 'Unregistered';
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_number` (`staff_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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

-- Create faculty_attendance table
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

-- Create staff_attendance table
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

CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time DATETIME NOT NULL,
    logout_time DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id)
);


npm install -g ngrok