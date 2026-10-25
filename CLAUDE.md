# CLAUDE.md

Admidio-5-Plugin „Backup-Viewer“ (`adm_plugins/adm-backup`). Fachliche Beschreibung,
Bedienung und Aufbau stehen in der @README.md und werden hier nicht wiederholt.

Admidio-Quellen:
https://github.com/Admidio/admidio/tree/v5.0

## Programmierrichtlinien

Grundlage sind die Admidio-Programmierrichtlinien:
https://www.admidio.org/dokuwiki/doku.php?id=en:entwickler:programmierrichtlinien


Die wichtigsten Regeln daraus, die in diesem Repo gelten:

- **Einrückung**: 4 Leerzeichen, keine Tabs (PHP, JS, HTML).
- **Klammern**: Öffnende Klammer in derselben Zeile wie `if`/`for`/`while`/`switch`, Leerzeichen
  davor. Immer Klammern setzen, auch bei einzeiligen Blöcken. `} elseif (...) {` und `} else {`
  in einer Zeile.
- **Benennung**: Klassen `PascalCase`, Funktionen und Variablen `camelCase`, Konstanten
  `GROSS_MIT_UNTERSTRICH`. Globale Admidio-Variablen tragen das Präfix `g` (`$gDb`, `$gCurrentUser`),
  Request-Parameter das Präfix `get`/`post` (`$getMode`). Keine kryptischen Abkürzungen.
- **Parameter** mit Standardwerten stehen am Ende der Signatur.
- **PHP-Tags**: nur `<?php`, nie `<?`. Reine PHP-Dateien ohne schließendes `?>`.
- **Strings**: einfache Anführungszeichen. Doppelte nur für HTML-Attribute innerhalb von Strings.
  In JavaScript doppelte Anführungszeichen.
- **Vergleiche**: immer `===` / `!==`, nie `==` / `!=`.
- **Kommentare**: `//` für ein bis zwei Zeilen, `/* */` ab drei Zeilen. Klassen, Methoden und
  Dateien bekommen einen Doc-Block (Doxygen/PHPDoc-Stil) mit Zweck und Parametern.
- **Dateien**: Kleinbuchstaben mit Unterstrichen (`neue_pruefung.php`), UTF-8 ohne BOM, LF-Zeilenenden.
  Ausnahme: Klassendateien in `classes/` heißen wie die Klasse (`BackupRepository.php`), damit der
  Autoloader in `index.php` sie findet. Auch `dump.php` und `dump-config.php` liegen im Repo-Stamm.
- **Datenbank**: Das Plugin verbindet sich nicht selbst per SQL, sondern ruft `mysql` und
  `mysqldump` über `proc_open` mit Argument-Array (nie über die Shell, nie Werte in
  Kommandozeilen-Strings einbauen) auf. Passwörter gehen über `MYSQL_PWD`, nicht über Argumente.
  Falls doch Abfragen über Admidio nötig werden: nur parametrisierte Abfragen über `$gDb`
  (Platzhalter `?`) und Tabellennamen über `TBL_*`.
- **Eingaben**: alle GET/POST-Parameter über `admFuncVariableIsValid()` prüfen. POST-Formulare mit
  CSRF-Token (`SecurityUtils::validateCsrfToken()`). Ausgaben mit `htmlspecialchars` escapen.
- **Commits**: kurze Beschreibung der Änderung, zugehörige Issue-Nummer angeben (z. B. `#12`),
  falls vorhanden.

## Abweichungen und Ergänzungen für dieses Repo

- **Sprache**: Kommentare, Doc-Blöcke, README und Anzeigetexte sind auf Deutsch (abweichend von
  der Admidio-Richtlinie, die Englisch vorsieht). Bezeichner im Code bleiben Englisch.
- **PHP 8.4+**: `declare`-freie, moderne Syntax ist erwünscht: `readonly`-Properties,
  Constructor Promotion, benannte Argumente, `static fn`, `str_starts_with` usw.
- **Namespace**: alle Klassen liegen unter `AdmBackup\` in `classes/`, eine Klasse pro Datei.
  Wertobjekte (`BackupConfig`, `DatabaseAccess`, `BackupFile`) sind `final readonly`, auch die
  Dienstklassen sind `final`.
- **Sicherheit der Backup-Instanz**: Der normale Import schreibt nie in die produktive Datenbank
  und ruft zuerst `BackupConfig::assertTargetIsNotProduction()` auf. Nur `importToProduction()`
  (Wiederherstellung) schreibt in die Produktion: sie ist hinter einer ungespeicherten Checkbox
  und einer Bestätigung versteckt, der Server verlangt `confirmProduction=1`, und `index.php`
  sichert vorher den aktuellen Stand. Designfarben werden dort nie gesetzt. Dateinamen aus Requests
  werden nie als Pfad verwendet, sondern nur über `BackupRepository::find()` gegen vorhandene Dateien
  aufgelöst (festes Namensmuster `YYYY-MM-DD_HH-MM-SS.sql.gz`). Alle Aktionen sind auf
  Administratoren beschränkt und per POST mit CSRF-Token geschützt.
- **Dump-Skript**: `dump.php` wird per HTTP-GET mit Token aufgerufen und liest `../dump-config.php`.
  Zugangsdaten, Token und Backup-Verzeichnis liegen außerhalb des Web-Roots und nie im Repo. Das
  Skript bleibt eigenständig und nutzt keine Klassen des Plugins.
- **Admidio-API**: Klassen aus dem Admidio-Kern per `use Admidio\...` einbinden, keine Kopien.
  Globale Admidio-Variablen (`$gCurrentUser`, `$GLOBALS` mit den Werten der `config.php`, ...)
  werden nur in `index.php` gelesen; alle anderen Klassen bekommen `BackupConfig` bzw.
  `BackupRepository` übergeben. Einstellungen kommen aus den Variablen der `config.php`
  (`$backup_*`, `$g_adm_*`), siehe `config.example.php`; dort jede neue Einstellung nachtragen.
- **Tests**: PHPUnit in `tests/`, laufen ohne Admidio (`composer install`, dann `composer check`).
  Dateisystemtests arbeiten in einem temporären Verzeichnis und räumen auf. Jede neue Klasse mit
  Logik bekommt einen Test; Klassen, die `mysql`/`mysqldump` starten, werden nicht automatisch
  getestet. Vor einem Commit Tests ausführen.
- **README pflegen**: neue Klassen, Dateien und Einstellungen werden in der README (Tabelle in
  „Installation“ und Liste in „Aufbau“) nachgetragen. Keine Code-Beispiele in die README kopieren.
- **Nicht ins Repo**: `config.php`, `dump-config.php`, `vendor/`, IDE-Ordner (siehe `.gitignore`).


