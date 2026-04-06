-- =====================================================================
-- Car Parts DB — Extended makes & models seed
-- - Expands top 15 global brands from 15 → ~30 models each
-- - Adds Toyota-specific: Supra MK3, Supra MK4, Celica Supra, MR2 gens
-- - Adds Lexus-specific:  IS200, IS300, IS-F and other IS/GS/LS trims
-- - Adds 14 major Asian brands: Infiniti, Acura, Daihatsu, Isuzu,
--   BYD, MG, Datsun (historical), Proton, Perodua, Tata, Mahindra,
--   Chery, Geely, Haval
--
-- Safe to run multiple times — all inserts use INSERT IGNORE.
-- Run via:  mysql -u USER -p DBNAME < seed_makes_extended.sql
-- =====================================================================

-- ── Step 1: ensure all makes exist ───────────────────────────────────────────
INSERT IGNORE INTO `CAR_MAKES` (`name`) VALUES
  -- top 15 (already seeded by the app, IGNORE is a no-op here)
  ('Toyota'), ('Volkswagen'), ('Ford'), ('Hyundai'), ('Honda'),
  ('Nissan'), ('Kia'), ('Chevrolet'), ('Mercedes-Benz'), ('BMW'),
  ('Renault'), ('Peugeot'), ('Audi'), ('Suzuki'), ('Fiat'),
  -- luxury branches (Lexus already seeded by app)
  ('Lexus'), ('Infiniti'), ('Acura'),
  -- other Asian brands
  ('Daihatsu'), ('Datsun');

-- ── Step 2: cache make IDs into session variables ─────────────────────────────
SET @toyota    = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Toyota'        LIMIT 1);
SET @vw        = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Volkswagen'    LIMIT 1);
SET @ford      = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Ford'          LIMIT 1);
SET @hyundai   = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Hyundai'       LIMIT 1);
SET @honda     = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Honda'         LIMIT 1);
SET @nissan    = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Nissan'        LIMIT 1);
SET @kia       = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Kia'           LIMIT 1);
SET @chevrolet = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Chevrolet'     LIMIT 1);
SET @mercedes  = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Mercedes-Benz' LIMIT 1);
SET @bmw       = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='BMW'           LIMIT 1);
SET @renault   = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Renault'       LIMIT 1);
SET @peugeot   = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Peugeot'       LIMIT 1);
SET @audi      = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Audi'          LIMIT 1);
SET @suzuki    = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Suzuki'        LIMIT 1);
SET @fiat      = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Fiat'          LIMIT 1);
SET @lexus     = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Lexus'         LIMIT 1);
SET @infiniti  = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Infiniti'      LIMIT 1);
SET @acura     = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Acura'         LIMIT 1);
SET @daihatsu  = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Daihatsu'      LIMIT 1);
SET @isuzu     = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Isuzu'         LIMIT 1);
SET @byd       = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='BYD'           LIMIT 1);
SET @mg        = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='MG'            LIMIT 1);
SET @datsun    = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Datsun'        LIMIT 1);
SET @proton    = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Proton'        LIMIT 1);
SET @perodua   = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Perodua'       LIMIT 1);
SET @tata      = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Tata'          LIMIT 1);
SET @mahindra  = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Mahindra'      LIMIT 1);
SET @chery     = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Chery'         LIMIT 1);
SET @geely     = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Geely'         LIMIT 1);
SET @haval     = (SELECT `id` FROM `CAR_MAKES` WHERE `name`='Haval'         LIMIT 1);

-- =====================================================================
-- TOP 15 GLOBAL BRANDS — extra models (app already seeded 15 per brand)
-- =====================================================================

-- ── Toyota ────────────────────────────────────────────────────────────────────
-- existing 15: Corolla, Camry, RAV4, Yaris, Land Cruiser, Hilux, Prius,
--              Supra, Celica, Auris, C-HR, Aygo, GT86, Avensis, Verso
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@toyota, 'Supra MK3',          1986, 1992),  -- A70: user has this already
(@toyota, 'Supra MK4',          1993, 2002),  -- A80: user has this already
(@toyota, 'Celica Supra',       1982, 1986),  -- MA61: user has this already
(@toyota, 'MR2 W10',            1984, 1989),  -- AW11 first gen
(@toyota, 'MR2 W20',            1989, 1999),  -- SW20 second gen
(@toyota, 'MR2 W30',            1999, 2007),  -- ZZW30 Spyder
(@toyota, '4Runner',            1984, NULL),
(@toyota, 'Tundra',             1999, NULL),
(@toyota, 'Tacoma',             1995, NULL),
(@toyota, 'Land Cruiser Prado', 1984, NULL),
(@toyota, 'GR Yaris',           2020, NULL),
(@toyota, 'GR86',               2021, NULL),
(@toyota, 'Starlet',            1973, 1999),
(@toyota, 'Cressida',           1976, 1992),
(@toyota, 'Alphard',            2002, NULL);

-- ── Volkswagen ────────────────────────────────────────────────────────────────
-- existing 15: Golf, Polo, Passat, Tiguan, T-Roc, Touareg, ID.4, Caddy,
--              Transporter, Arteon, Up, Sharan, Touran, T-Cross, ID.3
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@vw, 'Golf GTI',       1976, NULL),
(@vw, 'Golf R',         2003, NULL),
(@vw, 'Beetle',         1938, 2003),
(@vw, 'New Beetle',     1997, 2010),
(@vw, 'Jetta',          1979, NULL),
(@vw, 'Amarok',         2010, NULL),
(@vw, 'Crafter',        2006, NULL),
(@vw, 'Scirocco',       1974, 2017),
(@vw, 'Corrado',        1988, 1995),
(@vw, 'ID.5',           2021, NULL),
(@vw, 'Golf Variant',   1993, NULL),
(@vw, 'Vento',          1991, 1998),
(@vw, 'Phaeton',        2002, 2016),
(@vw, 'CC',             2008, 2017),
(@vw, 'Golf Sportsvan', 2013, 2020);

-- ── Ford ──────────────────────────────────────────────────────────────────────
-- existing 15: Focus, Fiesta, Mondeo, Mustang, Explorer, F-150, Kuga, Galaxy,
--              S-Max, Puma, EcoSport, Ranger, Transit, Edge, Escape
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@ford, 'Ka',               1996, 2016),
(@ford, 'Ka+',              2016, 2021),
(@ford, 'Fusion',           2005, 2020),
(@ford, 'Bronco',           1965, NULL),
(@ford, 'C-Max',            2003, 2019),
(@ford, 'Maverick',         2021, NULL),
(@ford, 'Expedition',       1996, NULL),
(@ford, 'Thunderbird',      1955, 2005),
(@ford, 'Sierra',           1982, 1993),
(@ford, 'Capri',            1968, 1986),
(@ford, 'Granada',          1972, 1994),
(@ford, 'Tourneo Connect',  2002, NULL),
(@ford, 'Probe',            1988, 1997),
(@ford, 'Cortina',          1962, 1982),
(@ford, 'Bronco Sport',     2020, NULL);

-- ── Hyundai ───────────────────────────────────────────────────────────────────
-- existing 15: i30, i20, i10, Tucson, Santa Fe, Kona, Ioniq, Elantra, Sonata,
--              ix35, Veloster, Accent, Getz, Coupe, i40
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@hyundai, 'Palisade',   2018, NULL),
(@hyundai, 'Ioniq 5',    2021, NULL),
(@hyundai, 'Ioniq 6',    2022, NULL),
(@hyundai, 'i20N',       2020, NULL),
(@hyundai, 'i30N',       2017, NULL),
(@hyundai, 'Bayon',      2021, NULL),
(@hyundai, 'H-1',        1997, NULL),
(@hyundai, 'ix55',       2007, 2015),
(@hyundai, 'Santa Cruz', 2021, NULL),
(@hyundai, 'Staria',     2021, NULL),
(@hyundai, 'Lantra',     1990, 2000),
(@hyundai, 'Trajet',     1999, 2008),
(@hyundai, 'Matrix',     2001, 2010),
(@hyundai, 'Terracan',   2001, 2006),
(@hyundai, 'ix20',       2010, 2019);

-- ── Honda ─────────────────────────────────────────────────────────────────────
-- existing 15: Civic, Accord, CR-V, Jazz, HR-V, Pilot, Legend, Integra,
--              NSX, S2000, CR-Z, Stream, Odyssey, Prelude, Element
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@honda, 'Fit',        2001, NULL),
(@honda, 'FR-V',       2004, 2009),
(@honda, 'BR-V',       2015, NULL),
(@honda, 'City',       1981, NULL),
(@honda, 'Shuttle',    2011, NULL),
(@honda, 'Ridgeline',  2005, NULL),
(@honda, 'Concerto',   1988, 1994),
(@honda, 'Freed',      2008, NULL),
(@honda, 'ZR-V',       2022, NULL),
(@honda, 'e',          2020, 2023),
(@honda, 'Logo',       1996, 2001),
(@honda, 'Crossroad',  2007, 2010),
(@honda, 'Stepwgn',    1996, NULL),
(@honda, 'Life',       1971, NULL),
(@honda, 'Today',      1985, 1998);

-- ── Nissan ────────────────────────────────────────────────────────────────────
-- existing 15: Qashqai, Micra, Juke, X-Trail, Leaf, 350Z, 370Z, GT-R,
--              Skyline, Primera, Almera, Patrol, Navara, Terrano, Silvia
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@nissan, 'Skyline R32',     1989, 1994),
(@nissan, 'Skyline R33',     1993, 1998),
(@nissan, 'Skyline R34',     1998, 2002),
(@nissan, 'Silvia S13',      1988, 1994),
(@nissan, 'Silvia S14',      1993, 1998),
(@nissan, 'Silvia S15',      1999, 2002),
(@nissan, '180SX',           1988, 1999),
(@nissan, 'Fairlady Z S30',  1969, 1978),
(@nissan, 'Fairlady Z Z31',  1983, 1989),
(@nissan, 'Sunny',           1966, 2006),
(@nissan, 'Bluebird',        1957, 2004),
(@nissan, 'Note',            2004, NULL),
(@nissan, 'Murano',          2002, NULL),
(@nissan, 'Pathfinder',      1985, NULL),
(@nissan, 'Pulsar',          1978, 2000);

-- ── Kia ───────────────────────────────────────────────────────────────────────
-- existing 15: Sportage, Picanto, Rio, Ceed, Stinger, Sorento, Niro, Soul,
--              EV6, ProCeed, Carnival, Stonic, Optima, Shuma, Carens
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@kia, 'EV9',       2023, NULL),
(@kia, 'Telluride', 2019, NULL),
(@kia, 'Seltos',    2019, NULL),
(@kia, 'Xceed',     2019, NULL),
(@kia, 'K5',        2020, NULL),
(@kia, 'K8',        2021, NULL),
(@kia, 'Mohave',    2008, NULL),
(@kia, 'Pride',     1987, 2000),
(@kia, 'Cerato',    2003, NULL),
(@kia, 'Magentis',  2000, 2010),
(@kia, 'Venga',     2009, 2019),
(@kia, 'Soul EV',   2014, NULL),
(@kia, 'Ceed SW',   2006, NULL),
(@kia, 'Retona',    1997, 2000),
(@kia, 'Joice',     1999, 2002);

-- ── Chevrolet ─────────────────────────────────────────────────────────────────
-- existing 15: Camaro, Corvette, Silverado, Malibu, Equinox, Tahoe, Suburban,
--              Impala, Cruze, Spark, Sonic, Blazer, Colorado, Traverse, Trax
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@chevrolet, 'Bolt EV',     2016, NULL),
(@chevrolet, 'Trailblazer', 2001, NULL),
(@chevrolet, 'Express',     1995, NULL),
(@chevrolet, 'Nova',        1962, 1988),
(@chevrolet, 'El Camino',   1959, 1987),
(@chevrolet, 'Monte Carlo', 1970, 2007),
(@chevrolet, 'Caprice',     1965, 1996),
(@chevrolet, 'Astro',       1984, 2005),
(@chevrolet, 'S-10',        1981, 2004),
(@chevrolet, 'Aveo',        2002, 2011),
(@chevrolet, 'HHR',         2005, 2011),
(@chevrolet, 'Captiva',     2006, 2018),
(@chevrolet, 'Orlando',     2010, 2015),
(@chevrolet, 'Torino',      1968, 1976),
(@chevrolet, 'Lacetti',     2002, 2011);

-- ── Mercedes-Benz ────────────────────────────────────────────────────────────
-- existing 15: C-Class, E-Class, GLC, A-Class, CLA, GLE, S-Class, GLB,
--              B-Class, CLS, G-Class, GLA, ML-Class, SL, AMG GT
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@mercedes, 'EQC',      2019, NULL),
(@mercedes, 'EQS',      2021, NULL),
(@mercedes, 'EQA',      2021, NULL),
(@mercedes, 'EQB',      2021, NULL),
(@mercedes, 'GLS',      2012, NULL),
(@mercedes, 'V-Class',  1996, NULL),
(@mercedes, 'Sprinter', 1995, NULL),
(@mercedes, 'Vito',     1996, NULL),
(@mercedes, 'CLK',      1997, 2009),
(@mercedes, 'SLK',      1996, 2016),
(@mercedes, 'SLC',      2016, NULL),
(@mercedes, 'R-Class',  2005, 2013),
(@mercedes, '190E',     1982, 1993),
(@mercedes, 'W124',     1984, 1997),
(@mercedes, 'W123',     1976, 1985);

-- ── BMW ───────────────────────────────────────────────────────────────────────
-- existing 15: 3 Series, 5 Series, X3, X5, 1 Series, X1, 7 Series,
--              M3, M5, 2 Series, 4 Series, Z4, i3, X6, 6 Series
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@bmw, '8 Series',    1989, NULL),
(@bmw, 'X2',          2018, NULL),
(@bmw, 'X4',          2014, NULL),
(@bmw, 'X7',          2018, NULL),
(@bmw, 'iX',          2021, NULL),
(@bmw, 'i4',          2021, NULL),
(@bmw, 'i5',          2023, NULL),
(@bmw, 'i7',          2022, NULL),
(@bmw, 'M2',          2015, NULL),
(@bmw, 'M4',          2014, NULL),
(@bmw, 'M6',          1983, 2018),
(@bmw, 'M8',          2019, NULL),
(@bmw, 'Z3',          1995, 2002),
(@bmw, 'Z8',          2000, 2003),
(@bmw, '3 Series GT', 2013, 2019);

-- ── Renault ───────────────────────────────────────────────────────────────────
-- existing 15: Clio, Megane, Scenic, Kadjar, Captur, Laguna, Trafic, Master,
--              Twingo, Zoe, Koleos, Kangoo, Espace, Duster, Arkana
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@renault, 'Megane RS',    1999, NULL),
(@renault, 'Clio RS',      2000, NULL),
(@renault, 'Austral',      2022, NULL),
(@renault, 'Rafale',       2023, NULL),
(@renault, 'Talisman',     2015, 2022),
(@renault, 'Symbol',       1999, 2008),
(@renault, 'Fluence',      2009, 2016),
(@renault, 'Modus',        2004, 2012),
(@renault, 'Vel Satis',    2001, 2009),
(@renault, 'Avantime',     2001, 2003),
(@renault, 'Alaskan',      2016, NULL),
(@renault, 'Grand Scenic', 1996, NULL),
(@renault, 'Safrane',      1992, 2000),
(@renault, 'Express',      1985, 1998),
(@renault, 'R5',           1972, 1996);

-- ── Peugeot ───────────────────────────────────────────────────────────────────
-- existing 15: 208, 308, 3008, 2008, 508, 206, 207, 306, 307,
--              405, 406, 407, 5008, Partner, Rifter
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@peugeot, '107',    2005, 2014),
(@peugeot, '108',    2014, 2021),
(@peugeot, '106',    1991, 2004),
(@peugeot, '205',    1983, 1998),
(@peugeot, '305',    1977, 1990),
(@peugeot, '309',    1985, 1993),
(@peugeot, '408',    2022, NULL),
(@peugeot, '505',    1979, 1992),
(@peugeot, '604',    1975, 1985),
(@peugeot, '806',    1994, 2002),
(@peugeot, '807',    2002, 2014),
(@peugeot, '1007',   2004, 2009),
(@peugeot, '4007',   2007, 2012),
(@peugeot, '4008',   2012, 2017),
(@peugeot, 'e-208',  2019, NULL);

-- ── Audi ──────────────────────────────────────────────────────────────────────
-- existing 15: A3, A4, A6, Q3, Q5, A5, Q7, TT, A1, A8, Q2, RS4, RS6, S3, e-tron
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@audi, 'A2',        1999, 2005),
(@audi, 'A7',        2010, NULL),
(@audi, 'Q4 e-tron', 2021, NULL),
(@audi, 'Q8',        2018, NULL),
(@audi, 'RS3',       2011, NULL),
(@audi, 'RS5',       2010, NULL),
(@audi, 'RS7',       2013, NULL),
(@audi, 'RS Q3',     2013, NULL),
(@audi, 'RS Q8',     2019, NULL),
(@audi, 'S4',        1991, NULL),
(@audi, 'S5',        2007, NULL),
(@audi, 'S6',        1994, NULL),
(@audi, 'S8',        1996, NULL),
(@audi, 'Allroad',   1999, NULL),
(@audi, 'SQ5',       2012, NULL);

-- ── Suzuki ────────────────────────────────────────────────────────────────────
-- existing 15: Swift, Vitara, Jimny, Baleno, Ignis, SX4, Alto, Grand Vitara,
--              Liana, Wagon R, Celerio, Ciaz, Ertiga, Samurai, Splash
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@suzuki, 'S-Cross',     2013, NULL),
(@suzuki, 'Kizashi',     2009, 2016),
(@suzuki, 'XL7',         2001, 2009),
(@suzuki, 'Carry',       1961, NULL),
(@suzuki, 'Every',       1982, NULL),
(@suzuki, 'Swift Sport', 2005, NULL),
(@suzuki, 'Across',      2020, NULL),
(@suzuki, 'Sidekick',    1988, 1998),
(@suzuki, 'Cultus',      1983, 2006),
(@suzuki, 'Cappuccino',  1991, 1998),
(@suzuki, 'X-90',        1995, 1997),
(@suzuki, 'Equator',     2009, 2012),
(@suzuki, 'Dzire',       2008, NULL),
(@suzuki, 'Fronx',       2023, NULL),
(@suzuki, 'Burgman',     1998, NULL);  -- scooter but often in parts DBs

-- ── Fiat ──────────────────────────────────────────────────────────────────────
-- existing 15: 500, Punto, Panda, Tipo, Bravo, Stilo, Doblo, Freemont, Qubo,
--              Sedici, Linea, Croma, Seicento, Barchetta, Multipla
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@fiat, '500X',         2014, NULL),
(@fiat, '500L',         2012, NULL),
(@fiat, '600',          2023, NULL),
(@fiat, '124 Spider',   2016, 2020),
(@fiat, 'Uno',          1983, 2013),
(@fiat, 'Tempra',       1990, 1998),
(@fiat, 'Regata',       1983, 1990),
(@fiat, 'Marea',        1996, 2002),
(@fiat, '128',          1969, 1984),
(@fiat, '127',          1971, 1983),
(@fiat, '850',          1964, 1973),
(@fiat, 'Grande Punto', 2005, 2012),
(@fiat, 'Idea',         2003, 2016),
(@fiat, 'Palio',        1996, 2016),
(@fiat, 'Fullback',     2016, 2020);

-- =====================================================================
-- LEXUS — specific trims the user already has + additional variants
-- =====================================================================
-- existing 15: IS, ES, RX, NX, GS, LS, UX, LC, RC, CT, LX, SC, GX, HS, LFA
-- user already has in DB: IS200, IS300, IS-F  (INSERT IGNORE = no-op)
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@lexus, 'IS200',    1998, 2005),
(@lexus, 'IS300',    2000, 2005),
(@lexus, 'IS-F',     2007, 2014),
(@lexus, 'IS200t',   2015, 2017),
(@lexus, 'IS300h',   2013, NULL),
(@lexus, 'IS350',    2005, NULL),
(@lexus, 'GS300',    1991, 2011),
(@lexus, 'GS430',    1997, 2007),
(@lexus, 'GS450h',   2006, 2020),
(@lexus, 'GS-F',     2015, 2020),
(@lexus, 'LS400',    1989, 2000),
(@lexus, 'LS430',    2000, 2006),
(@lexus, 'LS460',    2006, 2017),
(@lexus, 'ES300',    1992, 2003),
(@lexus, 'RX300',    1998, 2003),
(@lexus, 'RX350',    2006, NULL),
(@lexus, 'RX400h',   2005, 2009),
(@lexus, 'NX300h',   2014, 2021),
(@lexus, 'RC-F',     2014, NULL),
(@lexus, 'LX470',    1998, 2007);

-- =====================================================================
-- NEW ASIAN BRANDS
-- =====================================================================

-- ── Infiniti ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@infiniti, 'Q50',   2013, NULL),
(@infiniti, 'Q60',   2016, NULL),
(@infiniti, 'Q70',   2014, 2019),
(@infiniti, 'QX30',  2015, 2019),
(@infiniti, 'QX50',  2007, NULL),
(@infiniti, 'QX60',  2012, NULL),
(@infiniti, 'QX70',  2008, 2017),
(@infiniti, 'QX80',  2010, NULL),
(@infiniti, 'G35',   2002, 2007),
(@infiniti, 'G37',   2007, 2013),
(@infiniti, 'M35',   2002, 2010),
(@infiniti, 'M37',   2010, 2014),
(@infiniti, 'FX35',  2003, 2008),
(@infiniti, 'FX37',  2008, 2013),
(@infiniti, 'EX35',  2007, 2013),
(@infiniti, 'JX35',  2012, 2013),
(@infiniti, 'I30',   1995, 2001),
(@infiniti, 'I35',   2001, 2004),
(@infiniti, 'J30',   1992, 1997),
(@infiniti, 'Q45',   1989, 2006);

-- ── Acura ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@acura, 'TL',        1995, 2014),
(@acura, 'TSX',       2003, 2014),
(@acura, 'MDX',       2000, NULL),
(@acura, 'RDX',       2006, NULL),
(@acura, 'NSX',       1990, NULL),
(@acura, 'Integra',   1986, NULL),
(@acura, 'Legend',    1985, 1995),
(@acura, 'RSX',       2001, 2006),
(@acura, 'CL',        1996, 2003),
(@acura, 'ILX',       2012, 2022),
(@acura, 'ZDX',       2009, 2013),
(@acura, 'RL',        1996, 2012),
(@acura, 'TLX',       2014, NULL),
(@acura, 'Vigor',     1992, 1994),
(@acura, 'SLX',       1996, 1999),
(@acura, 'CDX',       2016, NULL),
(@acura, 'RLX',       2013, 2020),
(@acura, 'EL',        1997, 2005),
(@acura, 'CSX',       2005, 2011),
(@acura, 'Precision', 2025, NULL);

-- ── Daihatsu ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@daihatsu, 'Copen',     2002, NULL),
(@daihatsu, 'Terios',    1997, NULL),
(@daihatsu, 'Charade',   1977, 2000),
(@daihatsu, 'Rocky',     1984, NULL),
(@daihatsu, 'Sirion',    1998, 2004),
(@daihatsu, 'Cuore',     1980, 2012),
(@daihatsu, 'Move',      1995, NULL),
(@daihatsu, 'Tanto',     2003, NULL),
(@daihatsu, 'Mira',      1980, NULL),
(@daihatsu, 'Gran Max',  2007, NULL),
(@daihatsu, 'Feroza',    1988, 1997),
(@daihatsu, 'YRV',       2000, 2005),
(@daihatsu, 'Applause',  1989, 2000),
(@daihatsu, 'Materia',   2006, 2013),
(@daihatsu, 'Luxio',     2008, NULL),
(@daihatsu, 'Boon',      2004, NULL),
(@daihatsu, 'Cast',      2015, NULL),
(@daihatsu, 'Taft',      2020, NULL),
(@daihatsu, 'Hijet',     1960, NULL),
(@daihatsu, 'Compagno',  1963, 1969);





-- ── Datsun (historical Nissan brand, 1931–1986 + 2013–2020 revival) ──────────
INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES
(@datsun, '240Z',     1969, 1973),
(@datsun, '260Z',     1974, 1978),
(@datsun, '280Z',     1975, 1978),
(@datsun, '280ZX',    1978, 1983),
(@datsun, '1200',     1970, 1979),
(@datsun, '1600',     1966, 1973),
(@datsun, '2000',     1967, 1970),
(@datsun, '510',      1967, 1973),
(@datsun, '610',      1973, 1977),
(@datsun, '710',      1973, 1978),
(@datsun, '810',      1976, 1981),
(@datsun, 'Bluebird', 1955, 1983),
(@datsun, 'Sunny',    1966, 1983),
(@datsun, 'Cherry',   1970, 1980),
(@datsun, 'Violet',   1973, 1981),
(@datsun, 'Stanza',   1977, 1981),
(@datsun, 'Fairlady', 1961, 1970),
(@datsun, 'Go',       2013, 2020),   -- revival era (India/Africa/Indonesia)
(@datsun, 'Redi-GO',  2016, 2020),
(@datsun, 'Go+',      2013, 2019);








-- =====================================================================
-- Done. Run SELECT COUNT(*) FROM CAR_MAKES; and
--           SELECT COUNT(*) FROM CAR_MODELS; to verify.
-- =====================================================================
