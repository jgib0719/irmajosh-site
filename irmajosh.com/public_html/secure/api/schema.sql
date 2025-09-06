-- api/schema.sql (clean, matches live DB + required app fields)

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  google_sub VARCHAR(64) NOT NULL,
  email VARCHAR(255),
  name VARCHAR(255),
  created_at DATETIME NOT NULL,
  UNIQUE KEY idx_users_google_sub (google_sub)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  notes TEXT,
  date DATE NULL,
  time TIME NULL,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  is_urgent TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('pending','scheduled','cancelled') NOT NULL DEFAULT 'pending',
  requested_by VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  notes TEXT,
  status ENUM('available','accepted','scheduled','cancelled') NOT NULL DEFAULT 'available',
  posted_by VARCHAR(64) NULL,
  accepted_by VARCHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  date DATE NOT NULL,
  time TIME NULL,
  notes TEXT,
  is_done TINYINT(1) NOT NULL DEFAULT 0,
  is_urgent TINYINT(1) NOT NULL DEFAULT 0,
  source_type ENUM('request','job') NULL,
  source_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
