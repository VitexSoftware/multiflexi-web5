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

use Ease\Html\ATag;
use Ease\TWB5\Row;
use MultiFlexi\Company;

require_once './init.php';
WebPage::singleton()->onlyForLogged();
WebPage::singleton()->addItem(new PageTop(_('Company')));

$companies = new Company(WebPage::getRequestValue('id', 'int'));

// Enforce access control
\MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess(
    (int) $companies->getMyKey(),
    sprintf(_('You do not have access to company "%s"'), $companies->getRecordName())
);

$_SESSION['company'] = $companies->getMyKey();

$companyEnver = new \MultiFlexi\CompanyEnv($companies);

if (WebPage::singleton()->isPosted()) {
    $companyEnver->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    $logger = new \MultiFlexi\Logger();
    $logger->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    // Delete each RunTemplate individually so its deleteFromSQL() properly
    // removes child rows (actionconfig, runtemplate_topics, jobs, etc.)
    // before removing the runtemplate itself.
    $rtpl = new \MultiFlexi\RunTemplate();

    foreach ($rtpl->listingQuery()->where('company_id', $companies->getMyKey()) as $rtplRow) {
        $rtplToDelete = new \MultiFlexi\RunTemplate((int) $rtplRow['id']);
        $rtplToDelete->deleteFromSQL();
    }

    $confer = new \MultiFlexi\Configuration();
    $confer->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    $appToCompany = new \MultiFlexi\CompanyApp();
    $appToCompany->deleteFromSQL(['company_id' => $companies->getMyKey()]);

    if ($companies->deleteFromSQL(['id' => $companies->getMyKey()])) {
        $companies->addStatusMessage(_('Company Deleted'), 'success');
        WebPage::singleton()->redirect('companies.php');
    } else {
        $companies->addStatusMessage(_('Error deleting Company').' '.$companies->getDataValue('name'), 'error');
    }

    $companies->unsetDataValue('name');
}

$instanceName = $companies->getDataValue('name');

if (empty($instanceName) === false) {
    $instanceLink = new ATag($companies->getDataValue('company'), $companies->getDataValue('company'));
} else {
    $instanceName = _('New Company');
    $instanceLink = null;
}

$instanceRow = new Row();
$instanceRow->addColumn(4, new DeleteCompanyForm($companies, null, ['action' => 'companydelete.php']));

if (empty($companies->getDataValue('logo')) === false) {
    $rightColumn[] = new \Ease\Html\ImgTag($companies->getDataValue('logo'), 'logo', ['class' => 'img-fluid']);
}

$rightColumn[] = new EnvironmentView($companyEnver);
$instanceRow->addColumn(8, $rightColumn);
WebPage::singleton()->container->addItem(new CompanyPanel($companies, $instanceRow));
WebPage::singleton()->addItem(new PageBottom());
WebPage::singleton()->draw();
