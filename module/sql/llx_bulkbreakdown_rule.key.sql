-- Copyright (C) 2026 Zachary Melo
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_bulkbreakdown_rule ADD UNIQUE INDEX uk_bulkbreakdown_rule_product (fk_product, entity);
ALTER TABLE llx_bulkbreakdown_rule ADD INDEX idx_bulkbreakdown_rule_bom (fk_bom);
ALTER TABLE llx_bulkbreakdown_rule ADD CONSTRAINT fk_bulkbreakdown_rule_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid);
ALTER TABLE llx_bulkbreakdown_rule ADD CONSTRAINT fk_bulkbreakdown_rule_bom FOREIGN KEY (fk_bom) REFERENCES llx_bom_bom(rowid);
