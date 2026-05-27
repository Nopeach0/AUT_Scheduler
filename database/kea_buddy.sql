CREATE DATABASE IF NOT EXISTS kea_buddy;
USE kea_buddy;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    schedule_visibility VARCHAR(10) NOT NULL DEFAULT 'public',
    display_name VARCHAR(100) NULL DEFAULT NULL,
    bio VARCHAR(300) NULL DEFAULT NULL,
    avatar_path VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS calendar_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    note_date DATE NOT NULL,
    note_time VARCHAR(5) NOT NULL DEFAULT '09:00',
    category ENUM('assignment','meeting','study','other') NOT NULL DEFAULT 'other',
    repeat_rule ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
    reminder_minutes INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, note_date)
);

CREATE TABLE IF NOT EXISTS friendships (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    addressee_id INT NOT NULL,
    status       VARCHAR(10) NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pair (requester_id, addressee_id)
);
