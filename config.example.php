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

// Production (Zugang zur produktiven Datenbank)
// Access to the database of the SQL-Server
$g_adm_srv  = 'host';     // Host
$g_adm_port = 3306;     // Port
$g_adm_db   = '';     // Database-Name
$g_adm_usr  = ''; // Username
$g_adm_pw   = ''; // Password