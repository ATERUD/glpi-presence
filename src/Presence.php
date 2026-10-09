<?php

namespace GlpiPlugin\Presence;

use CommonITILActor;
use Html;
use Ticket;
use User;

class Presence
{
    /**
     * Hook pre_item_form: mostra il badge in cima al form di Utente e Ticket
     */
    public static function preItemForm(array $params): void
    {
        // Nessun badge (e nessuna chiamata all'endpoint) per chi non ha il permesso
        if (!PresenceConfig::canViewPresence()) {
            return;
        }

        $item = $params['item'] ?? null;

        if ($item instanceof User && $item->getID() > 0) {
            self::renderBadges([$item->getID()], false);
            return;
        }

        if ($item instanceof Ticket && $item->getID() > 0) {
            $ids = [];
            foreach ($item->getUsers(CommonITILActor::REQUESTER) as $actor) {
                if (!empty($actor['users_id'])) {
                    $ids[] = (int) $actor['users_id'];
                }
            }
            if (count($ids)) {
                self::renderBadges(array_unique($ids), true);
            }
        }
    }

    private static function renderBadges(array $userIds, bool $withName): void
    {
        $uid = 'presence_' . mt_rand();
        global $CFG_GLPI;
        $url = $CFG_GLPI['root_doc'] . '/plugins/presence/ajax/status.php';

        echo "<div id='$uid' class='plugin-presence mb-2'>";
        foreach ($userIds as $id) {
            $label = $withName
                ? htmlspecialchars(User::getFriendlyNameById($id), ENT_QUOTES) . ' - '
                : '';
            echo "<span class='badge bg-secondary me-1' data-presence-user='" . (int) $id . "'"
                . " data-presence-prefix='" . $label . "'>"
                . $label . htmlspecialchars(__('Presenza: verifica in corso...', 'presence'), ENT_QUOTES)
                . "</span>";
        }
        echo "</div>";

        $js = <<<JS
(function () {
    var root = document.getElementById('{$uid}');
    if (!root) { return; }
    var base = %s;
    root.querySelectorAll('[data-presence-user]').forEach(function (el) {
        var prefix = el.getAttribute('data-presence-prefix') || '';
        fetch(base + '?users_id=' + encodeURIComponent(el.getAttribute('data-presence-user')), {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var cls = {present: 'bg-success', absent: 'bg-danger'}[d.status] || 'bg-secondary';
                el.className = 'badge me-1 ' + cls;
                el.textContent = prefix + (d.label || '');
                if (d.status === 'unknown') { el.title = d.label || ''; }
            })
            .catch(function () {
                el.className = 'badge me-1 bg-secondary';
                el.textContent = prefix + %s;
            });
    });
})();
JS;
        echo Html::scriptBlock(sprintf(
            $js,
            json_encode($url),
            json_encode(__('Presenza non disponibile', 'presence'))
        ));
    }
}
