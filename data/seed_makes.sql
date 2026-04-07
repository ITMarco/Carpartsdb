-- =====================================================================
-- Car Parts DB — base makes & models seed
-- Exact copy of the data in makes_helper.php _makes_default_data()
-- 30 makes, 15 models each.
--
-- HOW TO USE:
--   1. Load any page on the site first (so PHP creates the tables).
--   2. Then run this file:
--        mysql -u YOUR_USER -p YOUR_DB < seed_makes.sql
--   OR paste it into phpMyAdmin / SQLTools / any MySQL client.
--
-- Safe to run multiple times — INSERT IGNORE never duplicates.
-- =====================================================================

-- ── Makes ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `CAR_MAKES` (`name`) VALUES
  ('Toyota'), ('Honda'), ('Ford'), ('Volkswagen'), ('BMW'),
  ('Mercedes-Benz'), ('Audi'), ('Nissan'), ('Hyundai'), ('Kia'),
  ('Mazda'), ('Subaru'), ('Mitsubishi'), ('Chevrolet'), ('Peugeot'),
  ('Renault'), ('Citroën'), ('Opel'), ('Volvo'), ('Skoda'),
  ('SEAT'), ('Fiat'), ('Alfa Romeo'), ('Jeep'), ('Land Rover'),
  ('Porsche'), ('Lexus'), ('Suzuki'), ('Dacia'), ('MINI');

-- ── Variable shortcuts (MySQL session variables) ──────────────────────────────
SET @toyota     = (SELECT id FROM CAR_MAKES WHERE name = 'Toyota'        LIMIT 1);
SET @honda      = (SELECT id FROM CAR_MAKES WHERE name = 'Honda'         LIMIT 1);
SET @ford       = (SELECT id FROM CAR_MAKES WHERE name = 'Ford'          LIMIT 1);
SET @vw         = (SELECT id FROM CAR_MAKES WHERE name = 'Volkswagen'    LIMIT 1);
SET @bmw        = (SELECT id FROM CAR_MAKES WHERE name = 'BMW'           LIMIT 1);
SET @mercedes   = (SELECT id FROM CAR_MAKES WHERE name = 'Mercedes-Benz' LIMIT 1);
SET @audi       = (SELECT id FROM CAR_MAKES WHERE name = 'Audi'          LIMIT 1);
SET @nissan     = (SELECT id FROM CAR_MAKES WHERE name = 'Nissan'        LIMIT 1);
SET @hyundai    = (SELECT id FROM CAR_MAKES WHERE name = 'Hyundai'       LIMIT 1);
SET @kia        = (SELECT id FROM CAR_MAKES WHERE name = 'Kia'           LIMIT 1);
SET @mazda      = (SELECT id FROM CAR_MAKES WHERE name = 'Mazda'         LIMIT 1);
SET @subaru     = (SELECT id FROM CAR_MAKES WHERE name = 'Subaru'        LIMIT 1);
SET @mitsubishi = (SELECT id FROM CAR_MAKES WHERE name = 'Mitsubishi'    LIMIT 1);
SET @chevrolet  = (SELECT id FROM CAR_MAKES WHERE name = 'Chevrolet'     LIMIT 1);
SET @peugeot    = (SELECT id FROM CAR_MAKES WHERE name = 'Peugeot'       LIMIT 1);
SET @renault    = (SELECT id FROM CAR_MAKES WHERE name = 'Renault'       LIMIT 1);
SET @citroen    = (SELECT id FROM CAR_MAKES WHERE name = 'Citroën'       LIMIT 1);
SET @opel       = (SELECT id FROM CAR_MAKES WHERE name = 'Opel'          LIMIT 1);
SET @volvo      = (SELECT id FROM CAR_MAKES WHERE name = 'Volvo'         LIMIT 1);
SET @skoda      = (SELECT id FROM CAR_MAKES WHERE name = 'Skoda'         LIMIT 1);
SET @seat       = (SELECT id FROM CAR_MAKES WHERE name = 'SEAT'          LIMIT 1);
SET @fiat       = (SELECT id FROM CAR_MAKES WHERE name = 'Fiat'          LIMIT 1);
SET @alfa       = (SELECT id FROM CAR_MAKES WHERE name = 'Alfa Romeo'    LIMIT 1);
SET @jeep       = (SELECT id FROM CAR_MAKES WHERE name = 'Jeep'          LIMIT 1);
SET @landrover  = (SELECT id FROM CAR_MAKES WHERE name = 'Land Rover'    LIMIT 1);
SET @porsche    = (SELECT id FROM CAR_MAKES WHERE name = 'Porsche'       LIMIT 1);
SET @lexus      = (SELECT id FROM CAR_MAKES WHERE name = 'Lexus'         LIMIT 1);
SET @suzuki     = (SELECT id FROM CAR_MAKES WHERE name = 'Suzuki'        LIMIT 1);
SET @dacia      = (SELECT id FROM CAR_MAKES WHERE name = 'Dacia'         LIMIT 1);
SET @mini       = (SELECT id FROM CAR_MAKES WHERE name = 'MINI'          LIMIT 1);

-- ── Models ────────────────────────────────────────────────────────────────────

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@toyota,'Corolla',       1966, NULL),
(@toyota,'Camry',         1982, NULL),
(@toyota,'RAV4',          1994, NULL),
(@toyota,'Yaris',         1999, NULL),
(@toyota,'Land Cruiser',  1951, NULL),
(@toyota,'Hilux',         1968, NULL),
(@toyota,'Prius',         1997, NULL),
(@toyota,'Supra',         1978, 2002),
(@toyota,'Celica',        1970, 2006),
(@toyota,'Auris',         2006, 2019),
(@toyota,'C-HR',          2016, NULL),
(@toyota,'Aygo',          2005, 2022),
(@toyota,'GT86',          2012, 2021),
(@toyota,'Avensis',       1997, 2018),
(@toyota,'Verso',         2009, 2018);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@honda,'Civic',          1972, NULL),
(@honda,'Accord',         1976, NULL),
(@honda,'CR-V',           1995, NULL),
(@honda,'Jazz',           2001, NULL),
(@honda,'HR-V',           1998, NULL),
(@honda,'Pilot',          2002, NULL),
(@honda,'Legend',         1985, 2021),
(@honda,'Integra',        1985, 2001),
(@honda,'NSX',            1990, 2005),
(@honda,'S2000',          1999, 2009),
(@honda,'CR-Z',           2010, 2016),
(@honda,'Stream',         2000, 2014),
(@honda,'Odyssey',        1994, NULL),
(@honda,'Prelude',        1978, 2001),
(@honda,'Element',        2002, 2011);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@ford,'Focus',           1998, NULL),
(@ford,'Fiesta',          1976, 2023),
(@ford,'Mondeo',          1993, NULL),
(@ford,'Mustang',         1964, NULL),
(@ford,'Explorer',        1990, NULL),
(@ford,'F-150',           1948, NULL),
(@ford,'Kuga',            2008, NULL),
(@ford,'Galaxy',          1995, NULL),
(@ford,'S-Max',           2006, NULL),
(@ford,'Puma',            2019, NULL),
(@ford,'EcoSport',        2003, NULL),
(@ford,'Ranger',          1983, NULL),
(@ford,'Transit',         1965, NULL),
(@ford,'Edge',            2006, NULL),
(@ford,'Escape',          2000, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@vw,'Golf',              1974, NULL),
(@vw,'Polo',              1975, NULL),
(@vw,'Passat',            1973, NULL),
(@vw,'Tiguan',            2007, NULL),
(@vw,'T-Roc',             2017, NULL),
(@vw,'Touareg',           2002, NULL),
(@vw,'ID.4',              2020, NULL),
(@vw,'Caddy',             1980, NULL),
(@vw,'Transporter',       1950, NULL),
(@vw,'Arteon',            2017, NULL),
(@vw,'Up',                2011, NULL),
(@vw,'Sharan',            1995, NULL),
(@vw,'Touran',            2003, NULL),
(@vw,'T-Cross',           2018, NULL),
(@vw,'ID.3',              2019, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@bmw,'3 Series',         1975, NULL),
(@bmw,'5 Series',         1972, NULL),
(@bmw,'X3',               2003, NULL),
(@bmw,'X5',               1999, NULL),
(@bmw,'1 Series',         2004, NULL),
(@bmw,'X1',               2009, NULL),
(@bmw,'7 Series',         1977, NULL),
(@bmw,'M3',               1986, NULL),
(@bmw,'M5',               1984, NULL),
(@bmw,'2 Series',         2013, NULL),
(@bmw,'4 Series',         2013, NULL),
(@bmw,'Z4',               2002, NULL),
(@bmw,'i3',               2013, 2022),
(@bmw,'X6',               2008, NULL),
(@bmw,'6 Series',         2003, 2018);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@mercedes,'C-Class',     1993, NULL),
(@mercedes,'E-Class',     1953, NULL),
(@mercedes,'GLC',         2015, NULL),
(@mercedes,'A-Class',     1997, NULL),
(@mercedes,'CLA',         2013, NULL),
(@mercedes,'GLE',         2015, NULL),
(@mercedes,'S-Class',     1972, NULL),
(@mercedes,'GLB',         2019, NULL),
(@mercedes,'B-Class',     2005, NULL),
(@mercedes,'CLS',         2004, NULL),
(@mercedes,'G-Class',     1979, NULL),
(@mercedes,'GLA',         2013, NULL),
(@mercedes,'ML-Class',    1997, 2015),
(@mercedes,'SL',          1954, NULL),
(@mercedes,'AMG GT',      2014, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@audi,'A3',              1996, NULL),
(@audi,'A4',              1994, NULL),
(@audi,'A6',              1994, NULL),
(@audi,'Q3',              2011, NULL),
(@audi,'Q5',              2008, NULL),
(@audi,'A5',              2007, NULL),
(@audi,'Q7',              2005, NULL),
(@audi,'TT',              1998, NULL),
(@audi,'A1',              2010, NULL),
(@audi,'A8',              1994, NULL),
(@audi,'Q2',              2016, NULL),
(@audi,'RS4',             2000, NULL),
(@audi,'RS6',             2002, NULL),
(@audi,'S3',              1999, NULL),
(@audi,'e-tron',          2018, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@nissan,'Qashqai',       2006, NULL),
(@nissan,'Micra',         1982, NULL),
(@nissan,'Juke',          2010, NULL),
(@nissan,'X-Trail',       2000, NULL),
(@nissan,'Leaf',          2010, NULL),
(@nissan,'350Z',          2002, 2009),
(@nissan,'370Z',          2009, 2021),
(@nissan,'GT-R',          2007, NULL),
(@nissan,'Skyline',       1957, 2007),
(@nissan,'Primera',       1990, 2008),
(@nissan,'Almera',        1995, 2006),
(@nissan,'Patrol',        1951, NULL),
(@nissan,'Navara',        1986, NULL),
(@nissan,'Terrano',       1985, 2017),
(@nissan,'Silvia',        1965, 2002);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@hyundai,'i30',          2007, NULL),
(@hyundai,'i20',          2008, NULL),
(@hyundai,'i10',          2007, NULL),
(@hyundai,'Tucson',       2004, NULL),
(@hyundai,'Santa Fe',     2000, NULL),
(@hyundai,'Kona',         2017, NULL),
(@hyundai,'Ioniq',        2016, NULL),
(@hyundai,'Elantra',      1990, NULL),
(@hyundai,'Sonata',       1985, NULL),
(@hyundai,'ix35',         2009, 2017),
(@hyundai,'Veloster',     2011, 2022),
(@hyundai,'Accent',       1994, NULL),
(@hyundai,'Getz',         2002, 2011),
(@hyundai,'Coupe',        1996, 2009),
(@hyundai,'i40',          2011, 2019);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@kia,'Sportage',         1993, NULL),
(@kia,'Picanto',          2004, NULL),
(@kia,'Rio',              2000, NULL),
(@kia,'Ceed',             2006, NULL),
(@kia,'Stinger',          2017, NULL),
(@kia,'Sorento',          2002, NULL),
(@kia,'Niro',             2016, NULL),
(@kia,'Soul',             2008, NULL),
(@kia,'EV6',              2021, NULL),
(@kia,'ProCeed',          2018, NULL),
(@kia,'Carnival',         1998, NULL),
(@kia,'Stonic',           2017, NULL),
(@kia,'Optima',           2000, 2020),
(@kia,'Shuma',            1997, 2004),
(@kia,'Carens',           1999, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@mazda,'Mazda3',         2003, NULL),
(@mazda,'Mazda6',         2002, NULL),
(@mazda,'CX-5',           2012, NULL),
(@mazda,'MX-5',           1989, NULL),
(@mazda,'CX-30',          2019, NULL),
(@mazda,'CX-9',           2007, NULL),
(@mazda,'Mazda2',         2002, NULL),
(@mazda,'RX-7',           1978, 2002),
(@mazda,'RX-8',           2003, 2012),
(@mazda,'323',            1977, 2003),
(@mazda,'626',            1978, 2002),
(@mazda,'Premacy',        1999, 2018),
(@mazda,'MX-3',           1991, 1998),
(@mazda,'MX-6',           1987, 1997),
(@mazda,'CX-3',           2015, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@subaru,'Impreza',       1992, NULL),
(@subaru,'Outback',       1994, NULL),
(@subaru,'Forester',      1997, NULL),
(@subaru,'Legacy',        1989, NULL),
(@subaru,'XV',            2011, NULL),
(@subaru,'BRZ',           2012, NULL),
(@subaru,'WRX',           1992, NULL),
(@subaru,'WRX STI',       1994, NULL),
(@subaru,'Tribeca',       2005, 2014),
(@subaru,'Liberty',       1989, NULL),
(@subaru,'Baja',          2003, 2006),
(@subaru,'SVX',           1991, 1997),
(@subaru,'Justy',         1984, 2017),
(@subaru,'Leone',         1971, 1994),
(@subaru,'Alcyone',       1985, 1991);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@mitsubishi,'Lancer',        1973, 2017),
(@mitsubishi,'Outlander',     2001, NULL),
(@mitsubishi,'Eclipse Cross', 2017, NULL),
(@mitsubishi,'Pajero',        1981, NULL),
(@mitsubishi,'L200',          1978, NULL),
(@mitsubishi,'Galant',        1969, 2012),
(@mitsubishi,'Eclipse',       1989, 2011),
(@mitsubishi,'3000GT',        1990, 2000),
(@mitsubishi,'ASX',           2010, NULL),
(@mitsubishi,'Colt',          1962, 2012),
(@mitsubishi,'Carisma',       1995, 2004),
(@mitsubishi,'Space Star',    1998, 2004),
(@mitsubishi,'FTO',           1994, 2000),
(@mitsubishi,'GTO',           1990, 2000),
(@mitsubishi,'Sigma',         1990, 1996);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@chevrolet,'Camaro',     1966, NULL),
(@chevrolet,'Corvette',   1953, NULL),
(@chevrolet,'Silverado',  1998, NULL),
(@chevrolet,'Malibu',     1964, NULL),
(@chevrolet,'Equinox',    2002, NULL),
(@chevrolet,'Tahoe',      1994, NULL),
(@chevrolet,'Suburban',   1935, NULL),
(@chevrolet,'Impala',     1958, 2020),
(@chevrolet,'Cruze',      2008, 2019),
(@chevrolet,'Spark',      2009, NULL),
(@chevrolet,'Sonic',      2011, 2020),
(@chevrolet,'Blazer',     1969, NULL),
(@chevrolet,'Colorado',   2004, NULL),
(@chevrolet,'Traverse',   2008, NULL),
(@chevrolet,'Trax',       2012, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@peugeot,'208',          2012, NULL),
(@peugeot,'308',          2007, NULL),
(@peugeot,'3008',         2008, NULL),
(@peugeot,'2008',         2013, NULL),
(@peugeot,'508',          2011, NULL),
(@peugeot,'206',          1998, 2010),
(@peugeot,'207',          2006, 2014),
(@peugeot,'306',          1993, 2002),
(@peugeot,'307',          2001, 2008),
(@peugeot,'405',          1987, 1997),
(@peugeot,'406',          1995, 2004),
(@peugeot,'407',          2004, 2011),
(@peugeot,'5008',         2009, NULL),
(@peugeot,'Partner',      1996, NULL),
(@peugeot,'Rifter',       2018, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@renault,'Clio',         1990, NULL),
(@renault,'Megane',       1995, NULL),
(@renault,'Scenic',       1996, NULL),
(@renault,'Kadjar',       2015, NULL),
(@renault,'Captur',       2013, NULL),
(@renault,'Laguna',       1993, 2015),
(@renault,'Trafic',       1980, NULL),
(@renault,'Master',       1980, NULL),
(@renault,'Twingo',       1993, NULL),
(@renault,'Zoe',          2012, NULL),
(@renault,'Koleos',       2008, NULL),
(@renault,'Kangoo',       1997, NULL),
(@renault,'Espace',       1984, NULL),
(@renault,'Duster',       2010, NULL),
(@renault,'Arkana',       2019, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@citroen,'C3',           2001, NULL),
(@citroen,'C4',           2004, NULL),
(@citroen,'Berlingo',     1996, NULL),
(@citroen,'Dispatch',     1994, NULL),
(@citroen,'C5',           2001, 2022),
(@citroen,'C1',           2005, 2022),
(@citroen,'DS3',          2009, 2019),
(@citroen,'C4 Cactus',    2014, NULL),
(@citroen,'Saxo',         1996, 2004),
(@citroen,'Xsara',        1997, 2006),
(@citroen,'Xantia',       1992, 2003),
(@citroen,'C8',           2002, 2014),
(@citroen,'C-Crosser',    2007, 2013),
(@citroen,'C3 Aircross',  2017, NULL),
(@citroen,'C5 X',         2021, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@opel,'Astra',           1991, NULL),
(@opel,'Corsa',           1982, NULL),
(@opel,'Insignia',        2008, NULL),
(@opel,'Zafira',          1999, NULL),
(@opel,'Mokka',           2012, NULL),
(@opel,'Crossland',       2017, NULL),
(@opel,'Grandland',       2017, NULL),
(@opel,'Meriva',          2003, 2017),
(@opel,'Vectra',          1988, 2008),
(@opel,'Omega',           1986, 2003),
(@opel,'Calibra',         1989, 1997),
(@opel,'Tigra',           1994, 2009),
(@opel,'Adam',            2012, 2019),
(@opel,'Agila',           2000, 2007),
(@opel,'Vivaro',          2001, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@volvo,'V40',            1995, 2019),
(@volvo,'V60',            2010, NULL),
(@volvo,'V70',            1996, 2016),
(@volvo,'V90',            2016, NULL),
(@volvo,'S60',            2000, NULL),
(@volvo,'S90',            2016, NULL),
(@volvo,'XC40',           2017, NULL),
(@volvo,'XC60',           2008, NULL),
(@volvo,'XC90',           2002, NULL),
(@volvo,'740',            1984, 1992),
(@volvo,'850',            1991, 1996),
(@volvo,'940',            1990, 1998),
(@volvo,'C30',            2006, 2013),
(@volvo,'S40',            1995, 2012),
(@volvo,'C70',            1997, 2013);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@skoda,'Octavia',        1996, NULL),
(@skoda,'Fabia',          1999, NULL),
(@skoda,'Superb',         2001, NULL),
(@skoda,'Kodiaq',         2016, NULL),
(@skoda,'Karoq',          2017, NULL),
(@skoda,'Kamiq',          2019, NULL),
(@skoda,'Citigo',         2011, 2020),
(@skoda,'Rapid',          2011, NULL),
(@skoda,'Roomster',       2006, 2015),
(@skoda,'Yeti',           2009, 2017),
(@skoda,'Scala',          2019, NULL),
(@skoda,'Enyaq',          2020, NULL),
(@skoda,'Felicia',        1994, 2001),
(@skoda,'Favorit',        1987, 1995),
(@skoda,'120',            1976, 1990);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@seat,'Ibiza',           1984, NULL),
(@seat,'Leon',            1999, NULL),
(@seat,'Ateca',           2016, NULL),
(@seat,'Arona',           2017, NULL),
(@seat,'Alhambra',        1996, 2022),
(@seat,'Formentor',       2020, NULL),
(@seat,'Toledo',          1991, 2019),
(@seat,'Cordoba',         1993, 2009),
(@seat,'Mii',             2011, 2021),
(@seat,'Tarraco',         2018, NULL),
(@seat,'Exeo',            2008, 2013),
(@seat,'Altea',           2004, 2015),
(@seat,'Terra',           1987, 2006),
(@seat,'Malaga',          1985, 1993),
(@seat,'Arosa',           1997, 2004);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@fiat,'500',             2007, NULL),
(@fiat,'Punto',           1993, 2018),
(@fiat,'Panda',           1980, NULL),
(@fiat,'Tipo',            1988, NULL),
(@fiat,'Bravo',           1995, 2001),
(@fiat,'Stilo',           2001, 2007),
(@fiat,'Doblo',           2000, NULL),
(@fiat,'Freemont',        2011, 2016),
(@fiat,'Qubo',            2008, 2019),
(@fiat,'Sedici',          2006, 2014),
(@fiat,'Linea',           2007, 2017),
(@fiat,'Croma',           2005, 2011),
(@fiat,'Seicento',        1998, 2010),
(@fiat,'Barchetta',       1995, 2005),
(@fiat,'Multipla',        1999, 2010);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@alfa,'Giulia',          2016, NULL),
(@alfa,'Stelvio',         2016, NULL),
(@alfa,'147',             2000, 2010),
(@alfa,'156',             1997, 2007),
(@alfa,'159',             2005, 2011),
(@alfa,'166',             1998, 2008),
(@alfa,'GTV',             1993, 2005),
(@alfa,'Spider',          1966, NULL),
(@alfa,'Brera',           2005, 2010),
(@alfa,'MiTo',            2008, 2018),
(@alfa,'145',             1994, 2001),
(@alfa,'146',             1994, 2001),
(@alfa,'Giulietta',       2010, NULL),
(@alfa,'33',              1983, 1994),
(@alfa,'75',              1985, 1992);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@jeep,'Wrangler',        1986, NULL),
(@jeep,'Grand Cherokee',  1992, NULL),
(@jeep,'Cherokee',        1974, NULL),
(@jeep,'Renegade',        2014, NULL),
(@jeep,'Compass',         2006, NULL),
(@jeep,'Gladiator',       2019, NULL),
(@jeep,'Commander',       2005, 2010),
(@jeep,'Liberty',         2001, 2012),
(@jeep,'Patriot',         2006, 2017),
(@jeep,'CJ',              1945, 1986),
(@jeep,'Wagoneer',        1962, NULL),
(@jeep,'Avenger',         2022, NULL),
(@jeep,'Trackhawk',       2017, 2021),
(@jeep,'Meridian',        2022, NULL),
(@jeep,'Recon',           2024, NULL);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@landrover,'Defender',              1983, NULL),
(@landrover,'Discovery',             1989, NULL),
(@landrover,'Range Rover',           1970, NULL),
(@landrover,'Freelander',            1997, 2014),
(@landrover,'Discovery Sport',       2014, NULL),
(@landrover,'Range Rover Evoque',    2011, NULL),
(@landrover,'Range Rover Sport',     2005, NULL),
(@landrover,'Range Rover Velar',     2017, NULL),
(@landrover,'Series I',              1948, 1958),
(@landrover,'Series II',             1958, 1971),
(@landrover,'Series III',            1971, 1985),
(@landrover,'110',                   1983, 2016),
(@landrover,'90',                    1983, 2016),
(@landrover,'130',                   1983, NULL),
(@landrover,'Forward Control',       1962, 1978);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@porsche,'911',          1963, NULL),
(@porsche,'Cayenne',      2002, NULL),
(@porsche,'Macan',        2014, NULL),
(@porsche,'Panamera',     2009, NULL),
(@porsche,'Taycan',       2019, NULL),
(@porsche,'718 Cayman',   2016, NULL),
(@porsche,'718 Boxster',  2016, NULL),
(@porsche,'944',          1982, 1991),
(@porsche,'968',          1991, 1995),
(@porsche,'928',          1977, 1995),
(@porsche,'914',          1969, 1976),
(@porsche,'912',          1965, 1969),
(@porsche,'959',          1986, 1989),
(@porsche,'356',          1948, 1965),
(@porsche,'Carrera GT',   2003, 2006);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@lexus,'IS',             1998, NULL),
(@lexus,'ES',             1989, NULL),
(@lexus,'RX',             1998, NULL),
(@lexus,'NX',             2014, NULL),
(@lexus,'GS',             1991, 2020),
(@lexus,'LS',             1989, NULL),
(@lexus,'UX',             2018, NULL),
(@lexus,'LC',             2017, NULL),
(@lexus,'RC',             2014, NULL),
(@lexus,'CT',             2010, 2022),
(@lexus,'LX',             1996, NULL),
(@lexus,'SC',             1991, 2010),
(@lexus,'GX',             2002, NULL),
(@lexus,'HS',             2009, 2018),
(@lexus,'LFA',            2010, 2012);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@suzuki,'Swift',         1983, NULL),
(@suzuki,'Vitara',        1988, NULL),
(@suzuki,'Jimny',         1970, NULL),
(@suzuki,'Baleno',        1995, NULL),
(@suzuki,'Ignis',         2000, NULL),
(@suzuki,'SX4',           2006, NULL),
(@suzuki,'Alto',          1979, NULL),
(@suzuki,'Grand Vitara',  1998, 2015),
(@suzuki,'Liana',         2001, 2007),
(@suzuki,'Wagon R',       1993, NULL),
(@suzuki,'Celerio',       2014, NULL),
(@suzuki,'Ciaz',          2014, NULL),
(@suzuki,'Ertiga',        2012, NULL),
(@suzuki,'Samurai',       1985, 2019),
(@suzuki,'Splash',        2008, 2015);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@dacia,'Sandero',        2008, NULL),
(@dacia,'Duster',         2010, NULL),
(@dacia,'Logan',          2004, NULL),
(@dacia,'Jogger',         2021, NULL),
(@dacia,'Spring',         2021, NULL),
(@dacia,'Lodgy',          2012, 2022),
(@dacia,'Dokker',         2012, 2020),
(@dacia,'Logan MCV',      2006, 2022),
(@dacia,'Stepway',        2008, NULL),
(@dacia,'Pick-Up',        2007, 2011),
(@dacia,'1300',           1969, 2004),
(@dacia,'1310',           1979, 2004),
(@dacia,'Nova',           1979, 2005),
(@dacia,'Liberta',        1984, 1991),
(@dacia,'Solenza',        2003, 2005);

INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@mini,'Cooper',              2001, NULL),
(@mini,'Cooper S',            2002, NULL),
(@mini,'One',                 2001, NULL),
(@mini,'Clubman',             2007, NULL),
(@mini,'Countryman',          2010, NULL),
(@mini,'Cabriolet',           2004, NULL),
(@mini,'Paceman',             2012, 2016),
(@mini,'Coupe',               2011, 2015),
(@mini,'Roadster',            2011, 2015),
(@mini,'John Cooper Works',   2006, NULL),
(@mini,'GP',                  2006, NULL),
(@mini,'Electric',            2019, NULL),
(@mini,'Cabrio',              2009, NULL),
(@mini,'Hatchback',           2014, NULL),
(@mini,'Aceman',              2024, NULL);

-- =====================================================================
-- Done.
-- Verify with:
--   SELECT COUNT(*) FROM CAR_MAKES;   -- should be 30
--   SELECT COUNT(*) FROM CAR_MODELS;  -- should be 450
-- =====================================================================
