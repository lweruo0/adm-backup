<?php

namespace AdmBackup;

use DateTimeImmutable;

/**
 * Findet die Backup-Dateien einer Datenbank im Backup-Verzeichnis und merkt sich den letzten Import.
 */
final class BackupRepository
{
    private const FILE_PATTERN = '/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/';
    private const LAST_IMPORT_FILE = 'last_import.json';

    /**
     * @param string $backupDir Backup-Verzeichnis
     * @param string $database Name der Datenbank (Unterordner)
     */
    public function __construct(
        private readonly string $backupDir,
        private readonly string $database
    ) {
    }

    /**
     * Alle Backups, neueste zuerst.
     *
     * @return list<BackupFile>
     */
    public function findAll(): array
    {
        $directory = $this->databaseDirectory();
        if ($directory === null || !is_dir($directory)) {
            return [];
        }

        $files = [];
        foreach (scandir($directory) ?: [] as $entry) {
            $file = $this->buildFile($directory, $entry);
            if ($file !== null) {
                $files[] = $file;
            }
        }

        usort($files, static fn (BackupFile $a, BackupFile $b): int => $b->createdAt <=> $a->createdAt);

        return $files;
    }

    /**
     * Sucht ein Backup anhand des Dateinamens; nur tatsächlich vorhandene Dateien werden geliefert.
     */
    public function find(string $fileName): ?BackupFile
    {
        $directory = $this->databaseDirectory();
        if ($directory === null) {
            return null;
        }

        return $this->buildFile($directory, $fileName);
    }

    /**
     * Verzeichnis, in dem neue Sicherungen der Datenbank abgelegt werden (wird bei Bedarf angelegt).
     */
    public function ensureDatabaseDirectory(): ?string
    {
        $directory = $this->databaseDirectory();
        if ($directory === null) {
            return null;
        }
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            return null;
        }

        return $directory;
    }

    /**
     * Notiert das zuletzt eingespielte Backup.
     */
    public function writeLastImport(BackupFile $file, DateTimeImmutable $importedAt): void
    {
        $directory = $this->ensureDatabaseDirectory();
        if ($directory === null) {
            return;
        }

        file_put_contents(
            $directory . '/' . self::LAST_IMPORT_FILE,
            json_encode(['file' => $file->fileName, 'importedAt' => $importedAt->format('Y-m-d H:i:s')])
        );
    }

    /**
     * @return array{file: string, importedAt: string}|null
     */
    public function readLastImport(): ?array
    {
        $directory = $this->databaseDirectory();
        $path = $directory === null ? '' : $directory . '/' . self::LAST_IMPORT_FILE;
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !isset($data['file'], $data['importedAt'])) {
            return null;
        }

        return ['file' => (string) $data['file'], 'importedAt' => (string) $data['importedAt']];
    }

    private function databaseDirectory(): ?string
    {
        // Nur einfache Namen zulassen, damit kein Pfad aus dem Backup-Verzeichnis herausführt
        if (preg_match('/^[A-Za-z0-9_]+$/', $this->database) !== 1) {
            return null;
        }

        return $this->backupDir . '/' . $this->database;
    }

    private function buildFile(string $directory, string $fileName): ?BackupFile
    {
        if (preg_match(self::FILE_PATTERN, $fileName) !== 1) {
            return null;
        }

        $path = $directory . '/' . $fileName;
        if (!is_file($path)) {
            return null;
        }

        $createdAt = DateTimeImmutable::createFromFormat('Y-m-d_H-i-s', substr($fileName, 0, 19));
        if ($createdAt === false) {
            return null;
        }

        return new BackupFile($fileName, $path, (int) filesize($path), $createdAt);
    }
}
