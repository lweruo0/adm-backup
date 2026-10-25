<?php

namespace AdmBackup;

use DateTimeImmutable;

/**
 * Eine vorhandene Backup-Datei (<DB_NAME>/<Zeitstempel>.sql.gz).
 */
final readonly class BackupFile
{
    /**
     * @param string $fileName Dateiname, z. B. 2026-10-25_03-00-00.sql.gz
     * @param string $path Vollständiger Pfad
     * @param int $size Größe in Bytes
     * @param DateTimeImmutable $createdAt Zeitpunkt aus dem Dateinamen
     */
    public function __construct(
        public string $fileName,
        public string $path,
        public int $size,
        public DateTimeImmutable $createdAt
    ) {
    }
}
