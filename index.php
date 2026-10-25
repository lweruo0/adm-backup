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
        $postMode = admFuncVariableIsValid($_POST, 'mode', 'string', ['validValues' => ['import', 'importProduction', 'create']]);

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

                if ($postMode === 'importProduction') {
                    // Wird von der Abfrage im Browser gesetzt, nicht von einer bloßen Formularübermittlung
                    if (($_POST['confirmProduction'] ?? '') !== '1') {
                        throw new RuntimeException('Die Wiederherstellung der Produktion wurde nicht bestätigt.');
                    }

                    // Vorher den aktuellen Stand sichern, damit die Wiederherstellung umkehrbar bleibt
                    $safetyBackup = (new BackupCreator($config, $repository))->create(new DateTimeImmutable());
                    (new BackupImporter($config, $repository))->importToProduction($file);
                    $messages[] = 'Backup ' . $file->fileName . ' wurde in die PRODUKTIVE Datenbank eingespielt. '
                        . 'Vorheriger Stand gesichert als ' . $safetyBackup->fileName . '.';
                } else {
                    (new BackupImporter($config, $repository))->import($file);
                    $messages[] = 'Backup ' . $file->fileName . ' wurde in die Backup-Datenbank eingespielt.';
                }
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

    $html = '<p>Hier können Datenbank Backups zusätzlich zu den automatisch erstellten Backups erstellt werden. '
        . 'Über den Link „Einspielen“ kann die Datenbank auf eine zweite Instanz eingespielt und angesehen werden. '
        . 'Über den Link „Einspielen produktiv“ wird die Sicherung auf der produktiven Instanz eingespielt - '
        . 'dabei gehen alle lokalen Änderungen verloren. Ein manuelles Backup wird daher empfohlen.</p>';

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
            . 'target="_blank" rel="noopener">Backup-Instanz öffnen</a></p>';
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

    // Wird bewusst nicht gespeichert: nach jedem Laden der Seite ist die Wiederherstellung wieder gesperrt
    $html .= '<div class="form-check mb-3">'
        . '<input type="checkbox" class="form-check-input" id="adm-backup-production-toggle" autocomplete="off">'
        . '<label class="form-check-label" for="adm-backup-production-toggle">'
        . 'Wiederherstellung der produktiven Datenbank aktivieren</label></div>';

    $productionConfirm = 'ACHTUNG: Die PRODUKTIVE Datenbank ' . $config->production->db
        . ' wird komplett durch dieses Backup ersetzt. Alle neueren Daten gehen verloren '
        . '(vorher wird eine Sicherung des aktuellen Stands erstellt). Wirklich fortfahren?';

    if ($backupFiles === []) {
        $html .= '<p>Keine Backups gefunden.</p>';
    } else {
        $html .= '<table class="table table-condensed"><thead><tr><th>Erstellt</th><th>Datei</th>'
            . '<th>Größe</th><th></th></tr></thead><tbody>';
        foreach ($backupFiles as $backupFile) {
            $html .= '<tr><td>' . $escape($backupFile->createdAt->format('d.m.Y H:i:s')) . '</td>'
                . '<td>' . $escape($backupFile->fileName) . '</td>'
                . '<td>' . $escape(number_format($backupFile->size / 1048576, 2, ',', '.')) . ' MB</td>'
                . '<td class="text-nowrap"><form method="post" class="d-inline" onsubmit="return confirm(\'Die Backup-Datenbank wird komplett ersetzt. Fortfahren?\');">'
                . '<input type="hidden" name="adm_csrf_token" value="' . $escape($csrfToken) . '">'
                . '<input type="hidden" name="mode" value="import">'
                . '<input type="hidden" name="file" value="' . $escape($backupFile->fileName) . '">'
                . '<button type="submit" class="btn btn-secondary btn-sm">Einspielen</button>'
                . '</form> '
                . '<form method="post" class="d-inline adm-backup-production d-none" '
                . 'onsubmit="if (!confirm(this.dataset.confirm)) { return false; } this.confirmProduction.value = \'1\';" '
                . 'data-confirm="' . $escape($productionConfirm) . '">'
                . '<input type="hidden" name="adm_csrf_token" value="' . $escape($csrfToken) . '">'
                . '<input type="hidden" name="mode" value="importProduction">'
                . '<input type="hidden" name="file" value="' . $escape($backupFile->fileName) . '">'
                . '<input type="hidden" name="confirmProduction" value="0">'
                . '<button type="submit" class="btn btn-danger btn-sm">Einspielen produktiv</button>'
                . '</form></td></tr>';
        }
        $html .= '</tbody></table>';
    }

    $html .= '<script>'
        . 'document.getElementById("adm-backup-production-toggle").addEventListener("change", function () {'
        . 'document.querySelectorAll(".adm-backup-production").forEach(function (form) {'
        . 'form.classList.toggle("d-none", !this.checked);'
        . '}, this);'
        . '});'
        . 'document.getElementById("adm-backup-production-toggle").checked = false;'
        . '</script>';

    $page->addHtml($html);
    $page->show();
} catch (Throwable $exception) {
    $gMessage->show($exception->getMessage());
}
