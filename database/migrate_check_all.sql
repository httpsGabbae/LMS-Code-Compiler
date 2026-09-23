USE lcc_compiler;
ALTER TABLE snapshots ADD COLUMN filename VARCHAR(255) NOT NULL DEFAULT '' AFTER class_code;
UPDATE snapshots SET filename='main.py' WHERE language='python' AND filename='';
UPDATE snapshots SET filename='main.js' WHERE language='javascript' AND filename='';
UPDATE snapshots SET filename='main.php' WHERE language='php' AND filename='';
UPDATE snapshots SET filename='Main.java' WHERE language='java' AND filename='';
UPDATE snapshots SET filename='main.cpp' WHERE language='cpp' AND filename='';
UPDATE snapshots SET filename='main.c' WHERE language='c' AND filename='';
UPDATE snapshots SET filename='main.txt' WHERE filename='';
ALTER TABLE snapshots DROP INDEX uq_snap;
ALTER TABLE snapshots ADD UNIQUE KEY uq_snap_file (student_name, class_code, filename);
CREATE TABLE IF NOT EXISTS test_cases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_code VARCHAR(50) NOT NULL,
  language VARCHAR(20) NOT NULL,
  stdin MEDIUMTEXT NOT NULL,
  expected_stdout MEDIUMTEXT NOT NULL,
  KEY idx_tc (class_code, language)
) ENGINE=InnoDB;
ALTER TABLE submissions ADD COLUMN filename VARCHAR(255) NOT NULL DEFAULT '' AFTER class_code;
UPDATE submissions SET filename='main.py' WHERE language='python' AND filename='';
UPDATE submissions SET filename='main.js' WHERE language='javascript' AND filename='';
UPDATE submissions SET filename='main.php' WHERE language='php' AND filename='';
UPDATE submissions SET filename='Main.java' WHERE language='java' AND filename='';
UPDATE submissions SET filename='main.cpp' WHERE language='cpp' AND filename='';
UPDATE submissions SET filename='main.c' WHERE language='c' AND filename='';
UPDATE submissions SET filename='main.txt' WHERE filename='';
