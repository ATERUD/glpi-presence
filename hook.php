<?php

use GlpiPlugin\Presence\PresenceConfig;
use GlpiPlugin\Presence\PresenceProfile;

function plugin_presence_install()
{
    $current  = Config::getConfigurationValues(PresenceConfig::CONTEXT);
    $defaults = PresenceConfig::defaults();

    // Imposta solo i valori mancanti (non sovrascrive in caso di reinstallazione/aggiornamento)
    $toSet = array_diff_key($defaults, $current);
    if (count($toSet)) {
        Config::setConfigurationValues(PresenceConfig::CONTEXT, $toSet);
    }

    // Diritto per profilo: registrato solo la prima volta, poi concesso al profilo
    // di chi installa, così l'amministratore non resta senza accesso.
    // Le righe già presenti (es. rimaste dopo una migrazione o una disinstallazione
    // incompleta) vengono mantenute: si aggiungono solo i profili che ne sono privi.
    global $DB;

    $firstTime = countElementsInTable('glpi_profilerights', ['name' => PresenceProfile::RIGHT]) === 0;

    $existing = [];
    foreach ($DB->request(['SELECT' => ['profiles_id'], 'FROM' => 'glpi_profilerights', 'WHERE' => ['name' => PresenceProfile::RIGHT]]) as $row) {
        $existing[(int) $row['profiles_id']] = true;
    }
    foreach ($DB->request(['SELECT' => ['id'], 'FROM' => 'glpi_profiles']) as $profile) {
        if (!isset($existing[(int) $profile['id']])) {
            $DB->insertOrIgnore('glpi_profilerights', [
                'profiles_id' => (int) $profile['id'],
                'name'        => PresenceProfile::RIGHT,
                'rights'      => 0,
            ]);
        }
    }

    if ($firstTime && !empty($_SESSION['glpiactiveprofile']['id'])) {
        ProfileRight::updateProfileRights(
            (int) $_SESSION['glpiactiveprofile']['id'],
            [PresenceProfile::RIGHT => READ]
        );
        $_SESSION['glpiactiveprofile'][PresenceProfile::RIGHT] = READ;
    }

    return true;
}

function plugin_presence_uninstall()
{
    $config = new Config();
    $config->deleteByCriteria(['context' => PresenceConfig::CONTEXT]);

    global $DB;
    $DB->delete('glpi_profilerights', ['name' => PresenceProfile::RIGHT]);

    return true;
}
