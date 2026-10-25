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
        foreach (['2026-10-24_03-00-00.sql.gz', '2026-10-25_03-00-00.sql.gz', 'notes.txt', '../evil.sql.gz'] as $name) {
            @file_put_contents($this->dir . '/dbprod/' . basename($name), 'x');
        }
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
            ['2026-10-25_03-00-00.sql.gz', '2026-10-24_03-00-00.sql.gz'],
            array_map(static fn ($file) => $file->fileName, $files)
        );
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
