<?php

namespace GlpiPlugin\Presence;

use Config;
use GLPIKey;
use Html;

class PresenceConfig
{
    public const CONTEXT = 'plugin:presence';

    public static function defaults(): array
    {
        return [
            'endpoint_url'     => '',
            'identifier_field' => 'name', // name | email | registration_number | id
            'auth_header_name' => '',
            'auth_token'       => '',     // cifrato con GLPIKey
            'json_path'        => 'present',
            'cache_ttl'        => '60',
            'timeout'          => '5',
            'verify_ssl'       => '1',
        ];
    }

    public static function getAll(): array
    {
        return array_merge(self::defaults(), Config::getConfigurationValues(self::CONTEXT));
    }

    /**
     * Token in chiaro (decifrato) per la chiamata all'endpoint
     */
    public static function getToken(array $cfg): string
    {
        if (empty($cfg['auth_token'])) {
            return '';
        }
        return (string) (new GLPIKey())->decrypt($cfg['auth_token']);
    }

    public static function saveFromPost(array $post): void
    {
        $url = trim((string) ($post['endpoint_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            \Session::addMessageAfterRedirect(
                __('L\'URL deve iniziare con http:// o https://', 'presence'),
                false,
                ERROR
            );
            return;
        }

        $allowedIds = ['name', 'email', 'registration_number', 'id'];
        $idField = in_array($post['identifier_field'] ?? '', $allowedIds, true)
            ? $post['identifier_field']
            : 'name';

        $values = [
            'endpoint_url'     => $url,
            'identifier_field' => $idField,
            'auth_header_name' => trim((string) ($post['auth_header_name'] ?? '')),
            'json_path'        => trim((string) ($post['json_path'] ?? '')),
            'cache_ttl'        => (string) max(0, (int) ($post['cache_ttl'] ?? 60)),
            'timeout'          => (string) max(1, min(30, (int) ($post['timeout'] ?? 5))),
            'verify_ssl'       => !empty($post['verify_ssl']) ? '1' : '0',
        ];

        // Il token si aggiorna solo se compilato (campo vuoto = mantieni quello esistente)
        $token = (string) ($post['auth_token'] ?? '');
        if (!empty($post['clear_token'])) {
            $values['auth_token'] = '';
        } elseif ($token !== '') {
            $values['auth_token'] = (new GLPIKey())->encrypt($token);
        }

        Config::setConfigurationValues(self::CONTEXT, $values);
        \Session::addMessageAfterRedirect(__('Configurazione salvata', 'presence'));
    }

    public static function showForm(): void
    {
        $cfg    = self::getAll();
        global $CFG_GLPI;
        $action = $CFG_GLPI['root_doc'] . '/plugins/presence/front/config.form.php';
        $e      = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);

        $idOptions = [
            'name'                => __('Login (nome utente)', 'presence'),
            'email'               => __('Email predefinita', 'presence'),
            'registration_number' => __('Numero di matricola', 'presence'),
            'id'                  => __('ID utente GLPI', 'presence'),
        ];

        echo "<form method='post' action='" . $e($action) . "' class='card'>";
        echo "<div class='card-header'><h3 class='card-title'>" . __('Presenza in sede - Configurazione', 'presence') . "</h3></div>";
        echo "<div class='card-body'>";

        // URL
        echo "<div class='mb-3'><label class='form-label'>" . __('URL endpoint', 'presence') . "</label>";
        echo "<input type='text' class='form-control' name='endpoint_url' value='" . $e($cfg['endpoint_url']) . "' placeholder='https://server/api/presence?user={user}'>";
        echo "<div class='form-text'>" . __('Usa {user} come segnaposto per l\'identificativo. Se omesso, viene aggiunto il parametro ?user=...', 'presence') . "</div></div>";

        // Identificativo
        echo "<div class='mb-3'><label class='form-label'>" . __('Identificativo da inviare', 'presence') . "</label>";
        echo "<select class='form-select' name='identifier_field'>";
        foreach ($idOptions as $k => $label) {
            $sel = $cfg['identifier_field'] === $k ? ' selected' : '';
            echo "<option value='" . $e($k) . "'$sel>" . $e($label) . "</option>";
        }
        echo "</select></div>";

        // Autenticazione
        echo "<div class='row'><div class='col-md-4 mb-3'><label class='form-label'>" . __('Nome header di autenticazione', 'presence') . "</label>";
        echo "<input type='text' class='form-control' name='auth_header_name' value='" . $e($cfg['auth_header_name']) . "' placeholder='Authorization'></div>";
        echo "<div class='col-md-8 mb-3'><label class='form-label'>" . __('Valore header (es. Bearer xxxx)', 'presence') . "</label>";
        echo "<input type='password' class='form-control' name='auth_token' value='' autocomplete='new-password' placeholder='"
            . (empty($cfg['auth_token']) ? '' : $e(__('(impostato - lascia vuoto per mantenerlo)', 'presence'))) . "'>";
        if (!empty($cfg['auth_token'])) {
            echo "<label class='form-check mt-1'><input type='checkbox' class='form-check-input' name='clear_token' value='1'> <span class='form-check-label'>"
                . __('Rimuovi il valore salvato', 'presence') . "</span></label>";
        }
        echo "</div></div>";

        // Parsing risposta
        echo "<div class='mb-3'><label class='form-label'>" . __('Percorso JSON del valore di presenza', 'presence') . "</label>";
        echo "<input type='text' class='form-control' name='json_path' value='" . $e($cfg['json_path']) . "' placeholder='present'>";
        echo "<div class='form-text'>" . __('Notazione puntata, es. data.status.present. Vuoto = la risposta stessa. Valori veri: true, 1, yes, si, presente, in, online. Valori falsi: false, 0, no, assente, out, offline.', 'presence') . "</div></div>";

        // Cache / timeout / SSL
        echo "<div class='row'><div class='col-md-4 mb-3'><label class='form-label'>" . __('Cache (secondi, 0 = disattivata)', 'presence') . "</label>";
        echo "<input type='number' min='0' class='form-control' name='cache_ttl' value='" . $e($cfg['cache_ttl']) . "'></div>";
        echo "<div class='col-md-4 mb-3'><label class='form-label'>" . __('Timeout (secondi)', 'presence') . "</label>";
        echo "<input type='number' min='1' max='30' class='form-control' name='timeout' value='" . $e($cfg['timeout']) . "'></div>";
        echo "<div class='col-md-4 mb-3 d-flex align-items-end'><label class='form-check'>";
        echo "<input type='checkbox' class='form-check-input' name='verify_ssl' value='1'" . ($cfg['verify_ssl'] === '1' ? ' checked' : '') . ">";
        echo " <span class='form-check-label'>" . __('Verifica certificato SSL', 'presence') . "</span></label></div></div>";

        echo "</div>";
        echo "<div class='card-footer text-center'>";
        echo Html::submit(_x('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
        echo "</div>";
        Html::closeForm();
    }
}
