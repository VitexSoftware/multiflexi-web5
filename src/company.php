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
$companies = new Company(WebPage::getRequestValue('id', 'int'));

// Enforce access control
\MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess(
    (int) $companies->getMyKey(),
    sprintf(_('You do not have access to company "%s"'), $companies->getRecordName()),
);

WebPage::singleton()->setBreadcrumb([
    _('Companies') => 'companies.php',
    _('Company').': '.$companies->getRecordName() => '',
]);
WebPage::singleton()->addItem(new PageTop(_('Company').': '.$companies->getRecordName()));

$_SESSION['company'] = $companies->getMyKey();
$_SESSION['server'] = $companies->getDataValue('server');
$_SESSION['customer'] = $companies->getDataValue('customer');

$_SESSION['company'] = $companies->getMyKey();
$_SESSION['server'] = $companies->getDataValue('server');
$_SESSION['customer'] = $companies->getDataValue('customer');

$showOnly = (string) WebPage::getRequestValue('showOnly');

WebPage::singleton()->includeCss(WebPage::versionedAsset('css/nocturne-dashboard.css'));

// Slide-in quick-view drawer with the company record at a glance, so the
// details can be inspected without leaving the page.
$detailDrawer = new \Ease\TWB5\OffCanvas('companyDetail', _('Company details'), new CompanyDetails($companies), 'end');
$detailTrigger = (string) $detailDrawer->triggerButton(new \Ease\TWB5\Widgets\BsIcon('info-circle').'&nbsp;'._('Details'), 'outline-info btn-sm');

$jobData = new \MultiFlexi\DashboardData((int) $companies->getMyKey());

$page = new \Ease\Html\DivTag(null, ['class' => 'nc-page']);
$page->addItem(new CompanyHeader($companies, $detailTrigger));
$page->addItem(new CompanyAppsBar($companies));

$chartsRow = new \Ease\Html\DivTag(null, ['class' => 'nc-row']);
$chartsRow->addItem(new DashboardDailyChart($jobData));
$chartsRow->addItem(new DashboardJobHistory($jobData));
$page->addItem($chartsRow);

$page->addItem(new CompanyRecentJobs($companies, $showOnly));
$page->addItem($detailDrawer);

WebPage::singleton()->container->addItem($page);
WebPage::singleton()->addItem(new PageBottom('company/'.$companies->getMyKey()));
WebPage::singleton()->draw();
