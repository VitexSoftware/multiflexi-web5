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

WebPage::singleton()->container->addItem(
    new CompanyPanel(
        $company,
        new CompanyUserAssignment($company),
    ),
);

WebPage::singleton()->addItem(new PageBottom('company/'.$company->getMyKey()));
WebPage::singleton()->draw();
