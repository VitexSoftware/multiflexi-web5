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

require_once './init.php';

WebPage::singleton()->onlyForLogged();

// Optional narrowing to credential types based on one prototype (link from credentialprototypes.php)
$prototype = WebPage::getRequestValue('prototype');
$prototype = (\is_string($prototype) && preg_match('/^[A-Za-z0-9_]+$/', $prototype) === 1) ? $prototype : null;

WebPage::singleton()->setBreadcrumb(
    $prototype === null
        ? [_('Credential Types') => '']
        : [_('Credential Prototypes') => 'credentialprototypes.php', _('Credential Types') => 'credentialtypes.php', $prototype => ''],
);
WebPage::singleton()->addItem(new PageTop(_('Crednetial Types listing')));

$engine = (new \MultiFlexi\FilteredCredentialTypeLister())->setPrototype($prototype);

if ($prototype !== null) {
    // The DataTable passes the filter on to ajax2db.php
    $engine->filter = ['prototype' => $prototype];
    WebPage::singleton()->container->addItem(new \Ease\Html\DivTag(
        [
            new \Ease\Html\SpanTag(_('Prototype').': ', ['class' => 'me-2']),
            new \Ease\TWB5\Badge($prototype, 'secondary'),
            new \Ease\Html\ATag('credentialtypes.php', '✖ '._('Show all credential types'), ['class' => 'ms-3 small']),
        ],
        ['class' => 'mb-3'],
    ));
}

WebPage::singleton()->container->addItem(new DBDataTable($engine));

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
