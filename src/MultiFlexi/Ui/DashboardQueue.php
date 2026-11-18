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

use MultiFlexi\DashboardData;
use MultiFlexi\Job;
use MultiFlexi\ScheduleLister;
use MultiFlexi\Scheduler;

/**
 * Queue of upcoming scheduled jobs, grouped by day, with an interval filter
 * and a text filter (both client side, no reload).
 *
 * @no-named-arguments
 */
class DashboardQueue extends \Ease\Html\DivTag
{
    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(DashboardData $data, array $properties = [])
    {
        $rows = $data->queueRows();
        $total = $data->queueCount();
        $today = new \DateTimeImmutable('today');

        $groups = [];

        foreach ($rows as $row) {
            $groups[substr((string) $row['after'], 0, 10)][] = $row;
        }

        $tbody = '';

        foreach ($groups as $date => $groupRows) {
            $tbody .= '<tbody class="nc-queue-group"><tr class="nc-group-head"><td colspan="6">'
                .htmlspecialchars(self::dayLabel($date, $today))
                .' <span class="nc-muted nc-group-count">'.\count($groupRows).'</span></td></tr>';

            foreach ($groupRows as $row) {
                $tbody .= self::row($row);
            }

            $tbody .= '</tbody>';
        }

        $segment = '';

        foreach (self::intervalFilters() as $code => $label) {
            $segment .= '<input type="radio" class="btn-check" name="nc-iv" id="nc-iv-'.$code.'" value="'.$code.'" autocomplete="off"'.($code === 'all' ? ' checked' : '').'>'
                .'<label class="btn btn-sm btn-outline-secondary" for="nc-iv-'.$code.'">'.htmlspecialchars($label).'</label>';
        }

        $count = \count($rows);
        $countText = $count < $total ? sprintf(_('%d of %d'), $count, $total) : (string) $total;

        $html = '<div class="nc-section-head nc-wrap">'
            .'<div class="nc-inline"><h2 class="nc-h2">'.htmlspecialchars(_('Scheduled jobs queue')).'</h2>'
            .'<span class="nc-muted" id="nc-queue-count" data-total="'.$count.'">'.htmlspecialchars($countText).'</span></div>'
            .'<div class="nc-inline"><div class="btn-group" role="group" aria-label="'.htmlspecialchars(_('Interval')).'">'.$segment.'</div>'
            .'<input type="search" class="form-control form-control-sm nc-queue-filter" id="nc-queue-q" placeholder="'.htmlspecialchars(_('Filter the queue')).'" aria-label="'.htmlspecialchars(_('Filter the queue')).'"></div></div>'
            .'<div class="nc-panel nc-table-panel"><div class="table-responsive"><table class="table nc-queue" id="nc-queue">'
            .'<thead><tr><th>'.htmlspecialchars(_('Starts')).'</th><th>'.htmlspecialchars(_('Interval')).'</th><th>'.htmlspecialchars(_('App')).'</th><th>'.htmlspecialchars(_('Runtemplate')).'</th><th>'.htmlspecialchars(_('Company')).'</th><th class="text-end">'.htmlspecialchars(_('Job')).'</th></tr></thead>'
            .$tbody.'</table></div>'
            .'<div class="nc-empty" id="nc-queue-empty"'.($count === 0 ? '' : ' hidden').'>'.htmlspecialchars(_('No scheduled jobs match the filter.')).'</div></div>'
            .'<div class="nc-foot nc-muted nc-small">'.htmlspecialchars(_('Refreshes automatically every minute')).'</div>';

        parent::__construct($html, $properties + ['class' => 'nc-section', 'id' => 'nc-queue-section']);
    }
    /**
     * Interval filter buttons: code => label.
     *
     * @return array<string, string>
     */
    public static function intervalFilters(): array
    {
        return [
            'all' => _('All'),
            'h' => _('Hourly'),
            'd' => _('Daily'),
            'w' => _('Weekly'),
            'm' => _('Monthly'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function row(array $row): string
    {
        $after = new \DateTime((string) $row['after']);
        $type = $row['schedule_type'] ?? null;
        $adhoc = [Job::SCHEDULE_TYPE_ADHOC, Job::SCHEDULE_TYPE_ADHOC_WEB, Job::SCHEDULE_TYPE_ADHOC_CLI, Job::SCHEDULE_TYPE_ADHOC_API, Job::SCHEDULE_TYPE_EVENT, Job::SCHEDULE_TYPE_COMMAND_LINE];
        $code = ($type === null || $type === '' || \in_array($type, $adhoc, true)) ? 'x' : Scheduler::intervalToCode((string) $type);
        $app = (string) ($row['app_name'] ?? '');
        $company = (string) ($row['company_name'] ?? '');
        $template = (string) ($row['runtemplate_name'] ?? '');
        $search = mb_strtolower($app.' '.$template.' '.$company.' '.$row['job']);

        return '<tr data-iv="'.htmlspecialchars($code).'" data-q="'.htmlspecialchars($search).'">'
            .'<td class="nc-num"><div class="nc-stack"><span>'.$after->format('H:i').'</span><span class="nc-muted nc-small">'.new \Ease\Html\Widgets\LiveAge($after).'</span></div></td>'
            .'<td><span class="nc-tag">'.ScheduleLister::scheduleTypeLabel($type === null ? null : (string) $type).'</span></td>'
            .'<td><a href="app.php?id='.(int) $row['app_id'].'" class="nc-entity"><span class="nc-chip-ico">'.htmlspecialchars(self::initials($app)).'</span>'.htmlspecialchars($app).'</a></td>'
            .'<td><a href="runtemplate.php?id='.(int) $row['runtemplate_id'].'" class="nc-plain">⚗️&nbsp;'.htmlspecialchars($template).'</a></td>'
            .'<td><a href="company.php?id='.(int) $row['company_id'].'" class="nc-entity nc-plain"><span class="nc-chip-ico nc-round">'.htmlspecialchars(self::initials($company)).'</span>'.htmlspecialchars($company).'</a></td>'
            .'<td class="text-end nc-num"><a href="job.php?id='.(int) $row['job'].'" class="nc-muted">🏁&nbsp;#'.(int) $row['job'].'</a></td></tr>';
    }

    private static function dayLabel(string $date, \DateTimeImmutable $today): string
    {
        $day = new \DateTimeImmutable($date);
        $diff = (int) $today->diff($day)->format('%r%a');
        $formatted = class_exists(\IntlDateFormatter::class)
            ? (new \IntlDateFormatter(\Ease\Locale::$localeUsed ?? 'en_US', \IntlDateFormatter::FULL, \IntlDateFormatter::NONE, null, null, 'EEEE d. M.'))->format($day)
            : $day->format('D j. n.');

        if ($diff < 0) {
            return _('Overdue').' · '.$formatted;
        }

        if ($diff === 0) {
            return _('Today').' · '.$formatted;
        }

        if ($diff === 1) {
            return _('Tomorrow').' · '.$formatted;
        }

        return ucfirst((string) $formatted);
    }

    private static function initials(string $name): string
    {
        $letters = '';

        foreach (\array_slice(preg_split('/\s+/u', trim($name)) ?: [], 0, 2) as $word) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $letters === '' ? '?' : $letters;
    }
}
