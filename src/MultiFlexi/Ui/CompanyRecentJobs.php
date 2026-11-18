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
use MultiFlexi\DateTimeHelper;
use MultiFlexi\Scheduler;

/**
 * Newest jobs of one company (started and still scheduled) as a compact table.
 *
 * @no-named-arguments
 */
class CompanyRecentJobs extends \Ease\Html\DivTag
{
    private const LIMIT = 20;

    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(Company $company, string $showOnly = '', array $properties = [])
    {
        $companyId = (int) $company->getMyKey();
        $query = (new \MultiFlexi\Job())->listingQuery()
            ->select(['apps.name AS appname', 'apps.uuid AS uuid', 'job.id', 'begin', 'exitcode', 'launched_by', 'job.executor', 'job.schedule_type', 'login', 'job.app_id AS app_id', 'runtemplate.id AS runtemplate_id', 'runtemplate.name AS runtemplate_name', 'schedule AS scheduled'], true)
            ->leftJoin('apps ON apps.id = job.app_id')
            ->leftJoin('user ON user.id = job.launched_by')
            ->leftJoin('runtemplate ON runtemplate.id = job.runtemplate_id')
            ->where('job.company_id', $companyId)
            ->limit(self::LIMIT)
            ->orderBy('job.id DESC');

        switch ($showOnly) {
            case 'failed':
                $query->where('job.exitcode IS NOT NULL AND job.exitcode <> 0');

                break;
            case 'unfinished':
                $query->where('job.exitcode IS NULL');

                break;
            case 'success':
                $query->where('job.exitcode', 0);

                break;

            default:
                $showOnly = '';

                break;
        }

        $tz = DateTimeHelper::getConfiguredTimezone();
        $rows = '';

        foreach ($query->fetchAll() as $job) {
            $jobId = (int) $job['id'];
            $templateId = (int) $job['runtemplate_id'];
            $appId = (int) $job['app_id'];
            $template = empty($job['runtemplate_name']) ? '#'.$templateId : (string) $job['runtemplate_name'];

            if ($job['begin']) {
                $when = '<div class="nc-stack"><span class="nc-num">'.htmlspecialchars((string) $job['begin']).'</span><span class="nc-muted nc-small">'
                    .new \Ease\Html\Widgets\LiveAge(new \DateTime((string) $job['begin'], $tz)).'</span></div>';
                $action = '<a class="btn btn-sm btn-outline-secondary" href="schedule.php?id='.$templateId.'&app_id='.$appId.'&company_id='.$companyId.'">'.htmlspecialchars(_('Schedule')).'&nbsp;🚀</a>';
            } else {
                $when = '<div class="nc-stack"><span>⏳&nbsp;'.htmlspecialchars(_('Scheduled')).'</span>'
                    .($job['scheduled'] ? '<span class="nc-muted nc-small">'.new \Ease\Html\Widgets\LiveAge(new \DateTime((string) $job['scheduled'], $tz)).'</span>' : '').'</div>';
                $action = '<a class="btn btn-sm btn-outline-warning" href="schedule.php?cancel='.$jobId.'&templateid='.$templateId.'&app_id='.$appId.'&company_id='.$companyId.'">'.htmlspecialchars(_('Cancel')).'&nbsp;❌</a>';
            }

            $launcher = $job['launched_by']
                ? '<a href="user.php?id='.(int) $job['launched_by'].'" class="nc-tag">'.htmlspecialchars((string) $job['login']).'</a>'
                : '<span class="nc-tag">'.htmlspecialchars(_('Timer')).'</span>';
            $interval = trim(Scheduler::getIntervalEmoji(Scheduler::intervalToCode((string) $job['schedule_type'])).' '.($job['scheduled'] ?? ''));

            $rows .= '<tr>'
                .'<td><a href="job.php?id='.$jobId.'" title="'.htmlspecialchars(_('Job Info')).'" class="nc-job-link">'.new ExitCode($job['exitcode'], ['style' => 'font-size: 0.95em; font-family: monospace;']).'<span class="nc-muted nc-num">#'.$jobId.'</span></a></td>'
                .'<td><a href="runtemplate.php?id='.$templateId.'" class="nc-entity"><img src="appimage.php?uuid='.htmlspecialchars((string) $job['uuid']).'" alt="" class="nc-app-icon nc-app-icon-sm">'
                .'<span class="nc-stack"><span class="nc-plain">'.htmlspecialchars($template).'</span><span class="nc-muted nc-small">'.htmlspecialchars((string) $job['appname']).'</span></span></a></td>'
                .'<td>'.$when.'</td>'
                .'<td><div class="nc-stack">'.$launcher.($interval !== '' ? '<span class="nc-muted nc-small">'.htmlspecialchars($interval).'</span>' : '').'</div></td>'
                .'<td class="text-end">'.$action.'</td></tr>';
        }

        $segment = '';

        foreach (self::filters() as $value => $label) {
            $url = 'company.php?id='.$companyId.($value === '' ? '' : '&showOnly='.$value);
            $segment .= '<a class="btn btn-sm btn-outline-secondary'.($value === $showOnly ? ' active' : '').'" href="'.htmlspecialchars($url).'">'.htmlspecialchars($label).'</a>';
        }

        $html = '<div class="nc-section-head nc-wrap"><h2 class="nc-h2">'.htmlspecialchars(_('Recent jobs')).'</h2>'
            .'<div class="btn-group nc-segment" role="group">'.$segment.'</div></div>'
            .'<div class="nc-panel nc-table-panel"><div class="table-responsive"><table class="table nc-queue nc-jobs">'
            .'<thead><tr><th>'.htmlspecialchars(_('Job')).'</th><th>'.htmlspecialchars(_('Run template').' / '._('Application')).'</th><th>'.htmlspecialchars(_('Launch time')).'</th><th>'.htmlspecialchars(_('Launcher')).'</th><th></th></tr></thead>'
            .'<tbody>'.$rows.'</tbody></table></div>'
            .($rows === '' ? '<div class="nc-empty">'.htmlspecialchars(_('No jobs match the filter.')).'</div>' : '').'</div>';

        parent::__construct($html, $properties + ['class' => 'nc-section']);
    }

    /**
     * Result filters: showOnly value => label.
     *
     * @return array<string, string>
     */
    public static function filters(): array
    {
        return [
            '' => _('All'),
            'success' => _('Success'),
            'failed' => _('Failed'),
            'unfinished' => _('Unfinished'),
        ];
    }
}
