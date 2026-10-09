<?php

use GlpiPlugin\Presence\PresenceClient;
use GlpiPlugin\Presence\PresenceConfig;

// GLPI 11: nessun include di inc/includes.php; l'accesso è già limitato
// agli utenti autenticati dal firewall degli script legacy.

header('Content-Type: application/json; charset=UTF-8');

$id   = (int) ($_GET['users_id'] ?? 0);
$user = new User();

// Controllo lato server: profilo con il diritto oppure utente nell'elenco autorizzati
if ($id <= 0 || !PresenceConfig::canViewPresence() || !$user->getFromDB($id)) {
    echo json_encode(['status' => 'unknown', 'label' => __('Non autorizzato', 'presence')]);
    return;
}

echo json_encode(PresenceClient::getStatus($user));
