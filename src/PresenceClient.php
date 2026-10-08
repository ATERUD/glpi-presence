<?php

namespace GlpiPlugin\Presence;

use GuzzleHttp\Client;
use Throwable;
use User;
use UserEmail;

class PresenceClient
{
    private const TRUE_VALUES  = ['1', 'true', 'yes', 'si', 'sì', 'presente', 'present', 'in', 'on', 'online'];
    private const FALSE_VALUES = ['0', 'false', 'no', 'assente', 'absent', 'out', 'off', 'offline'];

    /**
     * Restituisce ['status' => present|absent|unknown, 'label' => string, 'cached' => bool]
     */
    public static function getStatus(User $user): array
    {
        global $GLPI_CACHE;

        $cfg = PresenceConfig::getAll();
        if ($cfg['endpoint_url'] === '') {
            return self::result('unknown', __('Endpoint non configurato', 'presence'));
        }

        $identifier = self::getIdentifier($user, $cfg['identifier_field']);
        if ($identifier === '') {
            return self::result('unknown', __('Identificativo utente mancante', 'presence'));
        }

        $ttl = (int) $cfg['cache_ttl'];
        $key = 'plugin_presence_' . md5($cfg['endpoint_url'] . '|' . $identifier);

        if ($ttl > 0 && isset($GLPI_CACHE)) {
            try {
                $hit = $GLPI_CACHE->get($key);
                if (is_array($hit)) {
                    $hit['cached'] = true;
                    return $hit;
                }
            } catch (Throwable $e) {
                // cache non disponibile: si prosegue senza
            }
        }

        $result = self::fetch($cfg, $identifier);

        // Si mette in cache solo un esito valido, mai un errore
        if ($ttl > 0 && isset($GLPI_CACHE) && $result['status'] !== 'unknown') {
            try {
                $GLPI_CACHE->set($key, $result, $ttl);
            } catch (Throwable $e) {
            }
        }

        return $result;
    }

    private static function fetch(array $cfg, string $identifier): array
    {
        $url = self::buildUrl($cfg['endpoint_url'], $identifier);

        $headers = ['Accept' => 'application/json'];
        $token   = PresenceConfig::getToken($cfg);
        if ($cfg['auth_header_name'] !== '' && $token !== '') {
            $headers[$cfg['auth_header_name']] = $token;
        }

        try {
            $client   = new Client([
                'timeout'         => (int) $cfg['timeout'],
                'connect_timeout' => (int) $cfg['timeout'],
                'verify'          => $cfg['verify_ssl'] === '1',
                'http_errors'     => false,
            ]);
            $response = $client->get($url, ['headers' => $headers]);
        } catch (Throwable $e) {
            \Toolbox::logInFile('presence', 'Errore chiamata endpoint: ' . $e->getMessage() . "\n");
            return self::result('unknown', __('Endpoint non raggiungibile', 'presence'));
        }

        $code = $response->getStatusCode();
        if ($code < 200 || $code >= 300) {
            \Toolbox::logInFile('presence', "Endpoint ha risposto HTTP $code per $url\n");
            return self::result('unknown', sprintf(__('Risposta HTTP %d', 'presence'), $code));
        }

        $present = self::parse((string) $response->getBody(), $cfg['json_path']);
        if ($present === null) {
            return self::result('unknown', __('Risposta non interpretabile', 'presence'));
        }

        return $present
            ? self::result('present', __('In sede', 'presence'))
            : self::result('absent', __('Non in sede', 'presence'));
    }

    private static function buildUrl(string $template, string $identifier): string
    {
        $encoded = rawurlencode($identifier);
        if (strpos($template, '{user}') !== false) {
            return str_replace('{user}', $encoded, $template);
        }
        return $template . (strpos($template, '?') === false ? '?' : '&') . 'user=' . $encoded;
    }

    private static function getIdentifier(User $user, string $field): string
    {
        switch ($field) {
            case 'email':
                return (string) UserEmail::getDefaultForUser($user->getID());
            case 'registration_number':
                return trim((string) ($user->fields['registration_number'] ?? ''));
            case 'id':
                return (string) $user->getID();
            case 'name':
            default:
                return trim((string) ($user->fields['name'] ?? ''));
        }
    }

    private static function parse(string $body, string $path): ?bool
    {
        $data = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            $value = $data;
            if ($path !== '') {
                foreach (explode('.', $path) as $segment) {
                    if (is_array($value) && array_key_exists($segment, $value)) {
                        $value = $value[$segment];
                    } else {
                        return null;
                    }
                }
            }
        } else {
            // Risposta non JSON: si usa il testo grezzo
            $value = trim($body);
        }

        return self::toBool($value);
    }

    private static function toBool($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            $v = mb_strtolower(trim($value));
            if (in_array($v, self::TRUE_VALUES, true)) {
                return true;
            }
            if (in_array($v, self::FALSE_VALUES, true)) {
                return false;
            }
        }
        return null;
    }

    private static function result(string $status, string $label): array
    {
        return ['status' => $status, 'label' => $label, 'cached' => false];
    }
}
