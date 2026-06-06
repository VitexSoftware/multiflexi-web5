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
 * Description of AppLinkButton.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class AppLinkButton extends \Ease\TWB5\LinkButton
{
    public function __construct(\MultiFlexi\Application $app, $properties = [])
    {
        $classes = trim((string) ($properties['class'] ?? '').' mf-entity-link mf-app-link');
        $properties['class'] = $classes;

        parent::__construct(
            'app.php?id='.$app->getMyKey(),
            [new AppLogo($app, ['style' => 'height: 40px']), '&nbsp;', _($app->getRecordName())],
            'light',
            $properties,
        );
    }
}
