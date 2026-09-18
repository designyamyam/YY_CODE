<?php
// YamYam Berlin — Speisekarten-Upload für das Personal.
//
// Ersetzt uploads/menue.pdf. menue.html lädt diese Datei bevorzugt und fällt
// sonst auf das im Repo eingecheckte menue.pdf zurück (siehe pdf-viewer.js).
// uploads/ ist vom Deploy ausgenommen, damit ein Deploy Uploads nicht überschreibt.
//
// Passwort: der Hash kommt aus admin/config.php, das der Deploy-Workflow aus dem
// GitHub-Secret MENU_UPLOAD_PASSWORD schreibt. Fehlt config.php, ist der Upload aus.

declare(strict_types=1);
date_default_timezone_set('Europe/Berlin');

const MAX_BYTES     = 20 * 1024 * 1024;
const KEEP_VERSIONS = 10;

$root       = dirname(__DIR__);
$uploadsDir = $root . '/uploads';
$archiveDir = $uploadsDir . '/archive';
$target     = $uploadsDir . '/menue.pdf';
$fallback   = $root . '/menue.pdf';

$configured = false;
if (is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
    $configured = defined('MENU_UPLOAD_PASSWORD_HASH') && MENU_UPLOAD_PASSWORD_HASH !== '';
}

function pruneArchive(string $dir): void
{
    $files = glob($dir . '/menue-*.pdf') ?: [];
    rsort($files); // Zeitstempel im Namen → neueste zuerst
    foreach (array_slice($files, KEEP_VERSIONS) as $old) {
        @unlink($old);
    }
}

function checkPassword(bool $configured): ?array
{
    if (!$configured) {
        return ['error', 'Der Upload ist noch nicht eingerichtet (Passwort fehlt auf dem Server).'];
    }
    $password = (string)($_POST['password'] ?? '');
    if ($password === '' || !password_verify($password, MENU_UPLOAD_PASSWORD_HASH)) {
        sleep(2); // bremst Durchprobieren
        return ['error', 'Falsches Passwort.'];
    }
    return null;
}

// Frühere Version wiederherstellen oder löschen. $action = "restore:<datei>" | "delete:<datei>"
function handleArchiveAction(string $action, bool $configured, string $uploadsDir, string $archiveDir, string $target): array
{
    if ($err = checkPassword($configured)) {
        return $err;
    }
    if (!preg_match('/^(restore|delete):(menue-\d{8}-\d{6}\.pdf)$/', $action, $m)) {
        return ['error', 'Ungültige Aktion.'];
    }
    [, $what, $name] = $m;
    $path = $archiveDir . '/' . $name;           // Name ist durch das Muster auf den Archivordner beschränkt
    if (!is_file($path)) {
        return ['error', 'Diese Version gibt es nicht mehr.'];
    }
    $when = DateTime::createFromFormat('Ymd-His', substr($name, 6, 15));
    $label = $when ? $when->format('d.m.Y, H:i') : $name;

    if ($what === 'delete') {
        return @unlink($path)
            ? ['ok', 'Version vom ' . $label . ' gelöscht.']
            : ['error', 'Die Version konnte nicht gelöscht werden.'];
    }

    // restore: aktuelle Karte ins Archiv, Archivkopie atomar nach uploads/menue.pdf
    if (is_file($target)) {
        @copy($target, $archiveDir . '/menue-' . date('Ymd-His', (int)filemtime($target)) . '.pdf');
    }
    $tmp = $uploadsDir . '/menue.' . bin2hex(random_bytes(4)) . '.tmp';
    if (!copy($path, $tmp) || !rename($tmp, $target)) {
        @unlink($tmp);
        return ['error', 'Die Version konnte nicht wiederhergestellt werden.'];
    }
    @chmod($target, 0644);
    touch($target); // neuer Zeitstempel → Cache-Buster im Viewer greift
    pruneArchive($archiveDir);
    return ['ok', 'Version vom ' . $label . ' ist wieder online.'];
}

function handleUpload(bool $configured, string $uploadsDir, string $archiveDir, string $target): array
{
    if ($err = checkPassword($configured)) {
        return $err;
    }

    $file = $_FILES['pdf'] ?? null;
    if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
        return ['error', 'Keine Datei ausgewählt.'];
    }
    switch ((int)$file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['error', 'Keine Datei ausgewählt.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['error', 'Die Datei ist zu groß (maximal 20 MB).'];
        default:
            return ['error', 'Upload fehlgeschlagen (Fehler ' . (int)$file['error'] . '). Bitte noch einmal versuchen.'];
    }
    if ((int)$file['size'] > MAX_BYTES) {
        return ['error', 'Die Datei ist zu groß (maximal 20 MB).'];
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return ['error', 'Ungültiger Upload.'];
    }
    $head = file_get_contents($file['tmp_name'], false, null, 0, 5);
    if ($head !== '%PDF-') {
        return ['error', 'Die Datei ist kein PDF.'];
    }

    if (!is_dir($archiveDir) && !mkdir($archiveDir, 0755, true)) {
        return ['error', 'Der Upload-Ordner konnte nicht angelegt werden.'];
    }

    // Bisherige Version aufheben, damit ein Fehl-Upload rückgängig gemacht werden kann.
    if (is_file($target)) {
        @copy($target, $archiveDir . '/menue-' . date('Ymd-His', (int)filemtime($target)) . '.pdf');
        pruneArchive($archiveDir);
    }

    // Erst daneben ablegen, dann atomar drüberschieben — kein halb geschriebenes PDF für Besucher.
    $tmp = $uploadsDir . '/menue.' . bin2hex(random_bytes(4)) . '.tmp';
    if (!move_uploaded_file($file['tmp_name'], $tmp)) {
        return ['error', 'Die Datei konnte nicht gespeichert werden.'];
    }
    if (!rename($tmp, $target)) {
        @unlink($tmp);
        return ['error', 'Die Datei konnte nicht gespeichert werden.'];
    }
    @chmod($target, 0644);

    return ['ok', 'Die neue Speisekarte ist online.'];
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = (string)($_POST['action'] ?? 'upload');
    $message = $action === 'upload'
        ? handleUpload($configured, $uploadsDir, $archiveDir, $target)
        : handleArchiveAction($action, $configured, $uploadsDir, $archiveDir, $target);
}

$current = is_file($target) ? $target : (is_file($fallback) ? $fallback : null);
$currentStamp = $current ? date('d.m.Y, H:i', (int)filemtime($current)) . ' Uhr' : '—';
$currentNote  = is_file($target) ? 'hochgeladen über diese Seite' : 'Standardversion aus dem Repo';
$archive      = is_dir($archiveDir) ? (glob($archiveDir . '/menue-*.pdf') ?: []) : [];
rsort($archive);

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#e7e4df">
  <title>Speisekarte aktualisieren — YamYam Berlin</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="../global.css">
  <style>
    .admin {
      max-width: 560px;
      margin: 0 auto;
      padding: 48px var(--gap) 80px;
      font-family: var(--font-body);
      font-size: 16px;
      color: var(--black);
    }
    .admin__logo { display: block; width: 72px; margin-bottom: 32px; }
    .admin__logo img { display: block; width: 100%; }
    .admin__logo:hover img { filter: brightness(0); }   /* wie das Nav-Logo: hover = schwarz */
    .admin h1 {
      font-family: var(--font-display);
      font-weight: 500;
      font-size: clamp(28px, 4vw, 40px);
      letter-spacing: var(--tracking-display);
      text-transform: uppercase;
      color: var(--red);
      margin-bottom: 8px;
    }
    .admin__status { color: var(--gray-text); margin-bottom: 32px; line-height: 1.5; }
    .admin__status a { color: var(--red); }
    .admin__status a:hover { color: var(--black); }
    .admin label { display: block; font-weight: 700; margin-bottom: 6px; }
    .admin input[type="password"],
    .admin input[type="file"] {
      display: block;
      width: 100%;
      font: inherit;
      padding: 10px 12px;
      margin-bottom: 20px;
      border: 1.5px solid var(--gray-light);
      border-radius: 8px;
      background: var(--white);
      color: var(--black);
    }
    .admin input:focus { outline: 2px solid var(--red); outline-offset: 1px; }
    .admin__actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    .admin__msg {
      padding: 12px 16px;
      margin-bottom: 24px;
      border-radius: 8px;
      border: 1.5px solid var(--red);
      color: var(--red);
      font-weight: 700;
    }
    .admin__msg--ok { border-color: var(--black); color: var(--black); }
    .admin__help { margin-top: 40px; color: var(--gray-text); font-size: 14px; line-height: 1.6; }
    .admin__help ul { padding-left: 18px; }
    .admin__help li { margin-bottom: 4px; }
    .admin__archive { margin-top: 40px; font-size: 14px; color: var(--gray-text); }
    .admin__archive h2 {
      font-family: var(--font-display);
      font-weight: 500;
      font-size: 20px;
      letter-spacing: var(--tracking-display);
      text-transform: uppercase;
      color: var(--black);
      margin-bottom: 4px;
    }
    .admin__archive-hint { margin-bottom: 12px; }
    .admin__archive ul { padding-left: 20px; }            /* echte Bullet-Liste, neueste zuerst */
    .admin__archive li { padding: 6px 0; line-height: 1.5; }
    .admin__archive-date { color: var(--black); font-weight: 700; margin-right: 12px; }
    .admin__archive a,
    .admin__archive button { font: inherit; color: var(--red); text-decoration: underline; padding: 0; margin-right: 12px; }
    .admin__archive a:hover,
    .admin__archive button:hover { color: var(--black); }
  </style>
</head>
<body>
  <main class="admin" role="main">
    <a href="../index.html" class="admin__logo" aria-label="Zur Startseite"><img src="../images/logos/YY_Logo_Red.svg" alt="YamYam Berlin"></a>
    <h1>Speisekarte aktualisieren</h1>
    <p class="admin__status">
      Aktuelle Speisekarte: Stand <?= htmlspecialchars($currentStamp, ENT_QUOTES, 'UTF-8') ?>
      (<?= $currentNote ?>) · <a href="../menue.html" target="_blank" rel="noopener">ansehen</a>
    </p>

    <?php if ($message !== null): ?>
      <p class="admin__msg<?= $message[0] === 'ok' ? ' admin__msg--ok' : '' ?>" role="alert">
        <?= htmlspecialchars($message[1], ENT_QUOTES, 'UTF-8') ?>
      </p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_BYTES ?>">
      <label for="password">Passwort</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
      <label for="pdf">Neue Speisekarte (PDF, max. 20 MB)</label>
      <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf" required>
      <div class="admin__actions">
        <button type="submit" name="action" value="upload" class="btn-outline">Hochladen</button>
        <a href="../menue.html" target="_blank" rel="noopener" class="btn-outline">Speisekarte ansehen <img class="btn-arrow" src="../images/icons/arrow.svg" alt=""></a>
      </div>

      <?php if ($archive): ?>
        <div class="admin__archive">
          <h2>Frühere Versionen</h2>
          <p class="admin__archive-hint">Wiederherstellen und Löschen brauchen das Passwort von oben.</p>
          <ul>
            <?php foreach ($archive as $path):
              $name  = basename($path);
              $when  = DateTime::createFromFormat('Ymd-His', substr($name, 6, 15));
              $label = $when ? $when->format('d.m.Y, H:i') . ' Uhr' : $name;
              $esc   = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            ?>
              <li>
                <span class="admin__archive-date"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                <a href="../uploads/archive/<?= rawurlencode($name) ?>" target="_blank" rel="noopener">ansehen</a>
                <button type="submit" name="action" value="restore:<?= $esc ?>" formnovalidate
                        onclick="return confirm('Diese Version wieder online stellen? Die aktuelle Karte wandert ins Archiv.')">wiederherstellen</button>
                <button type="submit" name="action" value="delete:<?= $esc ?>" formnovalidate
                        onclick="return confirm('Diese Version endgültig löschen?')">löschen</button>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </form>

    <div class="admin__help">
      <ul>
        <li>Die neue Datei ersetzt die Speisekarte sofort auf <a href="../menue.html">yamyam-berlin.de/menue.html</a>.</li>
        <li>Die bisherige Version wird automatisch aufgehoben (die letzten <?= KEEP_VERSIONS ?> Stände) und kann unten mit einem Klick wieder online gestellt oder gelöscht werden.</li>
        <li>Wenn die Seite die alte Karte zeigt: einmal neu laden.</li>
      </ul>
    </div>

  </main>
</body>
</html>
