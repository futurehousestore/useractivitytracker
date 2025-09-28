
-- Install DDL for alt_user_activity (prefix aware via module init)
-- Version: 1.0.0
CREATE TABLE llx_alt_user_activity (
  rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
  tms TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  datestamp DATETIME NULL,
  entity INTEGER NOT NULL DEFAULT 1,
  action VARCHAR(128) NOT NULL,
  element_type VARCHAR(64) NULL,
  object_id INTEGER NULL,
  ref VARCHAR(128) NULL,
  userid INTEGER NULL,
  username VARCHAR(128) NULL,
  ip VARCHAR(64) NULL,
  payload LONGTEXT NULL,
  severity VARCHAR(16) NULL,
  kpi1 DECIMAL(24,6) NULL,
  kpi2 DECIMAL(24,6) NULL,
  note VARCHAR(255) NULL,
  INDEX idx_action (action),
  INDEX idx_element (element_type, object_id),
  INDEX idx_user (userid),
  INDEX idx_datestamp (datestamp),
  INDEX idx_entity (entity)
) ENGINE=innodb;
