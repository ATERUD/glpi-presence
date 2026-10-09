<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Presence\Presence;
use GlpiPlugin\Presence\PresenceProfile;

define('PLUGIN_PRESENCE_VERSION', '1.1.0');
define('PLUGIN_PRESENCE_MIN_GLPI', '11.0.0');
define('PLUGIN_PRESENCE_MAX_GLPI', '11.1.0');

/**
 * Inizializzazione del plugin
 */
function plugin_init_presence()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['presence'] = true;

    $plugin = new Plugin();
    if (!$plugin->isActivated('presence')) {
        return;
    }

    // Scheda "Presenza in sede" nei profili (diritto per profilo)
    Plugin::registerClass(PresenceProfile::class, ['addtabon' => ['Profile']]);

    // Pagina di configurazione (ingranaggio nella lista plugin)
    $PLUGIN_HOOKS['config_page']['presence'] = 'front/config.form.php';

    // Badge di presenza in cima ai form di Utente e Ticket
    $PLUGIN_HOOKS[Hooks::PRE_ITEM_FORM]['presence'] = [Presence::class, 'preItemForm'];
}

function plugin_version_presence()
{
    return [
        'name'         => 'Presenza in sede',
        'version'      => PLUGIN_PRESENCE_VERSION,
        'author'       => 'IT',
        'license'      => 'GPLv2+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_PRESENCE_MIN_GLPI,
                'max' => PLUGIN_PRESENCE_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

function plugin_presence_check_prerequisites()
{
    return true;
}

function plugin_presence_check_config($verbose = false)
{
    return true;
}
