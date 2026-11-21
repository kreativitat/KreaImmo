-- ============================================================================
-- Copyright (C) 2014-2019   Philippe Grand		<philippe.grand@atoo-net.com>
-- Copyright (C) 2014-2017   Regis Houssin		<regis.houssin@capnetworks.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see <http://www.gnu.org/licenses/>.

INSERT IGNORE INTO llx_c_kreaimmo_immoreceipt_status(rowid,code,label,active) VALUES (1, 'STATUS_DRAFT', 'Brouillon', 1);
INSERT IGNORE INTO llx_c_kreaimmo_immoreceipt_status(rowid,code,label,active) VALUES (2, 'STATUS_VALIDATED', 'Validée', 1);

INSERT IGNORE INTO llx_c_kreaimmo_immorent_type(rowid,code,label,active,entity) VALUES (1, 'EMPTY_HOUSING', 'Logement vide', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_immorent_type(rowid,code,label,active,entity) VALUES (2, 'FURNISHED_APARTMENT', 'Logement meublé', 1, 0);

INSERT IGNORE INTO llx_c_kreaimmo_immoproperty_type(rowid,code,label,active,entity) VALUES (1, 'APA', 'Apartment', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_immoproperty_type(rowid,code,label,active,entity) VALUES (2, 'HOU', 'Individual house', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_immoproperty_type(rowid,code,label,active,entity) VALUES (3, 'LOC', 'Business premises', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_immoproperty_type(rowid,code,label,active,entity) VALUES (4, 'SHO', 'Shop', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_immoproperty_type(rowid,code,label,active,entity) VALUES (5, 'GAR', 'Garage', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_immoproperty_type(rowid,code,label,active,entity) VALUES (6, 'BUL', 'Building', 1, 0);

INSERT IGNORE INTO llx_c_kreaimmo_juridique(rowid,code,label,active,entity) VALUES (1, 'MONO_PROP', 'Mono propriété', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_juridique(rowid,code,label,active,entity) VALUES (2, 'CO_PROP', 'Copropriété', 1, 0);

INSERT IGNORE INTO llx_c_kreaimmo_builtdate(rowid,code,label,active,entity) VALUES (1, 'BUILT1', 'avant 1949', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_builtdate(rowid,code,label,active,entity) VALUES (2, 'BUILT2', 'de 1949 à 1974', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_builtdate(rowid,code,label,active,entity) VALUES (3, 'BUILT3', 'de 1975 à 1989', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_builtdate(rowid,code,label,active,entity) VALUES (4, 'BUILT4', 'de 1989 à 2005', 1, 0);
INSERT IGNORE INTO llx_c_kreaimmo_builtdate(rowid,code,label,active,entity) VALUES (5, 'BUILT5', 'depuis 2005', 1, 0);

INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE001', 'Entretien de l’immeuble et des équipements', 'Charge récupérable/locative', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE001');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE002', 'Consommations communes', 'Charge récupérable/locative', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE002');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE003', 'Consommations personnelles', 'Charge récupérable/locative', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE003');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE004', 'Taxe d’enlèvement des ordures ménagères', 'Charge récupérable/locative', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE004');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE005', 'Autres', 'Charge récupérable/locative', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE005');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE006', 'Frais d’administration et de gestion', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE006');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE007', 'Primes d’assurance', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE007');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE008', 'Dépenses de réparation, d’entretien et d’amélioration', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE008');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE009', 'Charges récupérables non récupérées au départ du locataire', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE009');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE010', 'Indemnités d’éviction, frais de relogement', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE010');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE011', 'Taxes foncières, taxes annexes', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE011');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE012', 'Autres', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE012');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE013', 'Charge non déductible', 'Charge non déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE013');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE014', 'Intérêts d’emprunt', 'Charge déductible', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE014');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE015', 'Provisions pour charge', 'Syndic de copropriété', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE015');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE016', 'Arrêté des comptes : Charges récupérables/locatives', 'Syndic de copropriété', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE016');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE017', 'Arrêté des comptes : Charges déductibles', 'Syndic de copropriété', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE017');
INSERT IGNORE INTO llx_kreaimmo_immocost_type(ref, label, famille, fk_user_create, active, date_creation, entity) SELECT 'COSTTYPE018', 'Arrêté des comptes : Charges non déductibles', 'Syndic de copropriété', 1, 1, NOW(), __ENTITY__ WHERE NOT EXISTS (SELECT 1 FROM llx_kreaimmo_immocost_type WHERE entity = __ENTITY__ AND ref = 'COSTTYPE018');
