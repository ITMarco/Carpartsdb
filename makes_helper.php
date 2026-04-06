<?php
if (!defined('SNLDBCARPARTS_ACCESS')) die('Direct access not permitted.');

/**
 * Returns default data: [ make_name => [[model_name, year_from, year_to], ...] ]
 * 30 makes, up to 15 models each. year_to = null means still in production.
 */
function _makes_default_data(): array {
    return [
        'Toyota'        => [
            ['Corolla',        1966, null], ['Camry',          1982, null], ['RAV4',           1994, null],
            ['Yaris',          1999, null], ['Land Cruiser',   1951, null], ['Hilux',          1968, null],
            ['Prius',          1997, null], ['Supra',          1978, 2002], ['Celica',         1970, 2006],
            ['Auris',          2006, 2019], ['C-HR',           2016, null], ['Aygo',           2005, 2022],
            ['GT86',           2012, 2021], ['Avensis',        1997, 2018], ['Verso',          2009, 2018],
        ],
        'Honda'         => [
            ['Civic',          1972, null], ['Accord',         1976, null], ['CR-V',           1995, null],
            ['Jazz',           2001, null], ['HR-V',           1998, null], ['Pilot',          2002, null],
            ['Legend',         1985, 2021], ['Integra',        1985, 2001], ['NSX',            1990, 2005],
            ['S2000',          1999, 2009], ['CR-Z',           2010, 2016], ['Stream',         2000, 2014],
            ['Odyssey',        1994, null], ['Prelude',        1978, 2001], ['Element',        2002, 2011],
        ],
        'Ford'          => [
            ['Focus',          1998, null], ['Fiesta',         1976, 2023], ['Mondeo',         1993, null],
            ['Mustang',        1964, null], ['Explorer',       1990, null], ['F-150',          1948, null],
            ['Kuga',           2008, null], ['Galaxy',         1995, null], ['S-Max',          2006, null],
            ['Puma',           2019, null], ['EcoSport',       2003, null], ['Ranger',         1983, null],
            ['Transit',        1965, null], ['Edge',           2006, null], ['Escape',         2000, null],
        ],
        'Volkswagen'    => [
            ['Golf',           1974, null], ['Polo',           1975, null], ['Passat',         1973, null],
            ['Tiguan',         2007, null], ['T-Roc',          2017, null], ['Touareg',        2002, null],
            ['ID.4',           2020, null], ['Caddy',          1980, null], ['Transporter',    1950, null],
            ['Arteon',         2017, null], ['Up',             2011, null], ['Sharan',         1995, null],
            ['Touran',         2003, null], ['T-Cross',        2018, null], ['ID.3',           2019, null],
        ],
        'BMW'           => [
            ['3 Series',       1975, null], ['5 Series',       1972, null], ['X3',             2003, null],
            ['X5',             1999, null], ['1 Series',       2004, null], ['X1',             2009, null],
            ['7 Series',       1977, null], ['M3',             1986, null], ['M5',             1984, null],
            ['2 Series',       2013, null], ['4 Series',       2013, null], ['Z4',             2002, null],
            ['i3',             2013, 2022], ['X6',             2008, null], ['6 Series',       2003, 2018],
        ],
        'Mercedes-Benz' => [
            ['C-Class',        1993, null], ['E-Class',        1953, null], ['GLC',            2015, null],
            ['A-Class',        1997, null], ['CLA',            2013, null], ['GLE',            2015, null],
            ['S-Class',        1972, null], ['GLB',            2019, null], ['B-Class',        2005, null],
            ['CLS',            2004, null], ['G-Class',        1979, null], ['GLA',            2013, null],
            ['ML-Class',       1997, 2015], ['SL',             1954, null], ['AMG GT',         2014, null],
        ],
        'Audi'          => [
            ['A3',             1996, null], ['A4',             1994, null], ['A6',             1994, null],
            ['Q3',             2011, null], ['Q5',             2008, null], ['A5',             2007, null],
            ['Q7',             2005, null], ['TT',             1998, null], ['A1',             2010, null],
            ['A8',             1994, null], ['Q2',             2016, null], ['RS4',            2000, null],
            ['RS6',            2002, null], ['S3',             1999, null], ['e-tron',         2018, null],
        ],
        'Nissan'        => [
            ['Qashqai',        2006, null], ['Micra',          1982, null], ['Juke',           2010, null],
            ['X-Trail',        2000, null], ['Leaf',           2010, null], ['350Z',           2002, 2009],
            ['370Z',           2009, 2021], ['GT-R',           2007, null], ['Skyline',        1957, 2007],
            ['Primera',        1990, 2008], ['Almera',         1995, 2006], ['Patrol',         1951, null],
            ['Navara',         1986, null], ['Terrano',        1985, 2017], ['Silvia',         1965, 2002],
        ],
        'Hyundai'       => [
            ['i30',            2007, null], ['i20',            2008, null], ['i10',            2007, null],
            ['Tucson',         2004, null], ['Santa Fe',       2000, null], ['Kona',           2017, null],
            ['Ioniq',          2016, null], ['Elantra',        1990, null], ['Sonata',         1985, null],
            ['ix35',           2009, 2017], ['Veloster',       2011, 2022], ['Accent',         1994, null],
            ['Getz',           2002, 2011], ['Coupe',          1996, 2009], ['i40',            2011, 2019],
        ],
        'Kia'           => [
            ['Sportage',       1993, null], ['Picanto',        2004, null], ['Rio',            2000, null],
            ['Ceed',           2006, null], ['Stinger',        2017, null], ['Sorento',        2002, null],
            ['Niro',           2016, null], ['Soul',           2008, null], ['EV6',            2021, null],
            ['ProCeed',        2018, null], ['Carnival',       1998, null], ['Stonic',         2017, null],
            ['Optima',         2000, 2020], ['Shuma',          1997, 2004], ['Carens',         1999, null],
        ],
        'Mazda'         => [
            ['Mazda3',         2003, null], ['Mazda6',         2002, null], ['CX-5',           2012, null],
            ['MX-5',           1989, null], ['CX-30',          2019, null], ['CX-9',           2007, null],
            ['Mazda2',         2002, null], ['RX-7',           1978, 2002], ['RX-8',           2003, 2012],
            ['323',            1977, 2003], ['626',            1978, 2002], ['Premacy',        1999, 2018],
            ['MX-3',           1991, 1998], ['MX-6',           1987, 1997], ['CX-3',           2015, null],
        ],
        'Subaru'        => [
            ['Impreza',        1992, null], ['Outback',        1994, null], ['Forester',       1997, null],
            ['Legacy',         1989, null], ['XV',             2011, null], ['BRZ',            2012, null],
            ['WRX',            1992, null], ['WRX STI',        1994, null], ['Tribeca',        2005, 2014],
            ['Liberty',        1989, null], ['Baja',           2003, 2006], ['SVX',            1991, 1997],
            ['Justy',          1984, 2017], ['Leone',          1971, 1994], ['Alcyone',        1985, 1991],
        ],
        'Mitsubishi'    => [
            ['Lancer',         1973, 2017], ['Outlander',      2001, null], ['Eclipse Cross',  2017, null],
            ['Pajero',         1981, null], ['L200',           1978, null], ['Galant',         1969, 2012],
            ['Eclipse',        1989, 2011], ['3000GT',         1990, 2000], ['ASX',            2010, null],
            ['Colt',           1962, 2012], ['Carisma',        1995, 2004], ['Space Star',     1998, 2004],
            ['FTO',            1994, 2000], ['GTO',            1990, 2000], ['Sigma',          1990, 1996],
        ],
        'Chevrolet'     => [
            ['Camaro',         1966, null], ['Corvette',       1953, null], ['Silverado',      1998, null],
            ['Malibu',         1964, null], ['Equinox',        2002, null], ['Tahoe',          1994, null],
            ['Suburban',       1935, null], ['Impala',         1958, 2020], ['Cruze',          2008, 2019],
            ['Spark',          2009, null], ['Sonic',          2011, 2020], ['Blazer',         1969, null],
            ['Colorado',       2004, null], ['Traverse',       2008, null], ['Trax',           2012, null],
        ],
        'Peugeot'       => [
            ['208',            2012, null], ['308',            2007, null], ['3008',           2008, null],
            ['2008',           2013, null], ['508',            2011, null], ['206',            1998, 2010],
            ['207',            2006, 2014], ['306',            1993, 2002], ['307',            2001, 2008],
            ['405',            1987, 1997], ['406',            1995, 2004], ['407',            2004, 2011],
            ['5008',           2009, null], ['Partner',        1996, null], ['Rifter',         2018, null],
        ],
        'Renault'       => [
            ['Clio',           1990, null], ['Megane',         1995, null], ['Scenic',         1996, null],
            ['Kadjar',         2015, null], ['Captur',         2013, null], ['Laguna',         1993, 2015],
            ['Trafic',         1980, null], ['Master',         1980, null], ['Twingo',         1993, null],
            ['Zoe',            2012, null], ['Koleos',         2008, null], ['Kangoo',         1997, null],
            ['Espace',         1984, null], ['Duster',         2010, null], ['Arkana',         2019, null],
        ],
        'Citroën'       => [
            ['C3',             2001, null], ['C4',             2004, null], ['Berlingo',       1996, null],
            ['Dispatch',       1994, null], ['C5',             2001, 2022], ['C1',             2005, 2022],
            ['DS3',            2009, 2019], ['C4 Cactus',      2014, null], ['Saxo',           1996, 2004],
            ['Xsara',          1997, 2006], ['Xantia',         1992, 2003], ['C8',             2002, 2014],
            ['C-Crosser',      2007, 2013], ['C3 Aircross',    2017, null], ['C5 X',           2021, null],
        ],
        'Opel'          => [
            ['Astra',          1991, null], ['Corsa',          1982, null], ['Insignia',       2008, null],
            ['Zafira',         1999, null], ['Mokka',          2012, null], ['Crossland',      2017, null],
            ['Grandland',      2017, null], ['Meriva',         2003, 2017], ['Vectra',         1988, 2008],
            ['Omega',          1986, 2003], ['Calibra',        1989, 1997], ['Tigra',          1994, 2009],
            ['Adam',           2012, 2019], ['Agila',          2000, 2007], ['Vivaro',         2001, null],
        ],
        'Volvo'         => [
            ['V40',            1995, 2019], ['V60',            2010, null], ['V70',            1996, 2016],
            ['V90',            2016, null], ['S60',            2000, null], ['S90',            2016, null],
            ['XC40',           2017, null], ['XC60',           2008, null], ['XC90',           2002, null],
            ['740',            1984, 1992], ['850',            1991, 1996], ['940',            1990, 1998],
            ['C30',            2006, 2013], ['S40',            1995, 2012], ['C70',            1997, 2013],
        ],
        'Skoda'         => [
            ['Octavia',        1996, null], ['Fabia',          1999, null], ['Superb',         2001, null],
            ['Kodiaq',         2016, null], ['Karoq',          2017, null], ['Kamiq',          2019, null],
            ['Citigo',         2011, 2020], ['Rapid',          2011, null], ['Roomster',       2006, 2015],
            ['Yeti',           2009, 2017], ['Scala',          2019, null], ['Enyaq',          2020, null],
            ['Felicia',        1994, 2001], ['Favorit',        1987, 1995], ['120',            1976, 1990],
        ],
        'SEAT'          => [
            ['Ibiza',          1984, null], ['Leon',           1999, null], ['Ateca',          2016, null],
            ['Arona',          2017, null], ['Alhambra',       1996, 2022], ['Formentor',      2020, null],
            ['Toledo',         1991, 2019], ['Cordoba',        1993, 2009], ['Mii',            2011, 2021],
            ['Tarraco',        2018, null], ['Exeo',           2008, 2013], ['Altea',          2004, 2015],
            ['Terra',          1987, 2006], ['Malaga',         1985, 1993], ['Arosa',          1997, 2004],
        ],
        'Fiat'          => [
            ['500',            2007, null], ['Punto',          1993, 2018], ['Panda',          1980, null],
            ['Tipo',           1988, null], ['Bravo',          1995, 2001], ['Stilo',          2001, 2007],
            ['Doblo',          2000, null], ['Freemont',       2011, 2016], ['Qubo',           2008, 2019],
            ['Sedici',         2006, 2014], ['Linea',          2007, 2017], ['Croma',          2005, 2011],
            ['Seicento',       1998, 2010], ['Barchetta',      1995, 2005], ['Multipla',       1999, 2010],
        ],
        'Alfa Romeo'    => [
            ['Giulia',         2016, null], ['Stelvio',        2016, null], ['147',            2000, 2010],
            ['156',            1997, 2007], ['159',            2005, 2011], ['166',            1998, 2008],
            ['GTV',            1993, 2005], ['Spider',         1966, null], ['Brera',          2005, 2010],
            ['MiTo',           2008, 2018], ['145',            1994, 2001], ['146',            1994, 2001],
            ['Giulietta',      2010, null], ['33',             1983, 1994], ['75',             1985, 1992],
        ],
        'Jeep'          => [
            ['Wrangler',       1986, null], ['Grand Cherokee', 1992, null], ['Cherokee',       1974, null],
            ['Renegade',       2014, null], ['Compass',        2006, null], ['Gladiator',      2019, null],
            ['Commander',      2005, 2010], ['Liberty',        2001, 2012], ['Patriot',        2006, 2017],
            ['CJ',             1945, 1986], ['Wagoneer',       1962, null], ['Avenger',        2022, null],
            ['Trackhawk',      2017, 2021], ['Meridian',       2022, null], ['Recon',          2024, null],
        ],
        'Land Rover'    => [
            ['Defender',       1983, null], ['Discovery',      1989, null], ['Range Rover',    1970, null],
            ['Freelander',     1997, 2014], ['Discovery Sport',2014, null], ['Range Rover Evoque',2011,null],
            ['Range Rover Sport',2005,null],['Range Rover Velar',2017,null],['Series I',       1948, 1958],
            ['Series II',      1958, 1971], ['Series III',     1971, 1985], ['110',            1983, 2016],
            ['90',             1983, 2016], ['130',            1983, null], ['Forward Control', 1962, 1978],
        ],
        'Porsche'       => [
            ['911',            1963, null], ['Cayenne',        2002, null], ['Macan',          2014, null],
            ['Panamera',       2009, null], ['Taycan',         2019, null], ['718 Cayman',     2016, null],
            ['718 Boxster',    2016, null], ['944',            1982, 1991], ['968',            1991, 1995],
            ['928',            1977, 1995], ['914',            1969, 1976], ['912',            1965, 1969],
            ['959',            1986, 1989], ['356',            1948, 1965], ['Carrera GT',     2003, 2006],
        ],
        'Lexus'         => [
            ['IS',             1998, null], ['ES',             1989, null], ['RX',             1998, null],
            ['NX',             2014, null], ['GS',             1991, 2020], ['LS',             1989, null],
            ['UX',             2018, null], ['LC',             2017, null], ['RC',             2014, null],
            ['CT',             2010, 2022], ['LX',             1996, null], ['SC',             1991, 2010],
            ['GX',             2002, null], ['HS',             2009, 2018], ['LFA',            2010, 2012],
        ],
        'Suzuki'        => [
            ['Swift',          1983, null], ['Vitara',         1988, null], ['Jimny',          1970, null],
            ['Baleno',         1995, null], ['Ignis',          2000, null], ['SX4',            2006, null],
            ['Alto',           1979, null], ['Grand Vitara',   1998, 2015], ['Liana',          2001, 2007],
            ['Wagon R',        1993, null], ['Celerio',        2014, null], ['Ciaz',           2014, null],
            ['Ertiga',         2012, null], ['Samurai',        1985, 2019], ['Splash',         2008, 2015],
        ],
        'Dacia'         => [
            ['Sandero',        2008, null], ['Duster',         2010, null], ['Logan',          2004, null],
            ['Jogger',         2021, null], ['Spring',         2021, null], ['Lodgy',          2012, 2022],
            ['Dokker',         2012, 2020], ['Logan MCV',      2006, 2022], ['Stepway',        2008, null],
            ['Pick-Up',        2007, 2011], ['1300',           1969, 2004], ['1310',           1979, 2004],
            ['Nova',           1979, 2005], ['Liberta',        1984, 1991], ['Solenza',        2003, 2005],
        ],
        'MINI'          => [
            ['Cooper',         2001, null], ['Cooper S',       2002, null], ['One',            2001, null],
            ['Clubman',        2007, null], ['Countryman',     2010, null], ['Cabriolet',      2004, null],
            ['Paceman',        2012, 2016], ['Coupe',          2011, 2015], ['Roadster',       2011, 2015],
            ['John Cooper Works',2006,null],['GP',             2006, null], ['Electric',       2019, null],
            ['Cabrio',         2009, null], ['Hatchback',      2014, null], ['Aceman',         2024, null],
        ],
    ];
}

/**
 * Auto-create CAR_MAKES and CAR_MODELS tables and populate with default data if empty.
 *
 * SQL (for direct execution — run in this order):
 *   CREATE TABLE IF NOT EXISTS `CAR_MAKES` ( `id` INT NOT NULL AUTO_INCREMENT, `name` VARCHAR(100) NOT NULL,
 *     PRIMARY KEY (`id`), UNIQUE KEY `uk_name` (`name`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 *
 *   CREATE TABLE IF NOT EXISTS `CAR_MODELS` ( `id` INT NOT NULL AUTO_INCREMENT, `make_id` INT NOT NULL,
 *     `name` VARCHAR(100) NOT NULL, `year_from` SMALLINT DEFAULT NULL, `year_to` SMALLINT DEFAULT NULL,
 *     PRIMARY KEY (`id`), UNIQUE KEY `uk_make_model` (`make_id`,`name`), KEY `idx_make_id` (`make_id`),
 *     CONSTRAINT `fk_model_make` FOREIGN KEY (`make_id`) REFERENCES `CAR_MAKES` (`id`) ON DELETE CASCADE
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */
function makes_ensure_tables(mysqli $db): void {
    static $done = false;
    if ($done) return;
    $done = true;

    $db->query("CREATE TABLE IF NOT EXISTS `CAR_MAKES` (
        `id`   INT          NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(100) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS `CAR_MODELS` (
        `id`        INT          NOT NULL AUTO_INCREMENT,
        `make_id`   INT          NOT NULL,
        `name`      VARCHAR(100) NOT NULL,
        `year_from` SMALLINT     DEFAULT NULL,
        `year_to`   SMALLINT     DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_make_model` (`make_id`, `name`),
        KEY `idx_make_id` (`make_id`),
        CONSTRAINT `fk_model_make` FOREIGN KEY (`make_id`)
            REFERENCES `CAR_MAKES` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Populate only if makes table is empty
    $r = $db->query("SELECT COUNT(*) FROM `CAR_MAKES`");
    if ($r && (int)$r->fetch_row()[0] > 0) return;

    $data      = _makes_default_data();
    $make_stmt = $db->prepare("INSERT IGNORE INTO `CAR_MAKES` (`name`) VALUES (?)");
    $mod_stmt  = $db->prepare(
        "INSERT IGNORE INTO `CAR_MODELS` (`make_id`,`name`,`year_from`,`year_to`) VALUES (?,?,?,?)"
    );

    foreach ($data as $make_name => $models) {
        $make_stmt->bind_param('s', $make_name);
        $make_stmt->execute();

        $sr      = $db->query("SELECT `id` FROM `CAR_MAKES` WHERE `name` = '"
                              . $db->real_escape_string($make_name) . "' LIMIT 1");
        $make_id = (int)$sr->fetch_row()[0];

        foreach ($models as [$model_name, $year_from, $year_to]) {
            $mod_stmt->bind_param('isii', $make_id, $model_name, $year_from, $year_to);
            $mod_stmt->execute();
        }
    }

    $make_stmt->close();
    $mod_stmt->close();
}

/** Returns [id => name] sorted by name. */
function makes_list(mysqli $db): array {
    makes_ensure_tables($db);
    $out = [];
    $r   = $db->query("SELECT `id`,`name` FROM `CAR_MAKES` ORDER BY `name` ASC");
    while ($row = $r->fetch_assoc()) $out[(int)$row['id']] = $row['name'];
    return $out;
}

/** Returns all models grouped by make_id as JSON string (for use in JS). */
function makes_all_models_json(mysqli $db): string {
    makes_ensure_tables($db);
    $out = [];
    $r   = $db->query(
        "SELECT `id`,`make_id`,`name`,`year_from`,`year_to` FROM `CAR_MODELS` ORDER BY `name` ASC"
    );
    while ($row = $r->fetch_assoc()) {
        $mid        = (int)$row['make_id'];
        $out[$mid][] = [
            'id'   => (int)$row['id'],
            'name' => $row['name'],
            'yf'   => $row['year_from'],
            'yt'   => $row['year_to'],
        ];
    }
    return json_encode($out, JSON_UNESCAPED_UNICODE);
}

/** Returns models for one make as [ id => ['id','name','year_from','year_to'] ]. */
function makes_models_for(mysqli $db, int $make_id): array {
    makes_ensure_tables($db);
    $out  = [];
    $stmt = $db->prepare(
        "SELECT `id`,`name`,`year_from`,`year_to` FROM `CAR_MODELS` WHERE `make_id`=? ORDER BY `name` ASC"
    );
    $stmt->bind_param('i', $make_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $out[(int)$row['id']] = $row;
    $stmt->close();
    return $out;
}
