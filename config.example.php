<?php
// Vorlage: diese Variablen in die config.php der Backup-Instanz übernehmen
// (config.php nicht ins Repository einchecken)

// Zugang zur Datenbank der Backup-Instanz (Ziel des Imports, NICHT die produktive Datenbank)
$backup_adm_srv  = 'localhost'; // Host
$backup_adm_port = 3306;        // Port
$backup_adm_db   = '';          // Datenbankname
$backup_adm_usr  = '';          // Benutzername
$backup_adm_pw   = '';          // Passwort (Rechte: DROP, CREATE, INSERT)

// URL der Backup-Instanz (url_backup)
$backup_root_path = 'https://backup.example.de';

// Absoluter Serverpfad zum Backup-Verzeichnis (enthält <DB_NAME>/<Zeitstempel>.sql.gz),
// muss außerhalb des Web-Roots liegen
$backup_dir = '/pfad/zu/db_backup';

// Optional: Pfad zum Programm mysql für den Import
$backup_mysql = 'mysql';

// Optional: Pfad zum Programm mysqldump für die manuelle Sicherung
$backup_mysqldump = 'mysqldump';

// Zugang zur produktiven Datenbank: die Variablen $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr
// und $g_adm_pw sind in der config.php der Admidio-Installation bereits vorhanden und werden
// nur lesend verwendet (Auswahl der Backups, manuelle Sicherung, Schutzprüfung).