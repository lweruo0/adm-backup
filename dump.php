<?php
/**
 * Datenbanken als .sql.gz sichern (Aufruf per Cron-Job als HTTP-GET mit Token:
 * dump.php?token=<token>; auf der Kommandozeile: php dump.php <token>).
 *
 * Konfiguration: ../dump-config.php (ein Ordner höher). Je Datenbank entsteht die Datei
 * <backupDirectory>/<DB_NAME>/<Zeitstempel>.sql.gz.
 * mysqldump wird ohne Shell gestartet, das Passwort wird über die Umgebung übergeben
 * (nicht sichtbar in der Prozessliste) und die Kompression erfolgt in PHP.
 */

$config = require dirname(__DIR__) . '/dump-config.php';

$givenToken = PHP_SAPI === 'cli' ? ($argv[1] ?? '') : ($_GET['token'] ?? '');
if (!is_string($givenToken)
    || $config['token'] === ''
    || $config['token'] === 'HIER_ZUFAELLIGES_TOKEN_EINTRAGEN'
    || !hash_equals($config['token'], $givenToken)) {
    http_response_code(403);
    exit('Zugriff verweigert');
}

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(0);
ignore_user_abort(true);

$timestamp = date('Y-m-d_H-i-s');
$hasError = false;

foreach ($config['databases'] as $dbConfig) {
    // DB_HOST kann Port enthalten, z. B. "localhost:3306"
    $dbHost = $dbConfig['DB_HOST'];
    $dbPort = $dbConfig['DB_PORT'] ?? 3306;

    if (strpos($dbHost, ':') !== false) {
        [$dbHost, $dbPort] = explode(':', $dbHost, 2);
    }

    $targetDirectory = $config['backupDirectory'] . '/' . $dbConfig['DB_NAME'];
    $filename = $targetDirectory . '/' . $timestamp . '.sql.gz';

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0750, true) && !is_dir($targetDirectory)) {
        echo 'FEHLER: Verzeichnis kann nicht angelegt werden: ' . $targetDirectory . "\n";
        $hasError = true;
        continue;
    }

    // --single-transaction ist gut für InnoDB
    $command = [
        $config['mysqldump'],
        '--host=' . $dbHost,
        '--port=' . $dbPort,
        '--user=' . $dbConfig['DB_USER'],
        '--default-character-set=utf8mb4',
        '--single-transaction',
        '--quick',
        $dbConfig['DB_NAME'],
    ];

    $errorFile = tempnam(sys_get_temp_dir(), 'dump_err_');
    $process = proc_open(
        $command,
        [1 => ['pipe', 'w'], 2 => ['file', $errorFile, 'w']],
        $pipes,
        null,
        ['MYSQL_PWD' => $dbConfig['DB_PASSWORD']] + getenv()
    );

    $success = false;
    if (is_resource($process)) {
        $gzipHandle = gzopen($filename, 'wb9');
        if ($gzipHandle !== false) {
            while (!feof($pipes[1])) {
                gzwrite($gzipHandle, (string) fread($pipes[1], 65536));
            }
            gzclose($gzipHandle);
        }
        fclose($pipes[1]);
        $success = proc_close($process) === 0 && $gzipHandle !== false;
    }

    if (!$success) {
        echo 'FEHLER: Dump von ' . $dbConfig['DB_NAME'] . " fehlgeschlagen:\n";
        echo (string) file_get_contents($errorFile) . "\n";
        if (is_file($filename)) {
            unlink($filename);
        }
        $hasError = true;
    } else {
        echo 'OK: ' . $dbConfig['DB_NAME'] . ' -> ' . $filename . ' (' . number_format((int) filesize($filename)) . " Bytes)\n";
    }

    unlink($errorFile);
}

if ($hasError) {
    http_response_code(500);
    echo "Backup mit Fehlern beendet.\n";
    exit(1);
}

echo "Backup erfolgreich beendet.\n";
