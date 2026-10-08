<?php

use GlpiPlugin\Presence\PresenceClient;

// GLPI 11: nessun include di inc/includes.php; l'accesso è già limitato
// agli utenti autenticati dal firewall degli script legacy.

header('Content-Type: application/json; charset=UTF-8');

$id      = (int) ($_GET['users_id'] ?? 0);
$allowed = Session::haveRight('user', READ) || Session::haveRight('ticket', READ);
$user    = new User();

if ($id <= 0 || !$allowed || !$user->getFromDB($id)) {
    echo json_encode(['status' => 'unknown', 'label' => __('Non autorizzato', 'presence')]);
    return;
}

echo json_encode(PresenceClient::getStatus($user));
