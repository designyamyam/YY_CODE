<?php
// Vorlage — die echte admin/config.php schreibt der Deploy-Workflow aus dem
// GitHub-Secret MENU_UPLOAD_PASSWORD (Settings → Secrets and variables → Actions).
// Nicht committen; sie ist in .gitignore.
define('MENU_UPLOAD_PASSWORD_HASH', '$6$…');
