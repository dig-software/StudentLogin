-- Schema for StudentLogin Portal (prototype)
-- NOTE: Adjust engine/charset as needed (e.g., InnoDB, utf8mb4)

CREATE TABLE IF NOT EXISTS registration (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  reg_number VARCHAR(80) NOT NULL UNIQUE,
  phone VARCHAR(40) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  course VARCHAR(160) NOT NULL,
  profile_pic VARCHAR(255) DEFAULT NULL,
  bio TEXT DEFAULT NULL,
  password VARCHAR(255) NOT NULL,
  show_videos TINYINT(1) NOT NULL DEFAULT 1,
  last_active DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  INDEX (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webauthn_credentials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  credential_id VARCHAR(255) NOT NULL,
  public_key VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_cred (username, credential_id),
  INDEX (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  video_filename VARCHAR(255) NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  code VARCHAR(120) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (username),
  INDEX (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Messaging / group (minimal guesses based on file names) -- Adjust to your actual definitions.
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender VARCHAR(80) NOT NULL,
  receiver VARCHAR(80) NOT NULL,
  body TEXT NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (sender),
  INDEX (receiver)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_join_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  group_name VARCHAR(120) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (group_name),
  INDEX (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Example group membership table if desired
CREATE TABLE IF NOT EXISTS group_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL,
  group_name VARCHAR(120) NOT NULL,
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_member (username, group_name),
  INDEX (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional indexing improvements
-- ALTER TABLE webauthn_credentials ADD INDEX (credential_id);
