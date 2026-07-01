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

WebPage::singleton()->addItem(new PageTop(_('Users')));

$container = WebPage::singleton()->container;

$container->addItem(new \Ease\Html\DivTag([
    new \Ease\TWB5\LinkButton('createuser.php', '👤 '._('New User'), 'primary btn-sm'),
    ' ',
    new \Ease\TWB5\LinkButton('createaccount.php', '🤬 '._('New Admin'), 'warning btn-sm'),
], ['class' => 'mb-3 d-flex gap-2']));

$container->addItem(new DBDataTable(new \MultiFlexi\UserLister()));

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
