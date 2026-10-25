<?php

namespace AdmBackup\Tests;

use AdmBackup\BackupConfig;
use AdmBackup\DatabaseAccess;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BackupConfigTest extends TestCase
{
    private function globals(string $targetDb, string $productionDb): array
    {
        return [
            'backup_dir' => '/tmp/backups/',
            'backup_adm_srv' => 'host-a:3307',
            'backup_adm_db' => $targetDb,
            'backup_adm_usr' => 'u1',
            'backup_adm_pw' => 'p1',
            'g_adm_srv' => 'host-b',
            'g_adm_db' => $productionDb,
            'g_adm_usr' => 'u2',
            'g_adm_pw' => 'p2',
        ];
    }

    public function testReadsGlobalsAndSplitsHostPort(): void
    {
        $config = BackupConfig::fromGlobals($this->globals('backupdb', 'proddb'));

        self::assertSame('/tmp/backups', $config->backupDir);
        self::assertSame('host-a', $config->target->host);
        self::assertSame(3307, $config->target->port);
        self::assertSame(3306, $config->production->port);
        self::assertSame('mysql', $config->mysqlBinary);
    }

    public function testMissingSettingThrows(): void
    {
        $globals = $this->globals('backupdb', 'proddb');
        unset($globals['backup_dir']);

        $this->expectException(RuntimeException::class);
        BackupConfig::fromGlobals($globals);
    }

    public function testTargetEqualToProductionIsRejected(): void
    {
        $config = BackupConfig::fromGlobals($this->globals('proddb', 'proddb'));

        $this->expectException(RuntimeException::class);
        $config->assertTargetIsNotProduction();
    }

    public function testDifferentTargetIsAccepted(): void
    {
        BackupConfig::fromGlobals($this->globals('backupdb', 'proddb'))->assertTargetIsNotProduction();

        self::assertInstanceOf(DatabaseAccess::class, new DatabaseAccess('h', 1, 'd', 'u', 'p'));
    }
}
