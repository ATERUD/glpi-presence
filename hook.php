<?php

use GlpiPlugin\Presence\PresenceConfig;

function plugin_presence_install()
{
    $current = Config::getConfigurationValues(PresenceConfig::CONTEXT);
    $defaults = PresenceConfig::defaults();

    // Imposta solo i valori mancanti (non sovrascrive in caso di reinstallazione)
    $toSet = array_diff_key($defaults, $current);
    if (count($toSet)) {
        Config::setConfigurationValues(PresenceConfig::CONTEXT, $toSet);
    }

    return true;
}

function plugin_presence_uninstall()
{
    $config = new Config();
    $config->deleteByCriteria(['context' => PresenceConfig::CONTEXT]);

    return true;
}
