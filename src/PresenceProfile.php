<?php

namespace GlpiPlugin\Presence;

use CommonGLPI;
use Html;
use Profile;
use ProfileRight;
use Session;

/**
 * Scheda "Presenza in sede" nei profili: abilita la visualizzazione per profilo.
 */
class PresenceProfile extends CommonGLPI
{
    public const RIGHT = 'plugin_presence_view';

    public static function getTypeName($nb = 0)
    {
        return __('Presenza in sede', 'presence');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('interface') === 'central') {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            self::showForProfile((int) $item->getID());
        }
        return true;
    }

    public static function showForProfile(int $profiles_id): void
    {
        $canedit = Session::haveRight('profile', UPDATE);
        $rights  = ProfileRight::getProfileRights($profiles_id, [self::RIGHT]);
        $current = (int) ($rights[self::RIGHT] ?? 0) & READ ? 1 : 0;

        echo "<div class='spaced'>";
        if ($canedit) {
            echo "<form method='post' action='" . htmlspecialchars(Profile::getFormURL(), ENT_QUOTES) . "'>";
        }

        echo "<div class='card'><div class='card-header'><h3 class='card-title'>"
            . htmlspecialchars(self::getTypeName(), ENT_QUOTES) . "</h3></div><div class='card-body'>";
        echo "<label class='form-label'>" . __('Può vedere la presenza degli utenti', 'presence') . "</label> ";
        if ($canedit) {
            echo "<select name='_" . self::RIGHT . "' class='form-select w-auto d-inline-block'>";
            echo "<option value='0'" . ($current ? '' : ' selected') . ">" . __('No') . "</option>";
            echo "<option value='" . READ . "'" . ($current ? ' selected' : '') . ">" . __('Yes') . "</option>";
            echo "</select>";
        } else {
            echo $current ? __('Yes') : __('No');
        }
        echo "</div>";

        if ($canedit) {
            echo "<div class='card-footer text-center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_x('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo "</div>";
            echo "</div>";
            Html::closeForm();
        } else {
            echo "</div>";
        }
        echo "</div>";
    }
}
