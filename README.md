# Admidio Backup-Viewer (`adm-backup`)

Admidio-5-Plugin, mit dem sich Datenbank-Backups der produktiven Admidio-Instanz in einer
separaten Backup-Instanz ansehen lassen. Das Plugin lädt ein ausgewähltes Backup in die Datenbank
der Backup-Instanz; danach kann der Stand dort in der gewohnten Admidio-Oberfläche geprüft werden.

## Idee

| Instanz | URL | Aufgabe |
|---|---|---|
| Produktiv | `url_prod`   | Live-System, wird per Cron-Job gesichert |
| Backup    | `url_backup` | Zweite Admidio-Installation mit eigener Datenbank, in die das Plugin Backups einspielt |

Ablauf:

1. Ein Cron-Job ruft [dump.php](dump.php) per HTTP-GET mit Token auf und legt pro Datenbank und
   Lauf eine Datei `<DB_NAME>/<Zeitstempel>.sql.gz` im Backup-Verzeichnis (`../db_backup/`) ab.
   Die Zugangsdaten stehen in `../dump-config.php`.
2. Das Plugin wird in der Produktiv-Instanz installiert und listet die vorhandenen Backups der
   produktiven Datenbank zur Auswahl auf (Zeitstempel, Größe).
3. Nach Auswahl einer Datei und Bestätigung wird deren Inhalt in die Datenbank der Backup-Instanz
   importiert. Der bisherige Inhalt dieser Datenbank wird dabei ersetzt.
4. Zusätzlich kann manuell eine Sicherung des aktuellen Zustands der produktiven Datenbank
   erzeugt werden.
5. Optional: der Schritt „Admidio kopieren“ kopiert alle Dateien der Admidio-Instanz in den Ordner
   der Backup-Instanz. Deren `config.php` wird dabei auf die Backup-Datenbank und `url_backup`
   angepasst. Außerdem wird das Layout farblich geändert, damit sofort erkennbar ist, dass man
   sich nicht auf der Produktion befindet.
6. In `url_backup` ist anschließend der Stand zum Zeitpunkt des Backups sichtbar.

## Funktionen

- Auswahlliste der verfügbaren Backup-Dateien (neueste zuerst), gefiltert auf die Datenbank der
  produktiven Admidio-Instanz.
- Anzeige von Zeitstempel (aus dem Dateinamen) und Dateigröße.
- Einspielen des gewählten Backups (`.sql.gz`) in die Backup-Datenbank.
- Anzeige, welches Backup zuletzt eingespielt wurde.
- Zugriff nur für Administratoren der Backup-Instanz.

## Sicherheit

- Das Plugin darf **nie** in die produktive Datenbank schreiben. Ziel ist ausschließlich die
  Datenbank der Backup-Instanz (`$gDb` der Backup-Installation).
- Importiert wird nur aus dem konfigurierten Backup-Verzeichnis; der Dateiname kommt aus einer
  Auswahlliste und wird gegen die tatsächlich vorhandenen Dateien geprüft (kein freier Pfad).
- Das Einspielen ist ein POST-Formular mit CSRF-Token und Bestätigungsdialog.
- Das Backup-Verzeichnis muss außerhalb des Web-Roots liegen oder per Webserver gesperrt sein.

## Voraussetzungen

- Admidio 5 (`url_prod` und `url_backup`), PHP 8.5+
- Auf dem Server der Produktivinstanz: `mysqldump`, `gzip` und ein Cron-Job für `dump.php`
- Lesezugriff der Backup-Instanz auf das Backup-Verzeichnis
- Eigene, von der Produktivinstanz getrennte Datenbank für die Backup-Instanz

## Installation

1. Plugin-Ordner nach `adm_plugins/adm-backup` in der Produktiv-Instanz kopieren.
2. `config.php` aus [config.example.php](config.example.php) anlegen. Einstellungen:

   | Variable | Bedeutung |
   |---|---|
   | `$backup_adm_srv`, `$backup_adm_port` | Host und Port des SQL-Servers der Backup-Datenbank |
   | `$backup_adm_db` | Name der Backup-Datenbank (Ziel des Imports) |
   | `$backup_adm_usr`, `$backup_adm_pw` | Zugangsdaten der Backup-Datenbank |
   | `$backup_root_path` | URL der Backup-Instanz (`url_backup`) |
   | `$backup_dir` | Serverpfad zum Backup-Verzeichnis, außerhalb des Web-Roots |
   | `$backup_mysql`, `$backup_mysqldump` | optional: Pfade zu `mysql` (Import) und `mysqldump` (manuelle Sicherung) |

   Die produktive Datenbank (`$g_adm_*`) ist in der Admidio-`config.php` bereits vorhanden. Ihr
   Name bestimmt, welche Backups angeboten werden. Die Backup-Datenbank muss sich davon unterscheiden.
3. Cron-Job für `dump.php` auf dem Produktivserver einrichten.
4. Plugin in der Backup-Instanz als Menüpunkt einbinden.

## Aufbau

- [dump.php](dump.php) – Backup-Skript für den Cron-Job (erzeugt `.sql.gz` je Datenbank)
- `index.php` – Einstiegspunkt: Rechteprüfung (nur Administratoren), Autoloader, Liste, Aktionen
- `classes/` – Klassen unter dem Namespace `AdmBackup\`: `BackupConfig`, `DatabaseAccess`,
  `BackupFile`, `BackupRepository` (Dateien, letzter Import), `BackupImporter`, `BackupCreator`
- `tests/` – PHPUnit-Tests, laufen ohne Admidio (`composer install`, dann `composer check`)
- `config.php` – lokale Konfiguration (nicht im Repository)

## Entwicklung

Programmierrichtlinien und Konventionen stehen in [CLAUDE.md](CLAUDE.md).
