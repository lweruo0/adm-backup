<?php
/**
 ***********************************************************************************************
 * Backup-Viewer: Backups der produktiven Datenbank auflisten, manuell sichern und in die
 * Datenbank der Backup-Instanz einspielen.
 *
 * Zugriff nur für Administratoren. Alle Aktionen (POST) sind durch ein CSRF-Token geschützt.
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmBackup\BackupConfig;
use AdmBackup\BackupCreator;
use AdmBackup\BackupImporter;
use AdmBackup\BackupRepository;

require_once(__DIR__ . '/../../system/common.php');
require(__DIR__ . '/../../system/login_valid.php');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'AdmBackup\\')) {
        $file = __DIR__ . '/classes/' . substr($class, strlen('AdmBackup\\')) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

try {
    if (!$gCurrentUser->isAdministrator()) {
        throw new Exception('SYS_NO_RIGHTS');
    }

    $config = BackupConfig::fromGlobals($GLOBALS);
    $repository = new BackupRepository($config->backupDir, $config->production->db);

    $messages = [];
    $errors = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        SecurityUtils::validateCsrfToken($_POST['adm_csrf_token'] ?? '');
        $postMode = admFuncVariableIsValid($_POST, 'mode', 'string', ['validValues' => ['import', 'create']]);

        set_time_limit(0);
        ignore_user_abort(true);

        try {
            if ($postMode === 'create') {
                $file = (new BackupCreator($config, $repository))->create(new DateTimeImmutable());
                $messages[] = 'Sicherung ' . $file->fileName . ' wurde erstellt.';
            } else {
                $postFile = admFuncVariableIsValid($_POST, 'file', 'string', ['requireValue' => true]);
                $file = $repository->find($postFile);
                if ($file === null) {
                    throw new RuntimeException('Die gewählte Backup-Datei existiert nicht.');
                }
                (new BackupImporter($config, $repository))->import($file);
                $messages[] = 'Backup ' . $file->fileName . ' wurde in die Backup-Datenbank eingespielt.';
            }
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    $csrfToken = $gCurrentSession->getCsrfToken();
    $lastImport = $repository->readLastImport();
    $backupFiles = $repository->findAll();
    $escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    $page = PagePresenter::withHtmlIDAndHeadline('plg-adm-backup', 'Backups einspielen');

    $html = '';
    foreach ($messages as $message) {
        $html .= '<div class="alert alert-success">' . $escape($message) . '</div>';
    }
    foreach ($errors as $error) {
        $html .= '<div class="alert alert-danger">' . $escape($error) . '</div>';
    }

    $html .= '<p>Produktive Datenbank: <strong>' . $escape($config->production->db) . '</strong>, '
        . 'Ziel des Imports: <strong>' . $escape($config->target->db) . '</strong></p>';

    if ($config->backupRootPath !== '') {
        $html .= '<p><a href="' . $escape($config->backupRootPath) . '" class="btn btn-primary btn-lg" '
            . 'target="_blank" rel="noopener">Zur Backup-Instanz öffnen</a></p>';
    }

    if ($lastImport !== null) {
        $html .= '<p>Zuletzt eingespielt: <strong>' . $escape($lastImport['file']) . '</strong> am '
            . $escape($lastImport['importedAt']) . '</p>';
    }

    $html .= '<form method="post" class="mb-4">'
        . '<input type="hidden" name="adm_csrf_token" value="' . $escape($csrfToken) . '">'
        . '<input type="hidden" name="mode" value="create">'
        . '<button type="submit" class="btn btn-primary">Jetzt Sicherung der produktiven Datenbank erstellen</button>'
        . '</form>';

    if ($backupFiles === []) {
        $html .= '<p>Keine Backups gefunden.</p>';
    } else {
        $html .= '<table class="table table-condensed"><thead><tr><th>Erstellt</th><th>Datei</th>'
            . '<th>Größe</th><th></th></tr></thead><tbody>';
        foreach ($backupFiles as $backupFile) {
            $html .= '<tr><td>' . $escape($backupFile->createdAt->format('d.m.Y H:i:s')) . '</td>'
                . '<td>' . $escape($backupFile->fileName) . '</td>'
                . '<td>' . $escape(number_format($backupFile->size / 1048576, 2, ',', '.')) . ' MB</td>'
                . '<td><form method="post" onsubmit="return confirm(\'Die Backup-Datenbank wird komplett ersetzt. Fortfahren?\');">'
                . '<input type="hidden" name="adm_csrf_token" value="' . $escape($csrfToken) . '">'
                . '<input type="hidden" name="mode" value="import">'
                . '<input type="hidden" name="file" value="' . $escape($backupFile->fileName) . '">'
                . '<button type="submit" class="btn btn-secondary btn-sm">Einspielen</button>'
                . '</form></td></tr>';
        }
        $html .= '</tbody></table>';
    }

    $page->addHtml($html);
    $page->show();
} catch (Throwable $exception) {
    $gMessage->show($exception->getMessage());
}
