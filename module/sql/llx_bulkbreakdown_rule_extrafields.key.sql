-- Copyright (C) 2026 Zachary Melo
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_bulkbreakdown_rule_extrafields ADD INDEX idx_bulkbreakdown_rule_extrafields_fk (fk_object);
