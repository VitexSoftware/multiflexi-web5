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
 * Description of ResultFileSelect.
 *
 * @author Vitex <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class ResultFileSelect extends \Ease\Html\SelectTag
{
    public function __construct(\MultiFlexi\Application $engine)
    {
        $items = ['' => _('None')];

        // Config fields only exist for a saved application. For a new/unsaved
        // app getMyKey() is empty and Conffield::getAppConfigs() would throw
        // (Ease\Euri::fromObject: "Object identifier is empty").
        if ($engine->getMyKey()) {
            foreach (\MultiFlexi\Conffield::getAppConfigs($engine) as $appConfigField) {
                $items[$appConfigField->getCode()] = $appConfigField->getCode();
            }
        }

        parent::__construct('resultfile', $items, '');
    }
}
