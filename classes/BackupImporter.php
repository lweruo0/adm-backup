<?php

namespace AdmBackup;

use DateTimeImmutable;
use RuntimeException;

/**
 * Spielt ein Backup (.sql.gz) in die Backup-Datenbank ein und setzt danach die Designfarben der
 * Backup-Instanz, damit sofort erkennbar ist, dass man nicht auf der Produktion arbeitet.
 *
 * Die Dumps enthalten DROP TABLE IF EXISTS, bestehende Tabellen werden dadurch ersetzt.
 */
final class BackupImporter
{
    public function __construct(
        private readonly BackupConfig $config,
        private readonly BackupRepository $repository
    ) {
    }

    /**
     * @throws RuntimeException Bei Fehlern oder wenn das Ziel die produktive Datenbank wäre
     */
    public function import(BackupFile $file): void
    {
        $this->config->assertTargetIsNotProduction();

        $this->runMysql($this->config->target, $this->backupFeeder($file), 'Import fehlgeschlagen');

        $this->applyThemeColors();
        $this->repository->writeLastImport($file, new DateTimeImmutable());
    }

    /**
     * Spielt ein Backup in die PRODUKTIVE Datenbank ein und ersetzt deren Inhalt. Die Designfarben
     * der Backup-Instanz werden hier bewusst nicht gesetzt, der letzte Import wird nicht vermerkt.
     *
     * @throws RuntimeException Bei Fehlern
     */
    public function importToProduction(BackupFile $file): void
    {
        $this->runMysql($this->config->production, $this->backupFeeder($file), 'Import in die Produktion fehlgeschlagen');
    }

    /**
     * @return callable(resource): void Schreibt den entpackten Inhalt der Backup-Datei in den Eingabestrom
     */
    private function backupFeeder(BackupFile $file): callable
    {
        return static function ($input) use ($file): void {
            $source = gzopen($file->path, 'rb');
            if ($source === false) {
                throw new RuntimeException('Die Backup-Datei kann nicht gelesen werden.');
            }

            while (!gzeof($source)) {
                $chunk = gzread($source, 65536);
                if ($chunk === false || $chunk === '' || @fwrite($input, $chunk) === false) {
                    break;
                }
            }
            gzclose($source);
        };
    }

    /**
     * Setzt Primär- und Sekundärfarbe in der Backup-Datenbank. Admidio 5.0 speichert sie als
     * color_primary / color_secondary, ab 5.1 als theme_color_primary / theme_color_secondary;
     * nicht vorhandene Einträge werden von UPDATE einfach nicht berührt.
     *
     * @throws RuntimeException Bei Fehlern oder wenn das Ziel die produktive Datenbank wäre
     */
    public function applyThemeColors(): void
    {
        $this->config->assertTargetIsNotProduction();

        $sql = $this->buildThemeColorSql();
        $this->runMysql($this->config->target, static function ($input) use ($sql): void {
            fwrite($input, $sql);
        }, 'Designfarben konnten nicht gesetzt werden');
    }

    /**
     * SQL zum Setzen der Designfarben; Tabellenpräfix und Farben sind in BackupConfig validiert.
     */
    public function buildThemeColorSql(): string
    {
        $table = $this->config->tablePrefix . '_preferences';
        $sql = '';
        foreach ([
            'theme_color_primary' => $this->config->colorPrimary,
            'color_primary' => $this->config->colorPrimary,
            'theme_color_secondary' => $this->config->colorSecondary,
            'color_secondary' => $this->config->colorSecondary,
        ] as $name => $color) {
            $sql .= 'UPDATE `' . $table . '` SET prf_value = \'' . $color . '\' WHERE prf_name = \'' . $name . "';\n";
        }

        return $sql;
    }

    /**
     * Startet mysql für die angegebene Datenbank und füttert es über $feed mit SQL.
     *
     * @param DatabaseAccess $database Zieldatenbank
     * @param callable(resource): void $feed Schreibt SQL in den übergebenen Eingabestrom
     * @param string $errorPrefix Beginn der Fehlermeldung
     * @throws RuntimeException Bei Fehlern
     */
    private function runMysql(DatabaseAccess $database, callable $feed, string $errorPrefix): void
    {
        $target = $database;
        $command = [$this->config->mysqlBinary, ...$target->clientArguments(), $target->db];

        $errorFile = (string) tempnam(sys_get_temp_dir(), 'adm_backup_err_');
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['file', $errorFile, 'w'], 2 => ['file', $errorFile, 'a']],
            $pipes,
            null,
            $target->processEnvironment()
        );

        if (!is_resource($process)) {
            unlink($errorFile);
            throw new RuntimeException('Das Programm mysql konnte nicht gestartet werden.');
        }

        $feedError = null;
        try {
            $feed($pipes[0]);
        } catch (RuntimeException $exception) {
            $feedError = $exception;
        }
        fclose($pipes[0]);

        $exitCode = proc_close($process);
        $errorText = trim((string) file_get_contents($errorFile));
        unlink($errorFile);

        if ($feedError !== null) {
            throw $feedError;
        }
        if ($exitCode !== 0) {
            throw new RuntimeException($errorPrefix . ': ' . $errorText);
        }
    }
}
