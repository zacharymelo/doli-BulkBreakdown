-- Copyright (C) 2026 Zachary Melo
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_bulkbreakdown_rule_extrafields(
	rowid              INTEGER       AUTO_INCREMENT PRIMARY KEY,
	tms                TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_object          INTEGER       NOT NULL,
	import_key         VARCHAR(14)
) ENGINE=innodb;
