-- database/schema.sql : canonical fresh-install, never import into old DB unless replacing
CREATE DATABASE IF NOT EXISTS lcc_compiler CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lcc_compiler;
CREATE TABLE IF NOT EXISTS snapshots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  class_code VARCHAR(50) NOT NULL,
  filename VARCHAR(255) NOT NULL DEFAULT '',
  language VARCHAR(20) NOT NULL,
  code MEDIUMTEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_snap_file (student_name, class_code, filename),
  KEY idx_class (class_code, updated_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS test_cases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_code VARCHAR(50) NOT NULL,
  language VARCHAR(20) NOT NULL,
  stdin MEDIUMTEXT NOT NULL,
  expected_stdout MEDIUMTEXT NOT NULL,
  KEY idx_tc (class_code, language)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_name VARCHAR(100) NOT NULL,
  class_code VARCHAR(50) NOT NULL,
  filename VARCHAR(255) NOT NULL DEFAULT '',
  language VARCHAR(20) NOT NULL,
  code MEDIUMTEXT NOT NULL,
  output MEDIUMTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sub (class_code, created_at)
) ENGINE=InnoDB;
