
<div class="content-box">
    <h3>Importeer Supra vanaf URL</h3>
    <img src="images/admin.jpg" style="float:left; margin-left:0px;" alt="img" />
    <br><br>

<?php
// Check authentication - use session from index.php
session_start();

// Require admin authentication
if (!isset($_SESSION['isadmin']) || $_SESSION['isadmin'] !== 1) {
    echo "<div style='color: red;'>Access denied. Please <a href='index.php?navigate=secureadmin'>log in as admin</a> first.</div>";
    echo "</div>"; // Close content-box
    return;
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Extract car data from Marktplaats.nl listing
 */
function extractMarktplaatsData($url) {
    $data = array(
        'success' => false,
        'title' => '',
        'price' => '',
        'description' => '',
        'specs' => array(),
        'images' => array(),
        'error' => ''
    );

    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $data['error'] = 'Ongeldige URL';
        return $data;
    }

    // Only allow marktplaats.nl
    if (strpos($url, 'marktplaats.nl') === false) {
        $data['error'] = 'Alleen Marktplaats.nl URLs worden ondersteund';
        return $data;
    }

    // Fetch the page with a proper user agent
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($httpCode !== 200 || !$html) {
        $data['error'] = 'Kon pagina niet ophalen (HTTP ' . $httpCode . ')';
        return $data;
    }

    // Parse HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    // Extract title
    $titleNodes = $xpath->query('//h1[@class="hz-Listing-title"]');
    if ($titleNodes->length > 0) {
        $data['title'] = trim($titleNodes->item(0)->textContent);
    }

    // Extract price
    $priceNodes = $xpath->query('//span[@class="hz-Listing-price"]');
    if ($priceNodes->length > 0) {
        $data['price'] = trim($priceNodes->item(0)->textContent);
    }

    // Extract description
    $descNodes = $xpath->query('//div[@id="vip-ad-description"]//span');
    if ($descNodes->length > 0) {
        $data['description'] = trim($descNodes->item(0)->textContent);
    }

    // Extract "lees meer" (read more) content if available
    $leesMeerNodes = $xpath->query('//*[contains(@class, "description") or contains(@id, "description")]//text()[not(ancestor::script) and not(ancestor::style)]');
    $leesMeerText = '';
    foreach ($leesMeerNodes as $textNode) {
        $text = trim($textNode->textContent);
        if (!empty($text) && strlen($text) > 20) { // Skip very short text nodes
            $leesMeerText .= $text . "\n";
        }
    }

    // If we found more detailed text, append it to description
    if (!empty($leesMeerText) && strlen($leesMeerText) > strlen($data['description'])) {
        $data['description'] = trim($leesMeerText);
    }

    // Extract specifications - try multiple selectors for robustness
    // Method 1: Marktplaats carAttributesMainGroup structure (current format)
    // Try to find all parent containers first
    $parentContainers = $xpath->query('//div[contains(@class, "carAttributesMainGroup")]');

    foreach ($parentContainers as $container) {
        // Within each container, find label-value pairs
        $labels = $xpath->query('.//div[contains(@class, "-label")]', $container);
        $values = $xpath->query('.//div[contains(@class, "-value")]', $container);

        // If we have matching pairs, extract them
        if ($labels->length === $values->length) {
            for ($i = 0; $i < $labels->length; $i++) {
                $label = trim($labels->item($i)->textContent);
                $value = trim($values->item($i)->textContent);
                if ($label !== '' && $value !== '') {
                    $label = str_replace(':', '', $label);
                    $data['specs'][$label] = $value;
                }
            }
        }
    }

    // Also try direct label-value sibling approach as fallback
    if (count($data['specs']) < 3) {
        $labelNodes = $xpath->query('//div[contains(@class, "label")] | //dt');
        foreach ($labelNodes as $labelNode) {
            $label = trim($labelNode->textContent);
            // Try next sibling
            $valueNode = $labelNode->nextSibling;
            while ($valueNode && $valueNode->nodeType !== 1) {
                $valueNode = $valueNode->nextSibling;
            }
            if ($valueNode) {
                $value = trim($valueNode->textContent);
                if ($label !== '' && $value !== '' && strlen($label) < 50) { // Sanity check
                    $label = str_replace(':', '', $label);
                    $data['specs'][$label] = $value;
                }
            }
        }
    }

    // Method 2: Old Marktplaats hz-Listing-specs format (fallback)
    if (empty($data['specs'])) {
        $specNodes = $xpath->query('//dl[@class="hz-Listing-specs"]/dt | //dl[@class="hz-Listing-specs"]/dd');
        $currentLabel = '';
        foreach ($specNodes as $node) {
            if ($node->nodeName === 'dt') {
                $currentLabel = trim($node->textContent);
            } elseif ($node->nodeName === 'dd' && $currentLabel !== '') {
                $data['specs'][$currentLabel] = trim($node->textContent);
                $currentLabel = '';
            }
        }
    }

    // Method 3: Try to find specs in any dl/dt/dd structure
    if (empty($data['specs'])) {
        $specNodes = $xpath->query('//dl/dt | //dl/dd');
        foreach ($specNodes as $node) {
            if ($node->nodeName === 'dt') {
                $currentLabel = trim($node->textContent);
            } elseif ($node->nodeName === 'dd' && $currentLabel !== '') {
                $data['specs'][$currentLabel] = trim($node->textContent);
                $currentLabel = '';
            }
        }
    }

    // Method 4: Try table-based specs (Marktplaats format)
    if (empty($data['specs'])) {
        // Try multiple table/row selectors
        $tableRows = $xpath->query('//table//tr | //div[contains(@class, "spec")]//tr | //div[contains(@class, "attribute")]//div[@class="row"] | //div[@class="specs-list"]//div');

        foreach ($tableRows as $row) {
            // Try td cells first (table format)
            $cells = $row->getElementsByTagName('td');
            if ($cells->length >= 2) {
                $label = trim($cells->item(0)->textContent);
                $value = trim($cells->item(1)->textContent);
                if ($label !== '' && $value !== '') {
                    // Remove colons from labels
                    $label = str_replace(':', '', $label);
                    $data['specs'][$label] = $value;
                }
            } else {
                // Try span elements (some sites use span for label/value pairs)
                $spans = $row->getElementsByTagName('span');
                if ($spans->length >= 2) {
                    $label = trim($spans->item(0)->textContent);
                    $value = trim($spans->item(1)->textContent);
                    if ($label !== '' && $value !== '') {
                        $label = str_replace(':', '', $label);
                        $data['specs'][$label] = $value;
                    }
                }
            }
        }
    }

    // Method 5: Search for specific fields in visible text (avoiding script tags)
    // Always run this to fill in missing fields
    // Strip out script and style tags to avoid JSON data
    $cleanHtml = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $html);
    $cleanHtml = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i', '', $cleanHtml);

    // Look for specific patterns in the cleaned HTML
    $importantFields = [
        'Bouwjaar' => '/Bouwjaar[:\s]+([0-9]{4})/i',
        'Tellerstand' => '/Tellerstand[:\s]+([0-9.,\s]+)\s*(?:km|KM)?/i',
        'Kilometerstand' => '/Kilometerstand[:\s]+([0-9.,\s]+)\s*(?:km|KM)?/i',
        'Kleur' => '/(?:Kleur|Color)[:\s]+([a-zA-Z]+)(?!\s*interieur)/i',
        'Transmissie' => '/Transmissie[:\s]+([a-zA-Z]+)/i',
        'Brandstof' => '/Brandstof(?:soort)?[:\s]+([a-zA-Z]+)/i',
        'Merk' => '/Merk[:\s]+([a-zA-Z]+)/i',
        'Model' => '/Model[:\s]+([a-zA-Z0-9\s]+)/i'
    ];

    foreach ($importantFields as $field => $pattern) {
        if (!isset($data['specs'][$field])) {
            if (preg_match($pattern, $cleanHtml, $matches)) {
                $value = trim($matches[1]);
                // Clean up the value - remove extra spaces, commas, dots
                $value = strip_tags($value);
                $value = preg_replace('/\s+/', ' ', $value);
                $value = trim($value);
                // Skip if value contains "interieur"
                if ($value !== '' && stripos($value, 'interieur') === false) {
                    $data['specs'][$field] = $value;
                }
            }
        }
    }

    // Extract images
    $imgNodes = $xpath->query('//img[@class="hz-Gallery-image"]');
    foreach ($imgNodes as $img) {
        $src = $img->getAttribute('src');
        if ($src && strpos($src, 'http') === 0) {
            $data['images'][] = $src;
        }
    }

    $data['success'] = true;
    return $data;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_url'])) {
    // SECURITY: Validate CSRF token
    if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            error_log("CSRF token validation failed for importfromurl");
        }
    }

    $url = trim($_POST['url']);
    $extractedData = extractMarktplaatsData($url);

    if (!$extractedData['success']) {
        echo "<div style='color: red;'><strong>Fout:</strong> " . htmlspecialchars($extractedData['error']) . "</div>";
        echo "<p><a href='index.php?navigate=importfromurl'>Probeer opnieuw</a></p>";
    } else {
        // Parse extracted data for car form
        $specs = $extractedData['specs'];

        // Try to extract relevant info - try multiple field names
        $bouwjaar = $specs['Bouwjaar'] ?? $specs['bouwjaar'] ?? '';
        // Clean bouwjaar - keep only 4 digits
        if ($bouwjaar) {
            if (preg_match('/([0-9]{4})/', $bouwjaar, $matches)) {
                $bouwjaar = $matches[1];
            }
        }

        // Kilometerstand has multiple possible names
        $kilometerstand = $specs['Kilometerstand'] ?? $specs['Tellerstand'] ?? $specs['KM stand'] ?? $specs['Mileage'] ?? '';
        // Clean kilometerstand - extract just the number
        if ($kilometerstand) {
            // First remove common units
            $kilometerstand = str_replace([' km', ' KM', 'km', 'KM'], '', $kilometerstand);
            // Then extract the number part (handles formats like "62.402" or "62,402" or "62402")
            $kilometerstand = str_replace(['.', ','], '', $kilometerstand);
            // Keep only the first sequence of digits
            if (preg_match('/^([0-9]+)/', trim($kilometerstand), $matches)) {
                $kilometerstand = $matches[1];
            } else {
                $kilometerstand = '';
            }
        }

        $brandstof = $specs['Brandstof'] ?? $specs['Brandstofsoort'] ?? '';
        $transmissie = $specs['Transmissie'] ?? '';

        // Extract exterior and interior colors separately
        $kleur = '';
        $kleur_interieur = '';

        // Look for exterior and interior colors
        foreach ($specs as $label => $value) {
            // Skip if value contains "interieur" (wrong extraction)
            if (stripos($value, 'interieur') !== false) {
                continue;
            }

            if ($label === 'Kleur' && stripos($label, 'interieur') === false) {
                $kleur = $value;
            } elseif (stripos($label, 'Kleur interieur') !== false) {
                $kleur_interieur = $value;
            } elseif (stripos($label, 'kleur') !== false && stripos($label, 'interieur') === false) {
                $kleur = $value;
            }
        }

        // Clean exterior color - keep only letters and spaces
        if ($kleur) {
            $kleur = preg_replace('/[^a-zA-Z\s]/', '', $kleur);
            $kleur = trim($kleur);
        }

        // Clean interior color
        if ($kleur_interieur) {
            $kleur_interieur = preg_replace('/[^a-zA-Z\s]/', '', $kleur_interieur);
            $kleur_interieur = trim($kleur_interieur);
        }

        $kenteken = $specs['Kenteken'] ?? '';
        if ($kenteken) {
            $kenteken = strtoupper(str_replace('-', '', $kenteken));
        }

        // Combine all text for keyword detection
        $allText = $title . ' ' . $extractedData['description'] . ' ' . implode(' ', $specs);

        // SMART DETECTION: Determine model based on build year
        $model = 'Unknown';
        $engine = 'Unknown';
        $trans_type = 'A'; // Default automatic (more common)

        $year = intval($bouwjaar);

        if ($year >= 1982 && $year <= 1985) {
            // MKII Supra (1982-1985)
            $model = 'MA-60 (MKII)';
        } elseif ($year >= 1986 && $year <= 1992) {
            // MKIII Supra (1986-1992) - 7M engine
            $model = 'MA-70 (MKIII)';
            // Check if turbo
            if (stripos($allText, 'turbo') !== false || stripos($allText, '7mgte') !== false || stripos($allText, '7m-gte') !== false) {
                $engine = '7M-GTE';
            } else {
                $engine = '7M-GE';
            }
        } elseif ($year >= 1993 && $year <= 2002) {
            // MKIV Supra (1993-2002) - 2JZ engine
            $model = 'JA-80 (MKIV)';
            // Check if turbo
            if (stripos($allText, 'turbo') !== false || stripos($allText, '2jzgte') !== false || stripos($allText, '2jz-gte') !== false) {
                $engine = '2JZ-GTE';
            } else {
                $engine = '2JZ-GE';
            }
        } elseif ($year >= 2015 && $year <= 2025) {
            // MKV Supra (2015-2025) - BMW engine, almost always automatic
            $model = 'A-90 (MKV)';
            $trans_type = 'A'; // MKV is almost always automatic

            // Check engine size
            if (stripos($allText, '3.0') !== false || stripos($allText, 'b58') !== false) {
                $engine = 'BMW-B58';
            } elseif (stripos($allText, '2.0') !== false || stripos($allText, 'b48') !== false) {
                $engine = 'BMW-B48';
            }
        }

        // Override with title-based detection if present
        if (stripos($title, 'MK5') !== false || stripos($title, 'A90') !== false || stripos($title, 'GR Supra') !== false) {
            $model = 'A-90 (MKV)';
        } elseif (stripos($title, 'MK4') !== false || stripos($title, 'JZA80') !== false) {
            $model = 'JA-80 (MKIV)';
        } elseif (stripos($title, 'MK3') !== false || stripos($title, 'MA70') !== false) {
            $model = 'MA-70 (MKIII)';
        } elseif (stripos($title, 'MK2') !== false) {
            $model = 'MA-60 (MKII)';
        }

        // SMART DETECTION: Check for manual transmission indicators
        if (stripos($allText, 'R154') !== false || stripos($allText, 'W58') !== false || stripos($allText, 'W55') !== false) {
            $trans_type = 'M'; // R154, W58, W55 are manual transmissions
        }

        // Check for automatic transmission
        if (stripos($transmissie, 'Automaat') !== false || stripos($transmissie, 'Automatic') !== false) {
            $trans_type = 'A';
        } elseif (stripos($transmissie, 'Handgeschakeld') !== false || stripos($transmissie, 'Manual') !== false || stripos($transmissie, 'Handbak') !== false) {
            $trans_type = 'M';
        }
        ?>

        <div style="background-color: #d4edda; border: 1px solid #28a745; padding: 15px; margin: 20px 0;">
            <h3 style="color: green;">✓ Gegevens succesvol opgehaald!</h3>
            <p><strong>Titel:</strong> <?php echo htmlspecialchars($extractedData['title']); ?></p>
            <p><strong>Prijs:</strong> <?php echo htmlspecialchars($extractedData['price']); ?></p>
            <p><strong>Afbeeldingen gevonden:</strong> <?php echo count($extractedData['images']); ?></p>
        </div>

        <?php if (!empty($extractedData['specs'])): ?>
        <details style="margin: 20px 0;">
            <summary style="cursor: pointer; background-color: #e9ecef; padding: 10px; font-weight: bold;">
                🔍 Debug: Opgehaalde specificaties (<?php echo count($extractedData['specs']); ?> gevonden)
            </summary>
            <div style="background-color: #f8f9fa; padding: 10px; margin-top: 5px; font-family: monospace; font-size: 12px;">
                <?php foreach ($extractedData['specs'] as $key => $value): ?>
                    <strong><?php echo htmlspecialchars($key); ?>:</strong> <?php echo htmlspecialchars($value); ?><br>
                <?php endforeach; ?>
            </div>
        </details>
        <?php else: ?>
        <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0;">
            <strong>⚠️ Waarschuwing:</strong> Geen specificaties gevonden. HTML structuur mogelijk gewijzigd.
        </div>
        <?php endif; ?>

        <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0;">
            <strong>⚠️ BELANGRIJK:</strong><br>
            - Controleer alle gegevens voordat je opslaat<br>
            - Verwijder persoonlijke informatie (verkoper naam, telefoonnummer, etc.)<br>
            - Voeg ontbrekende informatie handmatig toe<br>
            - Foto's moeten handmatig gedownload worden
        </div>

        <h3>Review en bewerk gegevens:</h3>

        <form method="post" action="index.php?navigate=procesimport">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />
            <input type="hidden" name="source_url" value="<?php echo htmlspecialchars($url); ?>" />

            <label><strong>Kenteken:</strong></label><br>
            <input type="text" name="License" value="<?php echo htmlspecialchars($kenteken); ?>" style="width: 200px;" /><br><br>

            <label><strong>Eigenaar (te tonen naam):</strong></label><br>
            <input type="text" name="owner" value="" placeholder="Bijv: Te koop via Marktplaats" style="width: 300px;" /><br>
            <small style="color: #666;">Verwijder persoonlijke gegevens!</small><br><br>

            <label><strong>Model:</strong></label>
            <?php if ($model !== 'Unknown'): ?>
                <span style="color: green;">✓ Auto-detected</span>
            <?php else: ?>
                <span style="color: orange;">⚠ Not detected</span>
            <?php endif; ?>
            <br>
            <select name="mark" style="width: 250px;">
                <option value="Unknown" <?php echo ($model === 'Unknown') ? 'selected' : ''; ?>>Unknown</option>
                <option value="MA-46 (MKI)" <?php echo ($model === 'MA-46 (MKI)') ? 'selected' : ''; ?>>Celica Supra MKI</option>
                <option value="MA-60 (MKII)" <?php echo ($model === 'MA-60 (MKII)') ? 'selected' : ''; ?>>Celica Supra MKII</option>
                <option value="MA-70 (MKIII)" <?php echo ($model === 'MA-70 (MKIII)') ? 'selected' : ''; ?>>Supra MKIII MA</option>
                <option value="JZA70" <?php echo ($model === 'JZA70') ? 'selected' : ''; ?>>Supra MKIII JZA</option>
                <option value="JA-80 (MKIV)" <?php echo ($model === 'JA-80 (MKIV)') ? 'selected' : ''; ?>>Supra MKIV</option>
                <option value="A-90 (MKV)" <?php echo ($model === 'A-90 (MKV)') ? 'selected' : ''; ?>>Supra MKV</option>
            </select><br><br>

            <label><strong>Bouwjaar:</strong></label>
            <?php if (!empty($bouwjaar)): ?>
                <span style="color: green;">✓ Auto-ingevuld</span>
            <?php else: ?>
                <span style="color: orange;">⚠ Niet gevonden</span>
            <?php endif; ?>
            <br>
            <input type="text" name="bouwjaar" value="<?php echo htmlspecialchars($bouwjaar); ?>"
                   placeholder="Bijv: 2019" style="width: 150px; <?php echo empty($bouwjaar) ? 'border: 2px solid orange;' : ''; ?>" /><br>
            <small style="color: #666;">Opgehaald: "<?php
                $raw_bouwjaar = $specs['Bouwjaar'] ?? $specs['bouwjaar'] ?? 'Niet gevonden';
                echo htmlspecialchars($raw_bouwjaar);
            ?>"</small><br><br>

            <label><strong>Kilometerstand:</strong></label>
            <?php if (!empty($kilometerstand)): ?>
                <span style="color: green;">✓ Auto-ingevuld</span>
            <?php else: ?>
                <span style="color: orange;">⚠ Niet gevonden</span>
            <?php endif; ?>
            <br>
            <input type="text" name="milage" value="<?php echo htmlspecialchars($kilometerstand); ?>"
                   placeholder="Bijv: 85000" style="width: 150px; <?php echo empty($kilometerstand) ? 'border: 2px solid orange;' : ''; ?>" /><br>
            <small style="color: #666;">Opgehaald: "<?php
                $raw_km = $specs['Kilometerstand'] ?? $specs['Tellerstand'] ?? 'Niet gevonden';
                echo htmlspecialchars($raw_km);
            ?>"</small><br><br>

            <label><strong>Kleur:</strong></label>
            <?php if (!empty($kleur)): ?>
                <span style="color: green;">✓ Auto-ingevuld</span>
            <?php else: ?>
                <span style="color: orange;">⚠ Niet gevonden</span>
            <?php endif; ?>
            <br>
            <input type="text" name="color" value="<?php echo htmlspecialchars($kleur); ?>"
                   placeholder="Bijv: Zwart" style="width: 200px; <?php echo empty($kleur) ? 'border: 2px solid orange;' : ''; ?>" /><br>
            <small style="color: #666;">Opgehaald: "<?php
                $raw_kleur = $specs['Kleur'] ?? $specs['kleur'] ?? 'Niet gevonden';
                echo htmlspecialchars($raw_kleur);
            ?>"</small><br><br>

            <label><strong>Transmissie type:</strong></label><br>
            <input type="radio" name="trans" value="M" <?php echo ($trans_type === 'M') ? 'checked' : ''; ?>> Handbak
            <input type="radio" name="trans" value="A" <?php echo ($trans_type === 'A') ? 'checked' : ''; ?>> Automaat<br><br>

            <label><strong>Motor:</strong></label>
            <?php if ($engine !== 'Unknown'): ?>
                <span style="color: green;">✓ Auto-detected</span>
            <?php else: ?>
                <span style="color: orange;">⚠ Not detected</span>
            <?php endif; ?>
            <br>
            <select name="engine" style="width: 200px;">
                <option value="Unknown" <?php echo ($engine === 'Unknown') ? 'selected' : ''; ?>>Unknown</option>
                <option value="4M-E" <?php echo ($engine === '4M-E') ? 'selected' : ''; ?>>4M-E</option>
                <option value="5M-GE" <?php echo ($engine === '5M-GE') ? 'selected' : ''; ?>>5M-GE</option>
                <option value="7M-GE" <?php echo ($engine === '7M-GE') ? 'selected' : ''; ?>>7M-GE</option>
                <option value="7M-GTE" <?php echo ($engine === '7M-GTE') ? 'selected' : ''; ?>>7M-GTE</option>
                <option value="1JZ-GTE" <?php echo ($engine === '1JZ-GTE') ? 'selected' : ''; ?>>1JZ-GTE</option>
                <option value="1JZ-GTE-VVTI" <?php echo ($engine === '1JZ-GTE-VVTI') ? 'selected' : ''; ?>>1JZ-GTE VVT-I</option>
                <option value="2JZ-GE" <?php echo ($engine === '2JZ-GE') ? 'selected' : ''; ?>>2JZ-GE</option>
                <option value="2JZ-GTE" <?php echo ($engine === '2JZ-GTE') ? 'selected' : ''; ?>>2JZ-GTE</option>
                <option value="BMW-B48" <?php echo ($engine === 'BMW-B48') ? 'selected' : ''; ?>>BMW-B48</option>
                <option value="BMW-B58" <?php echo ($engine === 'BMW-B58') ? 'selected' : ''; ?>>BMW-B58</option>
            </select><br><br>

            <label><strong>Status:</strong></label><br>
            <select name="status" style="width: 200px;">
                <option value="Forsale" selected>For sale</option>
                <option value="Running">Running</option>
                <option value="No Road License">Geen kenteken</option>
                <option value="Wrecked">Wrecked</option>
                <option value="Garage">Garage</option>
                <option value="Not Available">Not Available</option>
            </select><br><br>

            <label><strong>Historie/Notities:</strong></label><br>
            <textarea name="history" rows="10" cols="60" style="width: 100%; max-width: 600px;"><?php
                echo "Bron: " . htmlspecialchars($url) . "\n";
                echo "Prijs: " . htmlspecialchars($extractedData['price']) . "\n";
                if (!empty($kleur_interieur)) {
                    echo "Kleur interieur: " . htmlspecialchars($kleur_interieur) . "\n";
                }
                echo "\n" . htmlspecialchars($extractedData['description']);
            ?></textarea><br>
            <small style="color: #666;">Verwijder persoonlijke informatie voordat je opslaat!</small><br><br>

            <label><strong>Modificaties:</strong></label><br>
            <textarea name="mods" rows="5" cols="60" style="width: 100%; max-width: 600px;"></textarea><br><br>

            <?php if (count($extractedData['images']) > 0): ?>
            <div style="background-color: #d1ecf1; border: 1px solid #17a2b8; padding: 15px; margin: 20px 0;">
                <strong>📷 Gevonden afbeeldingen (<?php echo count($extractedData['images']); ?>):</strong><br><br>
                <small>Kopieer deze URLs en download handmatig:</small><br>
                <textarea readonly style="width: 100%; max-width: 600px; height: 150px; font-size: 11px;"><?php
                    foreach ($extractedData['images'] as $img) {
                        echo htmlspecialchars($img) . "\n";
                    }
                ?></textarea>
            </div>
            <?php endif; ?>

            <br>
            <input type="submit" value="Supra toevoegen aan database" style="padding: 10px 20px; font-size: 16px; background-color: #28a745;" />
            <button type="button" onclick="window.location.href='index.php?navigate=importfromurl'" style="padding: 10px 20px; font-size: 16px; margin-left: 10px;">
                Annuleer
            </button>
        </form>

        <?php
    }
} else {
    // Show URL input form
    ?>
    <h3>Plak een Marktplaats.nl advertentie URL:</h3>

    <div style="background-color: #d1ecf1; border: 1px solid #17a2b8; padding: 15px; margin: 20px 0;">
        <strong>ℹ️ Hoe werkt dit?</strong><br>
        1. Zoek een Supra advertentie op Marktplaats.nl<br>
        2. Kopieer de URL (bijv: https://www.marktplaats.nl/v/auto-s/toyota/m2324146835-...)<br>
        3. Plak de URL hieronder<br>
        4. Controleer en bewerk de opgehaalde gegevens<br>
        5. Verwijder persoonlijke informatie<br>
        6. Sla op in de database
    </div>

    <form method="post" action="index.php?navigate=importfromurl">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" />

        <label for="url"><strong>Marktplaats.nl URL:</strong></label><br>
        <input type="url" id="url" name="url" required
               placeholder="https://www.marktplaats.nl/v/auto-s/toyota/..."
               style="width: 100%; max-width: 600px; padding: 10px; font-size: 14px;" /><br><br>

        <input type="submit" name="import_url" value="Gegevens ophalen" style="padding: 10px 20px; font-size: 16px;" />
        <button type="button" onclick="window.location.href='index.php?navigate=adminpanel'"
                style="padding: 10px 20px; font-size: 16px; margin-left: 10px;">
            Terug naar admin panel
        </button>
    </form>

    <div style="background-color: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin: 20px 0;">
        <strong>⚠️ Let op:</strong><br>
        - Dit is alleen bedoeld voor handmatig gebruik (max 1x per week)<br>
        - Verwijder altijd persoonlijke informatie voordat je opslaat<br>
        - Controleer alle gegevens voordat je opslaat<br>
        - Download foto's handmatig als je ze wilt opslaan
    </div>
    <?php
}
?>

</div>
