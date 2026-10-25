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

1. Ein Cron-Job führt [dump.php](dump.php) aus und legt pro Datenbank und Lauf eine Datei
   `<DB_NAME>/<Zeitstempel>.sql.gz` im Backup-Verzeichnis (`../db_backup/`) ab.
2. Das Plugin wind in der Produktiv-Instanz installeirt und die vorhandenen Backups zur Auswahl auf (Datenbank, Zeitstempel, Größe).
3. Nach Auswahl einer Datei und Bestätigung wird deren Inhalt in die Datenbank der Backup-Instanz
   importiert. Der bisherige Inhalt dieser Datenbank wird dabei ersetzt.
3b. es soll eine manuelle sicherung erzeugt werden können vom aktuellen Zustand.
4. Weiterhin gibt es einen optional schritt 'copy Admidio', der alle files der Admidio Instanz in den ordner der backup - instanz kopiert - config.php muss natürlich extra für die zweite instanz angepasst werden. Weiterhin soll eine Style anpassung erfolgen, der die Farbe ändert, damit ersichtlich ist, dass man nicht mehr auf produktion ist.
5. In `url_backup` ist anschließend der Stand zum Zeitpunkt des Backups sichtbar.

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
   | `$backup_prod_root_path` | URL der produktiven Instanz (`url_prod`), nur zur Anzeige |
   | `$backup_dir` | Serverpfad zum Backup-Verzeichnis, außerhalb des Web-Roots |
   | `$backup_source_db` | Name der produktiven Datenbank, deren Backups angeboten werden |
   | `$backup_mysql` | optional: Pfad zum Programm `mysql` für den Import |
   | `$backup_keep_days` | optional: ältere Backups nicht anbieten (`0` = alle) |
   | `$backup_import_table_prefix` | optional: Tabellenpräfix der Admidio-Tabellen |

   Die Backup-Datenbank muss sich von allen Datenbanken in `dump-config.php` unterscheiden.
3. Cron-Job für `dump.php` auf dem Produktivserver einrichten.
4. Plugin in der Backup-Instanz als Menüpunkt einbinden.

## Aufbau

- [dump.php](dump.php) – Backup-Skript für den Cron-Job (erzeugt `.sql.gz` je Datenbank)
- `index.php` – Einstiegspunkt, Autoloader, Rechteprüfung, Auswahlliste
- `classes/` – Klassen unter dem Namespace `AdmBackup\` (Backup-Dateien ermitteln, Import)
- `tests/` – PHPUnit-Tests, laufen ohne Admidio
- `config.php` – lokale Konfiguration (nicht im Repository)

## Entwicklung

Programmierrichtlinien und Konventionen stehen in [CLAUDE.md](CLAUDE.md).
Hinweis: Diese Datei stammt aus dem Projekt „Datenkonsistenz-Prüfungen“ und ist noch an dieses
Plugin anzupassen (Namespace, Struktur, Prüfungen/`checks/` entfallen).
