-- Installation DDL for User Activity Tracker
-- Creates canonical table names: useractivitytracker_activity and useractivitytracker_log

-- Primary event store
CREATE TABLE IF NOT EXISTS llx_useractivitytracker_activity (
  rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  datestamp DATETIME NOT NULL,
  fk_user INTEGER NULL,
  username VARCHAR(128) NULL,
  action VARCHAR(64) NOT NULL,
  element_type VARCHAR(64) NULL,
  element_id INTEGER NULL,
  ref VARCHAR(255) NULL,
  severity ENUM('info','notice','warning','error') NOT NULL DEFAULT 'info',
  ip VARCHAR(64) NULL,
  ua VARCHAR(255) NULL,
  uri TEXT NULL,
  kpi1 BIGINT NULL,
  kpi2 BIGINT NULL,
  note TEXT NULL,
  entity INTEGER NOT NULL DEFAULT 1,
  extraparams TEXT NULL,
  KEY idx_entity_date (entity, datestamp),
  KEY idx_action (action),
  KEY idx_user (username),
  KEY idx_element (element_type, element_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional raw page-hit log (if you keep it)
CREATE TABLE IF NOT EXISTS llx_useractivitytracker_log (
  rowid BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  datestamp DATETIME NOT NULL,
  fk_user INTEGER NULL,
  username VARCHAR(128) NULL,
  uri TEXT NULL,
  ip VARCHAR(64) NULL,
  ua VARCHAR(255) NULL,
  entity INTEGER NOT NULL DEFAULT 1,
  KEY idx_entity_date (entity, datestamp),
  KEY idx_user (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
