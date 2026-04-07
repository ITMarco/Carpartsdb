-- =====================================================================
-- Car Parts DB — Generation splits for Toyota, Honda, Nissan, Subaru
--
-- Each model is entered per generation with correct year_from / year_to
-- and a descriptive name (chassis code where known).
-- INSERT IGNORE is safe: existing generic entries ("Corolla", etc.)
-- from the base seed stay untouched. Delete them manually if you want.
--
-- HOW TO RUN:
--   mysql -u USER -p DBNAME < seed_generations.sql
-- =====================================================================

SET @toyota  = (SELECT id FROM CAR_MAKES WHERE name = 'Toyota'  LIMIT 1);
SET @honda   = (SELECT id FROM CAR_MAKES WHERE name = 'Honda'   LIMIT 1);
SET @nissan  = (SELECT id FROM CAR_MAKES WHERE name = 'Nissan'  LIMIT 1);
SET @subaru  = (SELECT id FROM CAR_MAKES WHERE name = 'Subaru'  LIMIT 1);

-- =====================================================================
-- TOYOTA
-- =====================================================================

-- ── Corolla ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Corolla E10',       1966, 1970),
(@toyota, 'Corolla E20',       1970, 1974),
(@toyota, 'Corolla KE30/TE37', 1974, 1979),
(@toyota, 'Corolla KE70',      1979, 1983),
(@toyota, 'Corolla AE86',      1983, 1987),   -- Levin / Trueno
(@toyota, 'Corolla E80',       1987, 1992),
(@toyota, 'Corolla E100',      1991, 1997),
(@toyota, 'Corolla E110',      1995, 2002),
(@toyota, 'Corolla E120/E130', 2001, 2006),
(@toyota, 'Corolla E140/E150', 2006, 2013),
(@toyota, 'Corolla E160/E170', 2013, 2019),
(@toyota, 'Corolla E210',      2018, NULL);

-- ── Supra ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Supra A40',         1978, 1981),
(@toyota, 'Supra A60',         1982, 1986),   -- Celica Supra
(@toyota, 'Supra A70 MK3',     1986, 1992),
(@toyota, 'Supra A80 MK4',     1993, 2002),
(@toyota, 'Supra J29 GR',      2019, NULL);

-- ── Celica ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Celica A20/A35',    1970, 1977),
(@toyota, 'Celica A40/A60',    1977, 1985),
(@toyota, 'Celica T160',       1985, 1989),
(@toyota, 'Celica T180',       1989, 1993),
(@toyota, 'Celica T200',       1993, 1999),
(@toyota, 'Celica T230',       1999, 2006);

-- ── MR2 ───────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'MR2 AW11',          1984, 1989),
(@toyota, 'MR2 SW20',          1989, 1999),
(@toyota, 'MR2 ZZW30 Spyder',  1999, 2007);

-- ── Camry ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Camry V10',         1982, 1986),
(@toyota, 'Camry V20',         1986, 1991),
(@toyota, 'Camry V30',         1991, 1996),
(@toyota, 'Camry V40',         1996, 2001),
(@toyota, 'Camry XV30',        2001, 2006),
(@toyota, 'Camry XV40',        2006, 2011),
(@toyota, 'Camry XV50',        2011, 2017),
(@toyota, 'Camry XV70',        2017, NULL);

-- ── RAV4 ──────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'RAV4 XA10',         1994, 2000),
(@toyota, 'RAV4 XA20',         2000, 2006),
(@toyota, 'RAV4 XA30',         2006, 2012),
(@toyota, 'RAV4 XA40',         2012, 2018),
(@toyota, 'RAV4 XA50',         2018, NULL);

-- ── Land Cruiser ──────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Land Cruiser FJ40',  1960, 1984),
(@toyota, 'Land Cruiser FJ55',  1967, 1980),
(@toyota, 'Land Cruiser FJ60',  1980, 1987),
(@toyota, 'Land Cruiser FJ80',  1987, 1998),
(@toyota, 'Land Cruiser J100',  1998, 2007),
(@toyota, 'Land Cruiser J200',  2007, 2021),
(@toyota, 'Land Cruiser J300',  2021, NULL);

-- ── Land Cruiser Prado ────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Prado J70',          1984, 1996),
(@toyota, 'Prado J90',          1996, 2002),
(@toyota, 'Prado J120',         2002, 2009),
(@toyota, 'Prado J150',         2009, NULL);

-- ── Hilux ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Hilux N10/N20',      1968, 1978),
(@toyota, 'Hilux N30/N40',      1978, 1983),
(@toyota, 'Hilux N50/N60',      1983, 1988),
(@toyota, 'Hilux N80/N90',      1988, 1997),
(@toyota, 'Hilux N100/N110',    1997, 2005),
(@toyota, 'Hilux N120/N130',    2005, 2015),
(@toyota, 'Hilux N210',         2015, NULL);

-- ── Prius ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Prius XW10',         1997, 2003),
(@toyota, 'Prius XW20',         2003, 2009),
(@toyota, 'Prius XW30',         2009, 2015),
(@toyota, 'Prius XW50',         2015, 2022),
(@toyota, 'Prius XW60',         2022, NULL);

-- ── Yaris ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Yaris XP10',         1999, 2005),
(@toyota, 'Yaris XP90',         2005, 2011),
(@toyota, 'Yaris XP130',        2011, 2020),
(@toyota, 'Yaris XP210',        2020, NULL);

-- ── GT86 / GR86 ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'GT86 ZN6',           2012, 2021),
(@toyota, 'GR86 ZN8',           2021, NULL);

-- ── Avensis ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Avensis T220',       1997, 2003),
(@toyota, 'Avensis T250',       2003, 2009),
(@toyota, 'Avensis T270',       2008, 2018);

-- ── Auris ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@toyota, 'Auris E150',         2006, 2012),
(@toyota, 'Auris E180',         2012, 2018);

-- =====================================================================
-- HONDA
-- =====================================================================

-- ── Civic ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'Civic 1st gen',       1972, 1979),
(@honda, 'Civic 2nd gen SB',    1979, 1983),
(@honda, 'Civic 3rd gen AH',    1983, 1987),
(@honda, 'Civic 4th gen EF',    1987, 1991),
(@honda, 'Civic 5th gen EG',    1991, 1995),
(@honda, 'Civic 6th gen EK',    1995, 2000),
(@honda, 'Civic 7th gen EP/EU', 2000, 2005),
(@honda, 'Civic 8th gen FN/FK', 2005, 2011),
(@honda, 'Civic 9th gen FB',    2011, 2015),
(@honda, 'Civic 10th gen FC/FK',2015, 2021),
(@honda, 'Civic 11th gen FE/FL',2021, NULL);

-- ── Accord ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'Accord SJ/SM',        1976, 1981),
(@honda, 'Accord AC/AD',        1981, 1985),
(@honda, 'Accord CA',           1985, 1989),
(@honda, 'Accord CB/CD',        1989, 1993),
(@honda, 'Accord CD',           1993, 1997),
(@honda, 'Accord CG/CF',        1997, 2002),
(@honda, 'Accord CL/CM',        2002, 2008),
(@honda, 'Accord CP/CS',        2007, 2015),
(@honda, 'Accord CR',           2012, 2017),
(@honda, 'Accord CV',           2017, NULL);

-- ── CR-V ──────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'CR-V RD1/RD2',        1995, 2001),
(@honda, 'CR-V RD4-8',          2001, 2006),
(@honda, 'CR-V RE',             2006, 2012),
(@honda, 'CR-V RM',             2011, 2016),
(@honda, 'CR-V RW/RT',          2016, 2022),
(@honda, 'CR-V YH',             2022, NULL);

-- ── Jazz / Fit ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'Jazz GD',             2001, 2008),
(@honda, 'Jazz GE',             2008, 2014),
(@honda, 'Jazz GK',             2014, 2020),
(@honda, 'Jazz GR',             2020, NULL);

-- ── Integra ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'Integra DA/DB DA6',   1985, 1989),
(@honda, 'Integra DB6-DB8',     1989, 1993),
(@honda, 'Integra DC2',         1993, 2001),
(@honda, 'Integra DC5 TypeR',   2001, 2006),
(@honda, 'Integra DC',          2022, NULL);

-- ── NSX ───────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'NSX NA1/NA2',         1990, 2005),
(@honda, 'NSX NC1',             2016, 2022);

-- ── S2000 ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'S2000 AP1',           1999, 2003),
(@honda, 'S2000 AP2',           2003, 2009);

-- ── Prelude ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'Prelude SN',          1978, 1982),
(@honda, 'Prelude AB',          1982, 1987),
(@honda, 'Prelude BA',          1987, 1991),
(@honda, 'Prelude BB1-BB9',     1991, 2001);

-- ── HR-V ──────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'HR-V GH',             1998, 2006),
(@honda, 'HR-V RU',             2015, 2021),
(@honda, 'HR-V RV',             2021, NULL);

-- ── Legend ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@honda, 'Legend HS/KA3',       1985, 1990),
(@honda, 'Legend KA7/KA8',      1990, 1995),
(@honda, 'Legend KA9',          1996, 2004),
(@honda, 'Legend KB1/KB2',      2004, 2012),
(@honda, 'Legend KC2',          2014, 2021);

-- =====================================================================
-- NISSAN
-- =====================================================================

-- ── Skyline (all generations) ─────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Skyline C10 Hakosuka',1968, 1972),
(@nissan, 'Skyline C110 Kenmeri',1971, 1977),
(@nissan, 'Skyline C210',        1977, 1981),
(@nissan, 'Skyline R30',         1981, 1985),
(@nissan, 'Skyline R31',         1985, 1989),
(@nissan, 'Skyline R32',         1989, 1994),
(@nissan, 'Skyline R33',         1993, 1998),
(@nissan, 'Skyline R34',         1998, 2002),
(@nissan, 'Skyline V35',         2001, 2006),
(@nissan, 'Skyline V36',         2006, 2014);

-- ── GT-R ──────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'GT-R R32',            1989, 1994),
(@nissan, 'GT-R R33',            1995, 1998),
(@nissan, 'GT-R R34',            1999, 2002),
(@nissan, 'GT-R R35',            2007, NULL);

-- ── Silvia ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Silvia S10',          1975, 1979),
(@nissan, 'Silvia S110',         1979, 1983),
(@nissan, 'Silvia S12',          1983, 1988),
(@nissan, 'Silvia S13',          1988, 1994),
(@nissan, 'Silvia S14',          1993, 1998),
(@nissan, 'Silvia S15',          1999, 2002);

-- ── 180SX ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, '180SX RS13',          1988, 1999);

-- ── Micra ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Micra K10',           1982, 1992),
(@nissan, 'Micra K11',           1992, 2002),
(@nissan, 'Micra K12',           2002, 2010),
(@nissan, 'Micra K13',           2010, 2017),
(@nissan, 'Micra K14',           2017, NULL);

-- ── Qashqai ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Qashqai J10',         2006, 2013),
(@nissan, 'Qashqai J11',         2013, 2021),
(@nissan, 'Qashqai J12',         2021, NULL);

-- ── X-Trail ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'X-Trail T30',         2000, 2007),
(@nissan, 'X-Trail T31',         2007, 2013),
(@nissan, 'X-Trail T32',         2013, 2022),
(@nissan, 'X-Trail T33',         2022, NULL);

-- ── Primera ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Primera P10',         1990, 1996),
(@nissan, 'Primera P11',         1996, 2002),
(@nissan, 'Primera P12',         2001, 2008);

-- ── Patrol ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Patrol FG/FQ',        1951, 1980),   -- early series
(@nissan, 'Patrol Y60',          1987, 1998),
(@nissan, 'Patrol Y61',          1997, 2013),
(@nissan, 'Patrol Y62',          2010, NULL);

-- ── Navara ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Navara D21',          1986, 1998),
(@nissan, 'Navara D22',          1997, 2004),
(@nissan, 'Navara D40',          2004, 2015),
(@nissan, 'Navara D23',          2014, NULL);

-- ── 350Z / 370Z ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, '350Z Z33',            2002, 2009),
(@nissan, '370Z Z34',            2009, 2021),
(@nissan, 'Z Z-car Z35',         2022, NULL);

-- ── Almera ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Almera N15',          1995, 2000),
(@nissan, 'Almera N16',          2000, 2006);

-- ── Juke ──────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Juke F15',            2010, 2019),
(@nissan, 'Juke F16',            2019, NULL);

-- ── Fairlady Z ────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@nissan, 'Fairlady Z S30',      1969, 1978),
(@nissan, 'Fairlady Z S130',     1978, 1983),
(@nissan, 'Fairlady Z Z31',      1983, 1989),
(@nissan, 'Fairlady Z Z32',      1989, 2000),
(@nissan, 'Fairlady Z Z33',      2002, 2008),
(@nissan, 'Fairlady Z Z34',      2008, 2021);

-- =====================================================================
-- SUBARU
-- =====================================================================

-- ── Impreza ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'Impreza GC/GF',       1992, 2000),
(@subaru, 'Impreza GD/GG',       2000, 2007),
(@subaru, 'Impreza GE/GH',       2007, 2011),
(@subaru, 'Impreza GJ/GP',       2011, 2016),
(@subaru, 'Impreza GT/GK',       2016, 2023),
(@subaru, 'Impreza G4/G5',       2023, NULL);

-- ── WRX / STI ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'WRX GC8',             1992, 2000),
(@subaru, 'WRX STI GC8',         1994, 2000),
(@subaru, 'WRX GD/GG',           2000, 2007),
(@subaru, 'WRX STI GD/GG',       2000, 2007),
(@subaru, 'WRX GE/GH',           2007, 2014),
(@subaru, 'WRX STI GRB/GVB',     2007, 2014),
(@subaru, 'WRX VA',              2014, 2021),
(@subaru, 'WRX STI VA',          2014, 2021),
(@subaru, 'WRX VB',              2021, NULL);

-- ── Legacy ────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'Legacy BC/BF',        1989, 1993),
(@subaru, 'Legacy BD/BG',        1993, 1998),
(@subaru, 'Legacy BE/BH',        1998, 2003),
(@subaru, 'Legacy BL/BP',        2003, 2009),
(@subaru, 'Legacy BR/BM',        2009, 2014),
(@subaru, 'Legacy BN/BS',        2014, 2020),
(@subaru, 'Legacy BT',           2019, NULL);

-- ── Outback ───────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'Outback BG/BG9',      1994, 1999),
(@subaru, 'Outback BH',          2000, 2003),
(@subaru, 'Outback BP',          2003, 2009),
(@subaru, 'Outback BR',          2009, 2014),
(@subaru, 'Outback BS',          2014, 2019),
(@subaru, 'Outback BT',          2019, NULL);

-- ── Forester ──────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'Forester SF',         1997, 2002),
(@subaru, 'Forester SG',         2002, 2008),
(@subaru, 'Forester SH',         2008, 2013),
(@subaru, 'Forester SJ',         2013, 2018),
(@subaru, 'Forester SK',         2018, NULL);

-- ── BRZ ───────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'BRZ ZC6',             2012, 2021),
(@subaru, 'BRZ ZD8',             2021, NULL);

-- ── XV / Crosstrek ────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'XV GP',               2011, 2017),
(@subaru, 'XV GT',               2017, 2023),
(@subaru, 'Crosstrek GU',        2023, NULL);

-- ── SVX ───────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO CAR_MODELS (make_id, name, year_from, year_to) VALUES
(@subaru, 'SVX CXW',             1991, 1997);

-- =====================================================================
-- Done.
-- Verify: SELECT name, year_from, year_to FROM CAR_MODELS
--         WHERE make_id = @toyota ORDER BY name, year_from;
-- =====================================================================
