-- Copyright (C) 2026 Zachary Melo
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_bulkbreakdown_rule(
	rowid              INTEGER       AUTO_INCREMENT PRIMARY KEY,
	entity             INTEGER       NOT NULL DEFAULT 1,
	fk_product         INTEGER       NOT NULL,
	fk_bom             INTEGER       NOT NULL,
	fk_warehouse       INTEGER       NULL,
	active             TINYINT       NOT NULL DEFAULT 1,
	note               TEXT,
	date_creation      DATETIME      NOT NULL,
	tms                TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat      INTEGER       NOT NULL,
	fk_user_modif      INTEGER
) ENGINE=innodb;
