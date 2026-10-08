<?php

use GlpiPlugin\Presence\PresenceConfig;

// GLPI 11: nessun include di inc/includes.php, l'inizializzazione è automatica.

Session::checkRight('config', UPDATE);

if (isset($_POST['update'])) {
    PresenceConfig::saveFromPost($_POST);
    Html::back();
    return;
}

Html::header(__('Presenza in sede', 'presence'), $_SERVER['PHP_SELF'], 'config', 'plugins');
PresenceConfig::showForm();
Html::footer();
