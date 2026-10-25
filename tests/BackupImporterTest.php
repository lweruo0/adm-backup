<?php

namespace AdmBackup\Tests;

use AdmBackup\BackupConfig;
use AdmBackup\BackupImporter;
use AdmBackup\BackupRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BackupImporterTest extends TestCase
{
    private function globals(array $extra = []): array
    {
        return $extra + [
            'backup_dir' => '/tmp/backups',
            'backup_adm_srv' => 'h1',
            'backup_adm_db' => 'backupdb',
            'backup_adm_usr' => 'u1',
            'backup_adm_pw' => 'p1',
            'g_adm_srv' => 'h2',
            'g_adm_db' => 'proddb',
            'g_adm_usr' => 'u2',
            'g_adm_pw' => 'p2',
        ];
    }

    public function testThemeColorSqlUsesDefaultColorsForBothSettingNames(): void
    {
        $config = BackupConfig::fromGlobals($this->globals(['g_tbl_praefix' => 'adm']));
        $sql = (new BackupImporter($config, new BackupRepository('/tmp', 'proddb')))->buildThemeColorSql();

        self::assertStringContainsString(
            "UPDATE `adm_preferences` SET prf_value = '#ff0000' WHERE prf_name = 'theme_color_primary';",
            $sql
        );
        self::assertStringContainsString("SET prf_value = '#ff0000' WHERE prf_name = 'color_primary';", $sql);
        self::assertStringContainsString("SET prf_value = '#808080' WHERE prf_name = 'theme_color_secondary';", $sql);
        self::assertStringContainsString("SET prf_value = '#808080' WHERE prf_name = 'color_secondary';", $sql);
    }

    public function testInvalidColorIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        BackupConfig::fromGlobals($this->globals(['backup_color_primary' => "red'; DROP TABLE x;--"]));
    }

    public function testInvalidTablePrefixIsRejected(): void
    {
        $this->expectException(RuntimeException::class);
        BackupConfig::fromGlobals($this->globals(['g_tbl_praefix' => 'adm`; --']));
    }
}
