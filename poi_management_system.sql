-- Clean schema only. Deliberately contains no users, passwords, POIs, coordinates or activity data.
CREATE DATABASE IF NOT EXISTS poi_management_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE poi_management_system;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(32) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','worker') NOT NULL DEFAULT 'worker',
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pois (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address VARCHAR(500) NOT NULL DEFAULT '',
  type VARCHAR(50) NOT NULL,
  lat DECIMAL(10,8) NULL,
  lng DECIMAL(11,8) NULL,
  radius INT UNSIGNED NULL,
  geometry_type ENUM('radius','polygon') NOT NULL,
  coordinates JSON NULL,
  company ENUM('lumiere','gias') NOT NULL DEFAULT 'lumiere',
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pois_type (type),
  CONSTRAINT fk_pois_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action_type VARCHAR(50) NOT NULL,
  description VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_created (created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip_hash CHAR(64) NOT NULL,
  username_hash CHAR(64) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_attempt_ip_time (ip_hash, attempted_at),
  INDEX idx_login_attempt_user_time (username_hash, attempted_at)
) ENGINE=InnoDB;

-- Legacy zones data was excluded from the reviewed application APIs; add back only after migration planning.
