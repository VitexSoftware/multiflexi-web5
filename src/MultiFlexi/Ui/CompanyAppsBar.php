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
use MultiFlexi\CompanyApp;

/**
 * @no-named-arguments
 */
class CompanyAppsBar extends \Ease\Html\DivTag
{
    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(Company $company, array $properties = [])
    {
        $companyId = (int) $company->getMyKey();
        $companyApps = (new CompanyApp($company))->getAssigned()->leftJoin('apps ON apps.id = companyapp.app_id')->select(['apps.name', 'apps.description', 'apps.id', 'apps.uuid', 'apps.tags'], true)->fetchAll();

        $jobber = new \MultiFlexi\Job();
        $scheduler = new \MultiFlexi\Scheduler();
        $tiles = '';
        $tagCounts = [];

        foreach ($companyApps as $companyApp) {
            $appId = (int) $companyApp['id'];
            $name = _((string) $companyApp['name']);

            // Last executed job of the application in this company
            $lastJob = $jobber->listingQuery()
                ->select(['id', 'exitcode', 'begin', 'end', 'runtemplate_id'], true)
                ->where(['company_id' => $companyId, 'app_id' => $appId])
                ->where('begin IS NOT NULL')
                ->order('begin DESC')
                ->limit(1)
                ->fetchAll();

            $status = '<span class="nc-muted" title="'.htmlspecialchars(_('No job yet')).'">🪤</span>';

            if ($lastJob) {
                $job = $lastJob[0];
                $status = '<a href="job.php?id='.(int) $job['id'].'" title="'.htmlspecialchars(_('Last job')).'">'
                    .new ExitCode($job['exitcode'], ['style' => 'font-size: 0.9em; font-family: monospace;']).'</a>';

                if (empty($job['end'])) {
                    $status .= '<span class="hourglass-spin" title="'.htmlspecialchars(_('Running')).'">⏳</span>';
                }

                $future = $scheduler->listingQuery()
                    ->select(['schedule.id', 'schedule.job'], true)
                    ->leftJoin('job ON job.id = schedule.job')
                    ->where('job.runtemplate_id = ? AND schedule.after > ?', [$job['runtemplate_id'], date('Y-m-d H:i:s')])
                    ->order('schedule.after ASC')
                    ->limit(1)
                    ->fetchAll();

                if ($future) {
                    $status .= '<a href="job.php?id='.(int) $future[0]['job'].'" title="'.htmlspecialchars(_('Job waiting for execution')).'">⏳</a>';
                }
            }

            $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($companyApp['tags'] ?? '')))));

            foreach ($tags as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }

            $tiles .= '<div class="nc-app" data-tags="'.htmlspecialchars(mb_strtolower(implode(',', $tags))).'">'
                .'<a class="nc-app-main" href="companyapp.php?company_id='.$companyId.'&app_id='.$appId.'" title="'.htmlspecialchars(_((string) $companyApp['description'])).'">'
                .'<img src="appimage.php?uuid='.htmlspecialchars((string) $companyApp['uuid']).'" alt="" class="nc-app-icon">'
                .'<span class="nc-app-name">'.htmlspecialchars($name).'</span></a>'
                .'<span class="nc-app-status">'.$status.'</span></div>';
        }

        // Tag filter pills (same tags as the activation wizard filters by);
        // single-use tags are left out to keep the bar short.
        arsort($tagCounts);
        $pills = '';

        foreach ($tagCounts as $tag => $count) {
            if ($count < 2) {
                continue;
            }

            $pills .= '<button type="button" class="nc-pill" data-tag="'.htmlspecialchars(mb_strtolower((string) $tag)).'" aria-pressed="false">'
                .htmlspecialchars((string) $tag).' <span class="nc-pill-count">'.$count.'</span></button>';
        }

        $filter = $pills === '' ? '' : '<div class="nc-pills" id="nc-app-tags" role="group" aria-label="'.htmlspecialchars(_('Filter applications by tag')).'">'
            .'<button type="button" class="nc-pill is-active" data-tag="" aria-pressed="true">'.htmlspecialchars(_('All')).' <span class="nc-pill-count">'.\count($companyApps).'</span></button>'
            .$pills.'</div>';

        \Ease\WebPage::singleton()->addJavaScript(<<<'EOD'

                        (function () {
                            var bar = document.getElementById('nc-app-tags');
                            if (!bar) { return; }
                            var grid = document.querySelector('.nc-app-grid');
                            var counter = document.getElementById('nc-app-count');
                            var selected = [];

                            function apply() {
                                var shown = 0;
                                grid.querySelectorAll('.nc-app').forEach(function (tile) {
                                    var tags = (tile.dataset.tags || '').split(',');
                                    var show = selected.length === 0 || selected.some(function (t) { return tags.indexOf(t) !== -1; });
                                    tile.hidden = !show;
                                    if (show) { shown++; }
                                });
                                bar.querySelectorAll('.nc-pill').forEach(function (pill) {
                                    var on = pill.dataset.tag === '' ? selected.length === 0 : selected.indexOf(pill.dataset.tag) !== -1;
                                    pill.classList.toggle('is-active', on);
                                    pill.setAttribute('aria-pressed', on ? 'true' : 'false');
                                });
                                if (counter) { counter.textContent = selected.length ? shown + ' / ' + counter.dataset.total : counter.dataset.total; }
                            }

                            // Click = only this tag (click again = show all); Ctrl/Cmd/Shift+click adds tags
                            bar.addEventListener('click', function (e) {
                                var pill = e.target.closest('.nc-pill');
                                if (!pill) { return; }
                                var tag = pill.dataset.tag;
                                var add = e.ctrlKey || e.metaKey || e.shiftKey;
                                if (tag === '') { selected = []; }
                                else if (add) {
                                    var i = selected.indexOf(tag);
                                    if (i === -1) { selected.push(tag); } else { selected.splice(i, 1); }
                                } else {
                                    selected = (selected.length === 1 && selected[0] === tag) ? [] : [tag];
                                }
                                apply();
                            });
                        })();

            EOD);

        parent::__construct(
            '<div class="nc-section-head"><h2 class="nc-h2">'.htmlspecialchars(_('Applications')).'</h2>'
            .'<span class="nc-muted" id="nc-app-count" data-total="'.\count($companyApps).'">'.\count($companyApps).'</span></div>'
            .$filter
            .'<div class="nc-app-grid">'.$tiles.'</div>',
            $properties + ['class' => 'nc-section'],
        );
    }
}
