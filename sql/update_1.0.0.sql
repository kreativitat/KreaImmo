-- Copyright (C) 2026 Kreativität Works <mail@kreativitat.com>
-- SPDX-License-Identifier: GPL-3.0-or-later
-- Native run_sql accepts already-existing columns on replay.
ALTER TABLE llx_kreaimmo_immoproperty ADD COLUMN section_cadastrale varchar(32);
ALTER TABLE llx_kreaimmo_immoproperty ADD COLUMN parcelle_cadastrale varchar(32);
ALTER TABLE llx_kreaimmo_immoproperty ADD COLUMN num_prm_edf varchar(32);
ALTER TABLE llx_kreaimmo_immoproperty ADD COLUMN num_internet_line varchar(32);
ALTER TABLE llx_kreaimmo_immorent MODIFY COLUMN rentamount decimal(24,8);
ALTER TABLE llx_kreaimmo_immorent MODIFY COLUMN chargesamount decimal(24,8);
ALTER TABLE llx_kreaimmo_immorent MODIFY COLUMN totalamount decimal(24,8);
ALTER TABLE llx_kreaimmo_immorent MODIFY COLUMN deposit decimal(24,8);
ALTER TABLE llx_kreaimmo_immorent MODIFY COLUMN encours decimal(24,8);
ALTER TABLE llx_kreaimmo_immocost MODIFY COLUMN tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_kreaimmo_immopayment MODIFY COLUMN tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_kreaimmo_immocost_type MODIFY COLUMN tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_kreaimmo_immorenter_type MODIFY COLUMN tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE llx_kreaimmo_immopayment ADD COLUMN fk_bank integer;

UPDATE llx_c_kreaimmo_immorent_type SET entity = 0 WHERE rowid = 1 AND code = 'EMPTY_HOUSING' AND label = 'Logement vide' AND entity = 1;
UPDATE llx_c_kreaimmo_immorent_type SET entity = 0 WHERE rowid = 2 AND code = 'FURNISHED_APARTMENT' AND label = 'Logement meublé' AND entity = 1;
UPDATE llx_c_kreaimmo_immoproperty_type SET entity = 0 WHERE rowid = 1 AND code = 'APA' AND label = 'Apartment' AND entity = 1;
UPDATE llx_c_kreaimmo_immoproperty_type SET entity = 0 WHERE rowid = 2 AND code = 'HOU' AND label = 'Individual house' AND entity = 1;
UPDATE llx_c_kreaimmo_immoproperty_type SET entity = 0 WHERE rowid = 3 AND code = 'LOC' AND label = 'Business premises' AND entity = 1;
UPDATE llx_c_kreaimmo_immoproperty_type SET entity = 0 WHERE rowid = 4 AND code = 'SHO' AND label = 'Shop' AND entity = 1;
UPDATE llx_c_kreaimmo_immoproperty_type SET entity = 0 WHERE rowid = 5 AND code = 'GAR' AND label = 'Garage' AND entity = 1;
UPDATE llx_c_kreaimmo_immoproperty_type SET entity = 0 WHERE rowid = 6 AND code = 'BUL' AND label = 'Building' AND entity = 1;
UPDATE llx_c_kreaimmo_juridique SET entity = 0 WHERE rowid = 1 AND code = 'MONO_PROP' AND label = 'Mono propriété' AND entity = 1;
UPDATE llx_c_kreaimmo_juridique SET entity = 0 WHERE rowid = 2 AND code = 'CO_PROP' AND label = 'Copropriété' AND entity = 1;
UPDATE llx_c_kreaimmo_builtdate SET entity = 0 WHERE rowid = 1 AND code = 'BUILT1' AND label = 'avant 1949' AND entity = 1;
UPDATE llx_c_kreaimmo_builtdate SET entity = 0 WHERE rowid = 2 AND code = 'BUILT2' AND label = 'de 1949 à 1974' AND entity = 1;
UPDATE llx_c_kreaimmo_builtdate SET entity = 0 WHERE rowid = 3 AND code = 'BUILT3' AND label = 'de 1975 à 1989' AND entity = 1;
UPDATE llx_c_kreaimmo_builtdate SET entity = 0 WHERE rowid = 4 AND code = 'BUILT4' AND label = 'de 1989 à 2005' AND entity = 1;
UPDATE llx_c_kreaimmo_builtdate SET entity = 0 WHERE rowid = 5 AND code = 'BUILT5' AND label = 'depuis 2005' AND entity = 1;

-- Annual meter costs are company data even when their reference type is shared.
ALTER TABLE llx_kreaimmo_immocompteur_cost ADD COLUMN entity integer DEFAULT NULL;
UPDATE llx_kreaimmo_immocompteur_cost AS c LEFT JOIN llx_c_kreaimmo_immocompteur_type AS p ON p.rowid = c.fk_immocompteur_type SET c.entity = CASE WHEN p.entity > 0 THEN p.entity ELSE 0 END WHERE c.entity IS NULL;
ALTER TABLE llx_kreaimmo_immocompteur_cost MODIFY COLUMN entity integer NOT NULL DEFAULT 1;
