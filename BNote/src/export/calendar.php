<?php
// ICS-Header hL, 17.4.25
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="kalender.ics"');

// ==========================
// XML-Konfiguration einlesen
// ==========================
$xmlPath = __DIR__ . '/../../config/database.xml';

if (!file_exists($xmlPath)) {
    die("Fehler: database.xml nicht gefunden.");
}

$xml = simplexml_load_file($xmlPath);
if (!$xml) {
    die("Fehler beim Parsen der database.xml.");
}

$host     = (string) $xml->Server;
$port     = (string) $xml->Port;
$dbname   = (string) $xml->Name;
$username = (string) $xml->User;
$password = (string) $xml->Password;

// ==========================
// Datenbankverbindung
// ==========================
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $db = new PDO($dsn, $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Verbindung fehlgeschlagen: " . $e->getMessage());
}

// ==========================
// Hilfsfunktionen
// ==========================
function icalDateLocal($datetime) {
    return date('Ymd\THis', strtotime($datetime));
}

function foldLine($text) {
    $text = str_replace(["\r", "\n"], "\\n", $text);
    $lines = [];
    while (strlen($text) > 75) {
        $cut = 75;
        while ($cut > 0 && (ord($text[$cut]) & 0xC0) === 0x80) {
            $cut--; // nicht mitten im UTF-8-Zeichen trennen
        }
        $lines[] = substr($text, 0, $cut);
        $text = ' ' . substr($text, $cut);
    }
    $lines[] = $text;
    return implode("\r\n", $lines);
}

// ==========================
// Kalender-Start
// ==========================
echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "CALSCALE:GREGORIAN\r\n";
echo "METHOD:PUBLISH\r\n";
echo "PRODID:-//bancanta chor//Kalenderexport//DE\r\n";
echo "X-WR-CALNAME:bancanta chor\r\n";
echo "X-WR-TIMEZONE:Europe/Berlin\r\n";

// Zeitzonendefinition (für Outlook/iOS)
echo "BEGIN:VTIMEZONE\r\n";
echo "TZID:Europe/Berlin\r\n";
echo "BEGIN:STANDARD\r\n";
echo "DTSTART:19701025T030000\r\n";
echo "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10\r\n";
echo "TZOFFSETFROM:+0200\r\n";
echo "TZOFFSETTO:+0100\r\n";
echo "TZNAME:CET\r\n";
echo "END:STANDARD\r\n";
echo "BEGIN:DAYLIGHT\r\n";
echo "DTSTART:19700329T020000\r\n";
echo "RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3\r\n";
echo "TZOFFSETFROM:+0100\r\n";
echo "TZOFFSETTO:+0200\r\n";
echo "TZNAME:CEST\r\n";
echo "END:DAYLIGHT\r\n";
echo "END:VTIMEZONE\r\n";

// ==================================================
// Events aus DB-view kalender_ics laden und ausgeben
// ==================================================
$sql = "SELECT uid, dtstart, dtend, summery, description, location, dtstamp FROM kalender_ics";
$stmt = $db->query($sql);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "BEGIN:VEVENT\r\n";
    echo "UID:" . $row['uid'] . "\r\n";
    echo "DTSTAMP:" . date('Ymd\THis\Z', strtotime($row['dtstamp'])) . "\r\n";
    echo "DTSTART;TZID=Europe/Berlin:" . icalDateLocal($row['dtstart']) . "\r\n";

    if (!empty($row['dtend'])) {
        echo "DTEND;TZID=Europe/Berlin:" . icalDateLocal($row['dtend']) . "\r\n";
    }

    if (!empty($row['summery'])) {
        echo foldLine("SUMMARY:" . $row['summery']) . "\r\n";
    }

    if (!empty($row['description'])) {
        // Umbrüche entfernen → verhindert doppelte Leerzeilen auf iOS/macOS
        $desc = preg_replace("/\r\n|\r|\n/", " ", $row['description']);
        $desc = str_replace("\\n", " ", $desc);
        echo foldLine("DESCRIPTION:" . $desc) . "\r\n";
    }

    if (!empty($row['location'])) {
        echo foldLine("LOCATION:" . $row['location']) . "\r\n";
    }

    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";