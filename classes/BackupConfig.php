<?php

namespace AdmBackup;

use RuntimeException;

/**
 * Einstellungen des Plugins (aus der config.php der Admidio-Installation).
 *
 * Enthält die Zugangsdaten der Backup-Datenbank (Ziel des Imports) und der produktiven
 * Datenbank (nur lesend, für die manuelle Sicherung).
 */
final readonly class BackupConfig
{
    /**
     * @param string $backupDir Serverpfad zum Backup-Verzeichnis
     * @param string $mysqlBinary Pfad zum Programm mysql (Import)
     * @param string $mysqldumpBinary Pfad zum Programm mysqldump (manuelle Sicherung)
     * @param DatabaseAccess $target Backup-Datenbank (Ziel des Imports)
     * @param DatabaseAccess $production Produktive Datenbank (nur Lesezugriff)
     * @param string $backupRootPath URL der Backup-Instanz
     */
    public function __construct(
        public string $backupDir,
        public string $mysqlBinary,
        public string $mysqldumpBinary,
        public DatabaseAccess $target,
        public DatabaseAccess $production,
        public string $backupRootPath = ''
    ) {
    }

    /**
     * Liest die Einstellungen aus den globalen Variablen der config.php.
     *
     * @param array<string,mixed> $globals Normalerweise $GLOBALS
     * @throws RuntimeException Wenn Pflichteinstellungen fehlen
     */
    public static function fromGlobals(array $globals): self
    {
        foreach (['backup_dir', 'backup_adm_srv', 'backup_adm_db', 'backup_adm_usr', 'backup_adm_pw',
            'g_adm_srv', 'g_adm_db', 'g_adm_usr', 'g_adm_pw'] as $name) {
            if (!isset($globals[$name]) || !is_scalar($globals[$name]) || (string) $globals[$name] === '') {
                throw new RuntimeException('Einstellung $' . $name . ' fehlt in der config.php.');
            }
        }

        return new self(
            backupDir: rtrim((string) $globals['backup_dir'], '/\\'),
            mysqlBinary: (string) ($globals['backup_mysql'] ?? 'mysql'),
            mysqldumpBinary: (string) ($globals['backup_mysqldump'] ?? 'mysqldump'),
            target: new DatabaseAccess(
                (string) $globals['backup_adm_srv'],
                (int) ($globals['backup_adm_port'] ?? 3306),
                (string) $globals['backup_adm_db'],
                (string) $globals['backup_adm_usr'],
                (string) $globals['backup_adm_pw']
            ),
            production: new DatabaseAccess(
                (string) $globals['g_adm_srv'],
                (int) ($globals['g_adm_port'] ?? 3306),
                (string) $globals['g_adm_db'],
                (string) $globals['g_adm_usr'],
                (string) $globals['g_adm_pw']
            ),
            backupRootPath: (string) ($globals['backup_root_path'] ?? '')
        );
    }

    /**
     * Verhindert, dass in die produktive Datenbank geschrieben wird.
     *
     * @throws RuntimeException Wenn Backup- und produktive Datenbank übereinstimmen
     */
    public function assertTargetIsNotProduction(): void
    {
        // Gleicher Datenbankname wird auch bei abweichendem Host abgelehnt (Alias-Hostnamen)
        if ($this->target->db === $this->production->db) {
            throw new RuntimeException(
                'Die Backup-Datenbank ist identisch mit der produktiven Datenbank. Import abgebrochen.'
            );
        }
    }
}
