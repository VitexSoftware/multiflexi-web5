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
WebPage::singleton()->includeCss(WebPage::versionedAsset('css/nocturne-dashboard.css'));

// Summary of every company the user may access (RBAC is applied inside DashboardData)
$data = new \MultiFlexi\DashboardData();

$page = new \Ease\Html\DivTag(null, ['class' => 'nc-page']);
$page->addItem(new DashboardKpiStrip($data));
$page->addItem(new DashboardCompanyGrid($data));

$chartsRow = new \Ease\Html\DivTag(null, ['class' => 'nc-row']);
$chartsRow->addItem(new DashboardDailyChart($data));
$chartsRow->addItem(new DashboardJobHistory($data));
$page->addItem($chartsRow);

// Queue: same information as `multiflexi-cli queue:list`, grouped by day
$page->addItem(new DashboardQueue($data));

WebPage::singleton()->container->addItem($page);

WebPage::singleton()->addItem(new PageBottom('jobs'));

WebPage::singleton()->addJavaScript(<<<'EOD'

        (function () {
            // Client side filters of the queue: interval segment + free text
            function applyQueueFilters() {
                var table = document.getElementById('nc-queue');
                if (!table) { return; }
                var checked = document.querySelector('input[name="nc-iv"]:checked');
                var iv = checked ? checked.value : 'all';
                var qInput = document.getElementById('nc-queue-q');
                var q = qInput ? qInput.value.trim().toLowerCase() : '';
                var shown = 0;

                table.querySelectorAll('tbody.nc-queue-group').forEach(function (group) {
                    var visible = 0;
                    group.querySelectorAll('tr[data-iv]').forEach(function (row) {
                        var show = (iv === 'all' || row.dataset.iv === iv) && (q === '' || row.dataset.q.indexOf(q) !== -1);
                        row.hidden = !show;
                        if (show) { visible++; }
                    });
                    group.querySelector('.nc-group-head').hidden = visible === 0;
                    shown += visible;
                });

                var empty = document.getElementById('nc-queue-empty');
                if (empty) { empty.hidden = shown !== 0; }
            }

            document.addEventListener('input', function (e) {
                if (e.target && e.target.id === 'nc-queue-q') { applyQueueFilters(); }
            });
            document.addEventListener('change', function (e) {
                if (e.target && e.target.name === 'nc-iv') { applyQueueFilters(); }
            });

            // Refresh the queue section every minute, keeping the chosen filters
            setInterval(function () {
                var section = document.getElementById('nc-queue-section');
                if (!section) { return; }
                var iv = (document.querySelector('input[name="nc-iv"]:checked') || {}).value || 'all';
                var q = (document.getElementById('nc-queue-q') || {}).value || '';
                fetch(window.location.href, { credentials: 'same-origin' })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('nc-queue-section');
                        if (!fresh) { return; }
                        section.innerHTML = fresh.innerHTML;
                        var radio = document.getElementById('nc-iv-' + iv);
                        if (radio) { radio.checked = true; }
                        document.getElementById('nc-queue-q').value = q;
                        applyQueueFilters();
                    })
                    .catch(function () {});
            }, 60000);
        })();

    EOD);

WebPage::singleton()->draw();
