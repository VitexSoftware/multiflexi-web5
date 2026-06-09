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

// Get companies accessible to current user via RBAC
$accessibleCompanyIds = \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies();

if (empty($accessibleCompanyIds)) {
    WebPage::singleton()->addItem(new PageTop(_('Company list')));
    WebPage::singleton()->container->addItem(new \Ease\TWB5\Alert(
        _('You do not have access to any companies. Please contact an administrator.'),
        'warning',
    ));
} else {
    $companies = new Company();
    WebPage::singleton()->addItem(new PageTop(_('Company list')));

    // Slide-in filter drawer (GET form). Filters are applied server-side below.
    $filterName = (string) \Ease\WebPage::getRequestValue('fname');
    $filterIc = (string) \Ease\WebPage::getRequestValue('fic');

    $filterDrawer = new FilterOffCanvas('companiesFilter', [
        FilterOffCanvas::textField('fname', _('Name contains'), $filterName),
        FilterOffCanvas::textField('fic', _('Registration No. contains'), $filterIc),
    ], 'companies.php');

    WebPage::singleton()->container->addItem(new \Ease\Html\DivTag(
        $filterDrawer->triggerButton(new \Ease\TWB5\Widgets\BsIcon('funnel').'&nbsp;'._('Filters'), 'outline-secondary'),
        ['class' => 'mb-3 text-end'],
    ));
    WebPage::singleton()->container->addItem($filterDrawer);

    $companyTable = new \Ease\TWB5\Table();

    foreach ($companies->listingQuery() as $companyInfo) {
        $companyId = $companyInfo['id'];

        // Only show companies user has access to
        if (!\in_array($companyId, $accessibleCompanyIds, true)) {
            continue;
        }

        // Apply slide-in drawer filters
        if ($filterName !== '' && stripos((string) $companyInfo['name'], $filterName) === false) {
            continue;
        }

        if ($filterIc !== '' && stripos((string) $companyInfo['ic'], $filterIc) === false) {
            continue;
        }

        $companies->setData($companyInfo);
        //    $companyColumns['enabled'] = new \Ease\Html\Widgets\SemaforLight($companyInfo['enabled'] === 1 ? 'green' : 'red', ['width' => 20]);
        $companyColumns['logo'] = new CompanyLinkButton($companies, ['height' => '64px']);
        $companyColumns['name'] = new \Ease\Html\ATag('company.php?id='.$companyId, $companyInfo['name']);
        $companyColumns['ic'] = $companyInfo['ic'];

        $companyColumns['setup'] = new \Ease\TWB5\LinkButton('companysetup.php?id='.$companyId, '🛠️&nbsp;'._('Setup'), 'secondary btn-lg w-100 ', ['title' => _('Setup company'), 'id' => 'setupcompanybutton']);
        $companyColumns['tasks'] = new \Ease\TWB5\LinkButton('tasks.php?company_id='.$companyId, '🔧&nbsp;'._('Tasks'), 'secondary btn-lg w-100', ['title' => _('View tasks'), 'id' => 'taskcompanybutton']);
        $companyColumns['apps'] = new \Ease\TWB5\LinkButton('companyapps.php?company_id='.$companyId, '📌&nbsp;'._('Applications'), 'secondary btn-lg w-100', ['title' => _('View applications'), 'id' => 'appscompanybutton']);
        $companyColumns['delete'] = new \Ease\TWB5\LinkButton('companydelete.php?id='.$companyId, '☠️&nbsp;'._('Delete'), 'danger', ['title' => _('Delete company'), 'id' => 'deletecompanybutton']);

        $companyTable->addRowColumns($companyColumns);
    }

    WebPage::singleton()->container->addItem($companyTable);
}

WebPage::singleton()->addItem(new PageBottom('companies'));
WebPage::singleton()->draw();
