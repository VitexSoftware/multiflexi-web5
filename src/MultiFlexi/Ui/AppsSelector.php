<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\Ui;

/**
 * Description of AppSelector.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class AppsSelector extends \Ease\Html\InputTextTag
{
    use \Ease\TWB5\Widgets\Selectizer;

    public function __construct($identifier = null, $enabled = [], $optionsPage = 'app.php')
    {
        parent::__construct($identifier, $enabled);

        $properties = [
            'valueField' => 'id',
            'labelField' => 'name',
            'searchField' => ['name', 'description', 'homepage'],
        ];

        $properties['render']['item'] = 'function (item, escape) { return "<div class=container><div class=row> <div class=col-md-2><a href=app.php?id=" + escape(item.id) + "><img height=40 align=left src=\"appimage.php?uuid=" + escape(item.uuid) + "\"></a></div><div class=col-md-7>&nbsp;" + escape(item.name) + "</div><div class=col-md-3><a href='.$optionsPage.'?id=" + escape(item.id) + "&interval='.$identifier.' style=\"font-size: 30px; padding: 5px;\" >🛠️️</a></div> </div></div>" }';
        $properties['render']['option'] = 'function (item, escape) { return "<div><img height=40 align=right src=\"appimage.php?uuid=" + escape(item.uuid) + "\">" + escape(item.name) + "<br><small>" + escape(item.description) + "</small></div>" }';
        $properties['plugins'] = ['remove_button'];

        $this->selectize($properties, self::addslashesColumns($this->availbleApps(), ['name', 'description']));
    }

    public function availbleApps()
    {
        $apper = new \MultiFlexi\Application();
        $currentLang = substr(\Ease\Locale::$localeUsed ?? 'en_US', 0, 2);

        return $apper->listingQuery()
            ->leftJoin('app_translations ON app_translations.app_id = apps.id AND app_translations.lang = ?', $currentLang)
            ->select(['id', 'COALESCE(app_translations.name, apps.name) AS name', 'COALESCE(app_translations.description, apps.description) AS description', 'homepage', 'uuid'], true)
            ->fetchAll();
    }

    /**
     * Escape strings in specified columns for safe embedding into JS.
     */
    public static function addslashesColumns(array $data, array $columns): array
    {
        foreach ($data as $rowId => $record) {
            foreach ($columns as $transcol) {
                if (\array_key_exists($transcol, $record) && \strlen((string) $record[$transcol])) {
                    $data[$rowId][$transcol] = addslashes($data[$rowId][$transcol]);
                }
            }
        }

        return $data;
    }
}
