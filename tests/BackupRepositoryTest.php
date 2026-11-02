<?php

namespace AdmBackup\Tests;

use AdmBackup\BackupRepository;
use PHPUnit\Framework\TestCase;

final class BackupRepositoryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/adm_backup_test_' . uniqid();
        mkdir($this->dir . '/dbprod', 0777, true);
        foreach (['2026-10-24_03-00-00.sql.gz', '2026-10-25_03-00-00.sql.gz', 'notes.txt', '../evil.sql.gz', 'last-17-00-00.sql.gz', 'last-17-00-00.sql.gz.part', 'last-1-00-00.sql.gz'] as $name) {
            @file_put_contents($this->dir . '/dbprod/' . basename($name), 'x');
        }
        // stuendliche Sicherung: Zeitpunkt aus dem Aenderungsdatum (zwischen den beiden Tagessicherungen)
        touch($this->dir . '/dbprod/last-17-00-00.sql.gz', strtotime('2026-10-24 17:00:05'));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/dbprod/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir . '/dbprod');
        rmdir($this->dir);
    }

    public function testListsOnlyBackupFilesNewestFirst(): void
    {
        $files = (new BackupRepository($this->dir, 'dbprod'))->findAll();

        self::assertSame(
            ['2026-10-25_03-00-00.sql.gz', 'last-17-00-00.sql.gz', '2026-10-24_03-00-00.sql.gz'],
            array_map(static fn ($file) => $file->fileName, $files)
        );
    }

    public function testHourlyBackupUsesModificationTime(): void
    {
        $file = (new BackupRepository($this->dir, 'dbprod'))->find('last-17-00-00.sql.gz');

        self::assertNotNull($file);
        self::assertSame('2026-10-24 17:00:05', $file->createdAt->format('Y-m-d H:i:s'));
        self::assertNull((new BackupRepository($this->dir, 'dbprod'))->find('last-17-00-00.sql.gz.part'));
    }

    public function testFindRejectsPathsAndUnknownFiles(): void
    {
        $repository = new BackupRepository($this->dir, 'dbprod');

        self::assertNotNull($repository->find('2026-10-25_03-00-00.sql.gz'));
        self::assertNull($repository->find('../dbprod/2026-10-25_03-00-00.sql.gz'));
        self::assertNull($repository->find('2020-01-01_00-00-00.sql.gz'));
    }

    public function testInvalidDatabaseNameYieldsNothing(): void
    {
        $repository = new BackupRepository($this->dir, '../dbprod');

        self::assertSame([], $repository->findAll());
        self::assertNull($repository->find('2026-10-25_03-00-00.sql.gz'));
    }
}
