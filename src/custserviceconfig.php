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

use MultiFlexi\Configuration;

/**
 * MultiFlexi - Config fields editor.
 *
 * @deprecated since version 1.14
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2020-2026 Vitex Software
 */

require_once './init.php';
WebPage::singleton()->onlyForLogged();
$appId = WebPage::getRequestValue('app_id', 'int');
$companyId = WebPage::getRequestValue('company_id', 'int');

$cscBreadcrumb = [];

if ($companyId) {
    $cscCompany = new \MultiFlexi\Company($companyId);
    $cscBreadcrumb[_('Company').': '.$cscCompany->getRecordName()] = $cscCompany->getLink();
}

if ($appId) {
    $cscApp = new \MultiFlexi\Application($appId);
    $cscBreadcrumb[_('Application').': '.$cscApp->getRecordName()] = $cscApp->getLink();
}

$cscBreadcrumb[_('App custom config Fields')] = '';
WebPage::singleton()->setBreadcrumb($cscBreadcrumb);
WebPage::singleton()->addItem(new PageTop(_('App custom config Fields')));

$configurator = new Configuration(['app_id' => $appId, 'company_id' => $companyId], ['autoload' => false]);
$configurator->setDataValue('app_id', $appId);

if (WebPage::singleton()->isPosted()) {
    if ($configurator->takeData($_POST) && null !== $configurator->saveToSQL()) {
        $configurator->addStatusMessage(_('Config fields Saved'), 'success');
    } else {
        $configurator->addStatusMessage(
            _('Error saving Config fields'),
            'error',
        );
    }
}

$app = new \MultiFlexi\Application($appId);
$company = new \MultiFlexi\Company($companyId);
$runTemplater = new \MultiFlexi\RunTemplate();
$runTemplater->loadFromSQL($runTemplater->runTemplateID($appId, $companyId));

$appPanel = new ApplicationPanel($app, new CustomAppConfigForm($configurator));

$runTemplateButton = new \Ease\TWB5\LinkButton('runtemplate.php?id='.$runTemplater->getMyKey(), '⚗️&nbsp;'._('Run Template'), 'dark btn-lg w-100');
$appPanel->headRow->addColumn(2, $runTemplateButton);

WebPage::singleton()->container->addItem(new CompanyPanel($company, $appPanel));

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
