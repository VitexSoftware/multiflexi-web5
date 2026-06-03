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

if ((null === \Ease\Shared::user()->getUserID()) === false) {
    \Ease\Shared::user()->logout();
}

WebPage::singleton()->addItem(new PageTop(_('Sign Off')));

$logoutFace = new \Ease\Html\DivTag(null, ['id' => 'LogoutFace']);

$logoutCard = new \Ease\Html\DivTag(null, ['class' => 'mf-login-card text-center']);
$logoutCard->addItem(new \Ease\Html\DivTag(
    new \Ease\Html\ImgTag('images/project-logo.svg', 'MultiFlexi', ['style' => 'height: 90px']),
    ['class' => 'mf-login-logo'],
));
$logoutCard->addItem(new \Ease\Html\H2Tag('👋 '._('Good bye'), ['class' => 'mt-2 mb-1']));
$logoutCard->addItem(new \Ease\Html\PTag(
    _('You have been signed out successfully.'),
    ['class' => 'text-muted mb-4'],
));
$logoutCard->addItem(new \Ease\Html\DivTag(
    new \Ease\TWB5\LinkButton('login.php', '🚪 '._('Sign in again'), 'primary btn-lg w-100', ['id' => 'signinagainbutton']),
    ['class' => 'd-grid'],
));

$logoutFace->addItem($logoutCard);
WebPage::singleton()->container->addItem($logoutFace);

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
