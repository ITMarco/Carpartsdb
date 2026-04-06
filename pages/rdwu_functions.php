<?php
// Shared RDW update helper functions.
// Included by both rdwupdate.php (interactive) and rdwsilent.php (background).

// ─── Fetch all Toyota Supras from RDW (paginated) ────────────────────────────
function rdwu_fetch_all_rdw() {
    $all    = [];
    $limit  = 1000;
    $offset = 0;
    $where  = "merk='TOYOTA' AND upper(handelsbenaming) like '%SUPRA%' AND datum_eerste_toelating>='19780101'";
    do {
        $url = 'https://opendata.rdw.nl/resource/m9d7-ebf2.json'
             . '?$where=' . rawurlencode($where)
             . '&$limit='  . $limit
             . '&$offset=' . $offset;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SNLDB/1.0');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if ($err)          return ['error' => 'cURL fout: ' . $err];
        if ($code !== 200) return ['error' => "RDW API antwoordde met HTTP $code"];
        $batch = json_decode($resp, true);
        if (!is_array($batch)) return ['error' => 'Ongeldig JSON antwoord van RDW'];
        foreach ($batch as $row) $all[] = $row;
        $offset += $limit;
    } while (count($batch) === $limit);
    return ['data' => $all];
}

// ─── Parse APK date/status from History field ─────────────────────────────────
function rdwu_parse_apk_from_history($history) {
    if (preg_match('/Vervaldatum APK[^:]*:\s*(\d{2}-\d{2}-\d{4})(\s*\(([^)]+)\))?/i', $history, $m)) {
        $status_str = isset($m[3]) ? strtolower(trim($m[3])) : '';
        return [
            'date_str'   => $m[1],
            'expired'    => strpos($status_str, 'verlopen') !== false,
            'status_str' => $status_str,
        ];
    }
    return null;
}

// ─── Get APK info from RDW vehicle data ───────────────────────────────────────
function rdwu_apk_from_rdw($v) {
    $raw = $v['vervaldatum_apk'] ?? '';
    if ($raw === '') return null;
    $s = preg_replace('/[^0-9]/', '', $raw);
    if (strlen($s) !== 8) return null;
    $date_str      = substr($s, 6, 2) . '-' . substr($s, 4, 2) . '-' . substr($s, 0, 4);
    $exp           = mktime(0, 0, 0, intval(substr($s, 4, 2)), intval(substr($s, 6, 2)), intval(substr($s, 0, 4)));
    $expired       = ($exp < time());
    $expiring_soon = (!$expired && $exp < time() + 60 * 86400);
    return ['date_str' => $date_str, 'expired' => $expired, 'expiring_soon' => $expiring_soon];
}

// ─── Build the text block that gets prepended to History ─────────────────────
function rdwu_prepend_block($reason, $apk_info, $new_status, $old_status) {
    $pad     = function($label) { return str_pad($label . ':', 34); };
    $divider = str_repeat('-', 44) . "\n";
    $t  = "=== RDW Update gedetecteerd: " . date('d-m-Y') . " ===\n";
    $t .= $pad('Reden') . $reason . "\n";
    if ($apk_info) {
        if ($apk_info['expired'])            $apk_lbl = 'VERLOPEN';
        elseif ($apk_info['expiring_soon'])  $apk_lbl = 'verloopt binnenkort';
        else                                 $apk_lbl = 'geldig';
        $t .= $pad('Vervaldatum APK (RDW)') . $apk_info['date_str'] . ' (' . $apk_lbl . ')' . "\n";
    }
    if ($new_status !== $old_status) {
        $t .= $pad('Status gewijzigd naar') . $new_status . "\n";
    }
    $t .= $divider;
    return $t;
}

// ─── Detect all changes for a set of DB cars against a RDW plate map ──────────
// Returns an array of change records ready for display or direct DB apply.
function rdwu_detect_changes(array $db_cars, array $rdw_by_plate) {
    $changed = [];
    $date    = date('Y-m-d H:i:s');
    foreach ($db_cars as $car) {
        $raw_db     = preg_replace('/[^A-Z0-9]/', '', strtoupper($car['License']));
        $cur_status = $car['Choise_Status'];
        $history    = $car['History'] ?? '';

        if (!isset($rdw_by_plate[$raw_db])) {
            if (in_array($cur_status, ['No Road License', 'Wrecked'])) continue;
            $new_status = 'No Road License';
            $prepend    = rdwu_prepend_block('Voertuig niet gevonden in RDW', null, $new_status, $cur_status);
            $changed[]  = [
                'recno'       => $car['RECNO'],
                'license'     => $car['License'],
                'reason'      => 'Niet gevonden in RDW',
                'old_status'  => $cur_status,
                'new_status'  => $new_status,
                'new_history' => $prepend . $history,
            ];
            continue;
        }

        $rdw_v    = $rdw_by_plate[$raw_db];
        $rdw_apk  = rdwu_apk_from_rdw($rdw_v);
        $hist_apk = rdwu_parse_apk_from_history($history);
        $reasons  = [];

        if ($rdw_apk === null) {
            if ($hist_apk !== null) $reasons[] = 'APK datum niet meer aanwezig in RDW';
        } else {
            if ($hist_apk === null) {
                $reasons[] = 'APK datum nieuw gevonden in RDW: ' . $rdw_apk['date_str'];
            } elseif ($hist_apk['date_str'] !== $rdw_apk['date_str']) {
                $reasons[] = 'APK datum gewijzigd: ' . $hist_apk['date_str'] . ' -> ' . $rdw_apk['date_str'];
            } elseif (!$hist_apk['expired'] && $rdw_apk['expired']) {
                $reasons[] = 'APK vervallen: ' . $rdw_apk['date_str'];
            }
        }

        if (empty($reasons)) continue;

        $new_status = $cur_status;
        if ($rdw_apk && $rdw_apk['expired'] && in_array($cur_status, ['Running', 'Forsale', 'No Road License', 'Not Available'])) {
            $new_status = 'Garage';
        } elseif ($rdw_apk && !$rdw_apk['expired'] && in_array($cur_status, ['Garage', 'No Road License', 'Not Available'])) {
            $new_status = 'Running';
        }

        $reason_str = implode('; ', $reasons);
        $prepend    = rdwu_prepend_block($reason_str, $rdw_apk, $new_status, $cur_status);
        $changed[]  = [
            'recno'       => $car['RECNO'],
            'license'     => $car['License'],
            'reason'      => $reason_str,
            'old_status'  => $cur_status,
            'new_status'  => $new_status,
            'new_history' => $prepend . $history,
        ];
    }
    return $changed;
}
