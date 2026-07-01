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

WebPage::singleton()->addItem(new PageTop(_('Credentials')));

// Get companies accessible to current user via RBAC
$accessibleCompanyIds = \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies();

if (empty($accessibleCompanyIds)) {
    WebPage::singleton()->container->addItem(new \Ease\TWB5\Alert(
        _('You do not have access to any companies. Please contact an administrator.'),
        'warning',
    ));
} else {
    // Use filtered credential lister that respects user access
    WebPage::singleton()->container->addItem(new DBDataTable(new \MultiFlexi\FilteredCredentialLister()));
}

WebPage::singleton()->addItem(new PageBottom('credentials'));
WebPage::singleton()->draw();
