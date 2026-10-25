<?php

namespace AdmBackup;

use DateTimeImmutable;
use RuntimeException;

/**
 * Spielt ein Backup (.sql.gz) in die Backup-Datenbank ein.
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

        $target = $this->config->target;
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

        $source = gzopen($file->path, 'rb');
        if ($source === false) {
            fclose($pipes[0]);
            proc_close($process);
            unlink($errorFile);
            throw new RuntimeException('Die Backup-Datei kann nicht gelesen werden.');
        }

        while (!gzeof($source)) {
            $chunk = gzread($source, 65536);
            if ($chunk === false || $chunk === '') {
                break;
            }
            if (@fwrite($pipes[0], $chunk) === false) {
                break;
            }
        }
        gzclose($source);
        fclose($pipes[0]);

        $exitCode = proc_close($process);
        $errorText = trim((string) file_get_contents($errorFile));
        unlink($errorFile);

        if ($exitCode !== 0) {
            throw new RuntimeException('Import fehlgeschlagen: ' . $errorText);
        }

        $this->repository->writeLastImport($file, new DateTimeImmutable());
    }
}
