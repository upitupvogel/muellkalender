<?php

// Klasse deklarieren, Name muss exakt dem Ordnernamen entsprechen
class Muellkalender extends IPSModule
{
    private $debug;

    public function Create()
    {
        // Die Eltern-Methode aufrufen
        parent::Create();

        // Eigenschaften registrieren
        $this->RegisterPropertyString('ICS_URL', '');
        $this->RegisterPropertyString('LOCATION', '');
        $this->RegisterPropertyString('POSTAL_CODE', '');
        $this->RegisterPropertyString('STREET', '');
        $this->RegisterPropertyString('WASTE_TYPES', 'restmuell,biomuell,gelbersack,altpapier');
        // KORREKTUR: AbfallID als String registrieren, passend zur form.json
        $this->RegisterPropertyString('AbfallID', 'restmuell');
        $this->RegisterPropertyInteger('UPDATE_INTERVAL', 3600); // Standard: 1 Stunde
        $this->RegisterPropertyString('ICS_EVENT_MATCH', '');
        $this->RegisterPropertyBoolean('DEBUG_ENABLED', false);

        // Variablen registrieren
        // ~HTMLBox Profil für HTML-Inhalte
        $this->RegisterVariableString('HTMLBOX', 'Müllkalender HTML', '~HTMLBox');
        // Variablen für die nächste Abholung
        $this->RegisterVariableString('NEXTWASTE', 'Nächste Abholung', ''); // Profil je nach Format anpassen, z.B. ~Text oder Datumsprofil
        $this->RegisterVariableBoolean('WaistNextTime', 'Nächste Abholung Heute', '~Switch'); // Schalter für Heute/Nicht Heute
        
        // Timer registrieren
        // WICHTIG: Die Funktionsnamen im Timer müssen Aktionen sein, die von RequestAction behandelt werden,
        // oder globale Funktionen, die die Instanz-ID übergeben bekommen und dann die Methode aufrufen.
        // Wir nutzen hier IPS_RequestAction, um die Methoden der Klasse zu triggern.
        $this->RegisterTimer('NextWasteUpdateTimer', 0, 'IPS_RequestAction($_IPS[\'TARGET\'], "UpdateNextWaste", "");');
        $this->RegisterTimer('HTMLBoxUpdateTimer', 0, 'IPS_RequestAction($_IPS[\'TARGET\'], "UpdateHTMLBox", "");');
        $this->RegisterTimer('WasteListUpdateTimer', 0, 'IPS_RequestAction($_IPS[\'TARGET\'], "UpdateWasteList", "");');
    }

    public function ApplyChanges()
    {
        // Die Eltern-Methode aufrufen
        parent::ApplyChanges();

        $this->debug = $this->ReadPropertyBoolean('DEBUG_ENABLED');

        // Timer-Intervals setzen
        $updateInterval = $this->ReadPropertyInteger('UPDATE_INTERVAL');
        $this->SetTimerInterval('NextWasteUpdateTimer', $updateInterval * 1000);
        $this->SetTimerInterval('HTMLBoxUpdateTimer', $updateInterval * 1000);
        $this->SetTimerInterval('WasteListUpdateTimer', $updateInterval * 1000);

        // Standard-Status auf "Aktiv" setzen
        $this->SetStatus(102); 

        // Initialen Update-Prozess starten
        $this->UpdateWasteList();
        $this->UpdateNextWaste();
        $this->UpdateHTMLBox();
    }

    /**
     * Behandelt Aktionen, die über RequestAction ausgelöst werden (z.B. von Timern oder WebFront)
     */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'UpdateNextWaste':
                $this->UpdateNextWaste();
                break;
            case 'UpdateHTMLBox':
                $this->UpdateHTMLBox();
                break;
            case 'UpdateWasteList':
                $this->UpdateWasteList();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'Unhandled action: ' . $Ident, 0);
                break;
        }
    }

    /**
     * Aktualisiert die Liste der Abfalldaten aus der ICS-URL.
     */
    public function UpdateWasteList()
    {
        $this->SendDebug(__FUNCTION__, 'Starting UpdateWasteList...', 0);
        
        $icsUrl = $this->ReadPropertyString('ICS_URL');
        if (empty($icsUrl)) {
            $this->SendDebug(__FUNCTION__, 'ICS URL ist leer. Update abgebrochen.', 0);
            return;
        }

        $icsContent = @file_get_contents($icsUrl);

        if ($icsContent === false) {
            $this->SendDebug(__FUNCTION__, 'Fehler beim Laden der ICS-Datei von: ' . $icsUrl, 1); // 1 = Error
            $this->SetStatus(201); // Fehlerstatus setzen (z.B. HTTP_NOT_FOUND)
            return;
        } else {
            $this->SetStatus(102); // Status zurück auf OK setzen
        }

        $regex = '/BEGIN:VEVENT.*?DTSTART(?:;VALUE=DATE)?:(?<start>[0-9]{8}).*?SUMMARY:(?<summary>[^\n\r]+)/is';
        preg_match_all($regex, $icsContent, $matches, PREG_SET_ORDER);

        $wasteData = [];
        foreach ($matches as $match) {
            $date = DateTime::createFromFormat('Ymd', $match['start']);
            if ($date) {
                $summary = trim($match['summary']);
                $wasteData[] = ['date' => $date->format('Y-m-d'), 'summary' => $summary];
            }
        }

        // Sortieren nach Datum
        usort($wasteData, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Abfalldaten persistent speichern
        $this->SetProperty('ABFALLDATEN', json_encode($wasteData));
        if ($this->Has<ctrl61>
        $this->ApplyChanges(); // Änderungen übernehmen
        $this->SendDebug(__FUNCTION__, 'Abfalldaten wurden aktualisiert und gespeichert.', 0);
    }

    /**
     * Berechnet und aktualisiert die nächste Abholung.
     */
    public function UpdateNextWaste()
    {
        $this->SendDebug(__FUNCTION__, 'Starting UpdateNextWaste...', 0);

        $abfallDatenJson = $this->ReadPropertyString('ABFALLDATEN');
        $abfallDaten = json_decode($abfallDatenJson, true);
        if (!is_array($abfallDaten)) {
            $this->SendDebug(__FUNCTION__, 'Keine gültigen Abfalldaten gefunden.', 0);
            $this->SetValue('NEXTWASTE', 'Keine Daten');
            $this->SetValue('WaistNextTime', false);
            return;
        }

        $targetAbfallID = $this->ReadPropertyString('AbfallID'); // Der ausgewählte Abfalltyp
        $icsEventMatch  = $this->ReadPropertyString('ICS_EVENT_MATCH'); // Optionaler Regex

        $today = new DateTime();
        $nextWasteDate = null;
        $nextWasteSummary = '';

        foreach ($abfallDaten as $item) {
            $itemDate = new DateTime($item['date']);
            $summary = $item['summary'];

            // Prüfen, ob die Abfallart mit dem konfigurierten Typ übereinstimmt
            $matchFound = false;
            if (!empty($icsEventMatch)) {
                 if (preg_match('/' . preg_quote($icsEventMatch, '/') . '/i', $summary)) {
                    $matchFound = true;
                }
            } else {
                // Wenn kein RegEx gesetzt ist, wird der Mülltyp anhand des Summary-Strings ermittelt.
                // Dies ist eine Vereinfachung und müsste für exakte Treffer evtl. verfeinert werden.
                $abfallarten = [
                    'restmuell'  => ['Restmüll', 'Hausmüll', 'graue Tonne'],
                    'biomuell'   => ['Biomüll', 'braune Tonne'],
                    'gelbersack' => ['Gelber Sack', 'Plastik'],
                    'altpapier'  => ['Altpapier', 'blaue Tonne']
                ];

                if (isset($abfallarten[$targetAbfallID])) {
                    foreach ($abfallarten[$targetAbfallID] as $keyword) {
                        if (stripos($summary, $keyword) !== false) {
                            $matchFound = true;
                            break;
                        }
                    }
                }
            }

            if ($matchFound && $itemDate >= $today) {
                if ($nextWasteDate === null || $itemDate < $nextWasteDate) {
                    $nextWasteDate = $itemDate;
                    $nextWasteSummary = $summary;
                }
            }
        }

        if ($nextWasteDate) {
            $this->SetValue('NEXTWASTE', $nextWasteDate->format('d.m.Y') . ' - ' . $nextWasteSummary);
            
            // Prüfen, ob es heute ist
            $isToday = ($nextWasteDate->format('Y-m-d') == $today->format('Y-m-d'));
            $this->SetValue('WaistNextTime', $isToday);
        } else {
            $this->SetValue('NEXTWASTE', 'Keine anstehende Abholung gefunden');
            $this->SetValue('WaistNextTime', false);
        }

        $this->SendDebug(__FUNCTION__, 'NextWaste updated.', 0);
    }

    /**
     * Aktualisiert den Inhalt der HTML-Box Variable.
     */
    public function UpdateHTMLBox()
    {
        $this->SendDebug(__FUNCTION__, 'Starting UpdateHTMLBox...', 0);

        $abfallDatenJson = $this->ReadPropertyString('ABFALLDATEN');
        $abfallDaten = json_decode($abfallDatenJson, true);
        if (!is_array($abfallDaten)) {
            $this->SendDebug(__FUNCTION__, 'Keine gültigen Abfalldaten für HTML-Box gefunden.', 0);
            $this->SetValue('HTMLBOX', '<html><body><p>Keine Abfalldaten verfügbar.</p></body></html>');
            return;
        }

        $wasteTypes = array_map('trim', explode(',', $this->ReadPropertyString('WASTE_TYPES')));
        $today = new DateTime();
        $htmlContent = '<html><body>';
        $htmlContent .= '<h3>Nächste Müllabfuhr</h3>';
        $htmlContent .= '<table style="width:100%; border-collapse: collapse;">';
        $htmlContent .= '<thead><tr><th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Datum</th><th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Art</th></tr></thead>';
        $htmlContent .= '<tbody>';

        $processedDates = [];
        $displayedCount = 0;
        $maxDisplay = 7; // Max. 7 Einträge anzeigen

        foreach ($abfallDaten as $item) {
            $itemDate = new DateTime($item['date']);
            $summary = $item['summary'];

            // Nur zukünftige oder heutige Einträge
            if ($itemDate < $today && $itemDate->format('Y-m-d') != $today->format('Y-m-d')) {
                continue;
            }

            // Sicherstellen, dass nur relevante Mülltypen angezeigt werden, wenn WAVE_TYPES gesetzt ist
            $relevant = false;
            if (empty($wasteTypes) || (count($wasteTypes) == 1 && empty($wasteTypes[0]))) {
                $relevant = true; // Wenn WASTE_TYPES leer ist, alles anzeigen
            } else {
                foreach ($wasteTypes as $type) {
                    if (stripos($summary, $type) !== false) {
                        $relevant = true;
                        break;
                    }
                }
            }

            if ($relevant) {
                // Nur den ersten Eintrag pro Datum anzeigen, wenn mehrere Typen am selben Tag abgeholt werden
                if (!in_array($itemDate->format('Y-m-d'), $processedDates)) {
                    if ($displayedCount < $maxDisplay) {
                        $htmlContent .= '<tr>';
                        $htmlContent .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $itemDate->format('d.m.Y') . '</td>';
                        $htmlContent .= '<td style="border: 1px solid #ddd; padding: 8px;">' . htmlspecialchars($summary) . '</td>';
                        $htmlContent .= '</tr>';
                        $processedDates[] = $itemDate->format('Y-m-d');
                        $displayedCount++;
                    }
                }
            }
        }
        $htmlContent .= '</tbody></table>';
        $htmlContent .= '</body></html>';

        $this->SetValue('HTMLBOX', $htmlContent);
        $this->SendDebug(__FUNCTION__, 'HTMLBox updated.', 0);
    }
}
?>
