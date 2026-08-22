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

WebPage::singleton()->setBreadcrumb([
    _('Administration') => 'users.php',
    _('Audit Log') => '',
]);
WebPage::singleton()->addItem(new PageTop(_('Audit Log')));

$auditEngine = new \MultiFlexi\Security\AuditLogEntry();
$auditEngine->setObjectName('AuditLog');
WebPage::singleton()->container->addItem(new DBDataTable($auditEngine, ['buttons' => false]));

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
