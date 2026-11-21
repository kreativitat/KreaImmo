ALTER TABLE llx_c_kreaimmo_boxes ADD UNIQUE INDEX uk_boxes (entity, box_id, position, fk_user);
ALTER TABLE llx_c_kreaimmo_boxes ADD INDEX idx_boxes_boxid (box_id);
ALTER TABLE llx_c_kreaimmo_boxes ADD INDEX idx_boxes_fk_user (fk_user);
ALTER TABLE llx_c_kreaimmo_boxes ADD CONSTRAINT fk_kreaimmo_boxes_box_id FOREIGN KEY (box_id) REFERENCES llx_c_kreaimmo_boxes_def (rowid);
