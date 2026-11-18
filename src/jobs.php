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

// Získání parametrů filtru
$appId = WebPage::singleton()->getRequestValue('app_id');
$companyId = WebPage::singleton()->getRequestValue('company_id');
$filter = WebPage::singleton()->getRequestValue('filter');

// Use filtered job lister that respects user access via RBAC
$engine = new \MultiFlexi\FilteredCompanyJobLister();
$objectName = \Ease\Functions::baseClassName($engine);

// Nastavení titulku podle filtru
$pageTitle = JobFilterToolbar::getPageTitle($filter);

WebPage::singleton()->setBreadcrumb([
    _('Administration') => 'users.php',
    $pageTitle => '',
]);
WebPage::singleton()->addItem(new PageTop($pageTitle));

// Add filter toolbar
WebPage::singleton()->container->addItem(new JobFilterToolbar($filter, 'jobs.php'));

// Job history graph scoped to the active company/app filters
$companyIdInt = $companyId ? (int) $companyId : null;
$appIdInt = $appId ? (int) $appId : null;
WebPage::singleton()->container->addItem(new JobGraphWidget(null, $companyIdInt, $appIdInt));

// Row colours of job states (job-success, job-failed, ...) live in css/nocturne.css

$engine->setCompany($companyId);
$engine->setApp($appId);

// Aplikace filtru pokud je specifikován
if (!empty($filter)) {
    $engine->applyFilter($filter);
}

WebPage::singleton()->addJavaScript(<<<'EOD'
    $.fn.dataTable.ext.buttons.dismisAll = {
        text: '
    EOD._('Dismis All').<<<'EOD'
    ',
        action: function ( e, dt, node, config ) {
            $( ".dismis" ).each(function() {
                $( this ).click();
            });
            dt.ajax.reload();
        }
    };
    EOD);
WebPage::singleton()->includeJavascript('js/dismisLog.js');
WebPage::singleton()->container->addItem(new DBDataTable($engine, ['buttons' => ['dismisAll']]));
WebPage::singleton()->addItem(new PageBottom('jobs'));
WebPage::singleton()->addJavaScript(<<<'EOD'

        // Initialize Bootstrap 5 popovers (native API) — data-bs-* attributes are read automatically
        function mfInitPopovers() {
            document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
                if (!bootstrap.Popover.getInstance(el)) {
                    new bootstrap.Popover(el, { container: 'body' });
                }
            });
        }
        document.addEventListener('DOMContentLoaded', mfInitPopovers);
        mfInitPopovers();

        // Reinitialize popovers after every DataTable redraw
        $('#Molecule').on('draw.dt', function () {
            mfInitPopovers();
        });

        setInterval(function () {
          Molecule.ajax.reload();
    }, 300000);

    EOD);

WebPage::singleton()->draw();
