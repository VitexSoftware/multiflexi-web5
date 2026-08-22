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

WebPage::singleton()->setBreadcrumb([]);
WebPage::singleton()->addItem(new PageTop(_('MultiFlexi')));

WebPage::singleton()->container->addItem(new CompaniesBar());

// Get filter parameter
$filter = WebPage::singleton()->getRequestValue('filter');

$chartEngine = new \MultiFlexi\FilteredCompanyJobLister();

// Apply filter if specified
if (!empty($filter)) {
    $chartEngine->applyFilter($filter);
}

$chartsRow = new \Ease\TWB5\Row();
$chartsRow->addColumn(8, new AllJobsLastMonthChart($chartEngine, ['id' => 'container']));
$chartsRow->addColumn(4, new JobGraphWidget());
WebPage::singleton()->container->addItem($chartsRow);

// Queue: same information as `multiflexi-cli queue:list`, with IDs used only as link targets
$engine = new \MultiFlexi\ScheduleLister();

WebPage::singleton()->container->addItem(new DBDataTable($engine));

WebPage::singleton()->addItem(new PageBottom('jobs'));

// Get dynamic object name for JavaScript and CSS (without namespace)
$objectName = \Ease\Functions::baseClassName($engine);

// Add compact table styling
WebPage::singleton()->addCSS(<<<CSS
    #{$objectName}_wrapper table.dataTable tbody td {
        padding: 4px 8px !important;
        line-height: 1.3 !important;
        vertical-align: middle !important;
    }
    #{$objectName}_wrapper table.dataTable tbody tr {
        height: 32px !important;
    }
    #{$objectName}_wrapper table.dataTable thead th {
        padding: 6px 8px !important;
        font-size: 0.9em !important;
    }
CSS);

WebPage::singleton()->addJavaScript(<<<'EOD'

    // Initialize Bootstrap 5 popovers (native API). Data attributes on each
    // trigger (data-bs-content / data-bs-trigger / data-bs-placement / data-bs-html)
    // are read automatically by bootstrap.Popover.
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
    $('#
EOD.$objectName.<<<'EOD'
').on('draw.dt', function () {
        mfInitPopovers();
    });

    setInterval(function () {
      $('#
EOD.$objectName.<<<'EOD'
').DataTable().ajax.reload();
    }, 60000);

EOD);

WebPage::singleton()->draw();
