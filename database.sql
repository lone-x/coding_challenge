CREATE DATABASE coding_challenge;
USE coding_challenge;

CREATE TABLE contestants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_number INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    challenge_type VARCHAR(50) NOT NULL
);

CREATE TABLE progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contestant_id INT,
    level_id INT,
    start_time TIMESTAMP NULL,
    completion_time TIMESTAMP NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (contestant_id) REFERENCES contestants(id),
    FOREIGN KEY (level_id) REFERENCES levels(id)
);

CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);
