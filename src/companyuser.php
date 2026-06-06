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

use MultiFlexi\Company;

require_once './init.php';

WebPage::singleton()->onlyForLogged();

$canManageAssignments = !\MultiFlexi\Security\RbacHelpers::isAvailable()
    || \MultiFlexi\Security\RbacHelpers::isCurrentUserAdmin();

$company = new Company(WebPage::getRequestValue('company_id', 'int'));

if (null === $company->getMyKey()) {
    WebPage::singleton()->redirect('companies.php');
}

// Enforce access control
\MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess(
    (int) $company->getMyKey(),
    sprintf(_('You do not have access to company "%s"'), $company->getRecordName())
);

WebPage::singleton()->addItem(new PageTop(_('Access Rights for Company').': '.$company->getRecordName()));

if (!$canManageAssignments) {
    WebPage::singleton()->addStatusMessage(
        _('You can view company assignments, but only administrators can change them.'),
        'warning',
    );

    WebPage::singleton()->container->addItem(
        new \Ease\TWB5\Alert(
            'warning',
            '<strong>'._('Read-only mode').'</strong><br>'
            ._('You can view company assignments, but only administrators can change them.'),
            ['class' => 'mb-3'],
        ),
    );
}

WebPage::singleton()->container->addItem(
    new CompanyPanel(
        $company,
        new CompanyUserAssignment($company, $canManageAssignments),
    ),
);

WebPage::singleton()->addItem(new PageBottom('company/'.$company->getMyKey()));
WebPage::singleton()->draw();
