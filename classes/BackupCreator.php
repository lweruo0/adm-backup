<?php

namespace AdmBackup;

use DateTimeImmutable;
use RuntimeException;

/**
 * Erzeugt eine manuelle Sicherung der produktiven Datenbank (nur lesend, mit mysqldump).
 */
final class BackupCreator
{
    public function __construct(
        private readonly BackupConfig $config,
        private readonly BackupRepository $repository
    ) {
    }

    /**
     * @param DateTimeImmutable $now Zeitpunkt, wird für den Dateinamen verwendet
     * @throws RuntimeException Bei Fehlern
     */
    public function create(DateTimeImmutable $now): BackupFile
    {
        $directory = $this->repository->ensureDatabaseDirectory();
        if ($directory === null) {
            throw new RuntimeException('Das Backup-Verzeichnis kann nicht angelegt werden.');
        }

        $production = $this->config->production;
        $fileName = $now->format('Y-m-d_H-i-s') . '.sql.gz';
        $path = $directory . '/' . $fileName;

        // --single-transaction ist gut für InnoDB
        $command = [
            $this->config->mysqldumpBinary,
            ...$production->clientArguments(),
            '--single-transaction',
            '--quick',
            $production->db,
        ];

        $errorFile = (string) tempnam(sys_get_temp_dir(), 'adm_backup_err_');
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['file', $errorFile, 'w']],
            $pipes,
            null,
            $production->processEnvironment()
        );

        if (!is_resource($process)) {
            unlink($errorFile);
            throw new RuntimeException('Das Programm mysqldump konnte nicht gestartet werden.');
        }

        $target = gzopen($path, 'wb9');
        if ($target !== false) {
            while (!feof($pipes[1])) {
                gzwrite($target, (string) fread($pipes[1], 65536));
            }
            gzclose($target);
        }
        fclose($pipes[1]);

        $exitCode = proc_close($process);
        $errorText = trim((string) file_get_contents($errorFile));
        unlink($errorFile);

        if ($target === false || $exitCode !== 0) {
            if (is_file($path)) {
                unlink($path);
            }
            throw new RuntimeException('Sicherung fehlgeschlagen: ' . $errorText);
        }

        $file = $this->repository->find($fileName);
        if ($file === null) {
            throw new RuntimeException('Die Sicherung wurde nicht angelegt.');
        }

        return $file;
    }
}
