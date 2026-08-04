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

/**
 * RunTemplate Statistics Cards Component.
 *
 * Displays key metrics and statistics for a RunTemplate
 *
 * @author vitex
 */
class RunTemplateStatsCards extends \Ease\Html\DivTag
{
    private \MultiFlexi\RunTemplate $runtemplate;
    private array $stats;

    public function __construct(\MultiFlexi\RunTemplate $runtemplate)
    {
        $this->runtemplate = $runtemplate;
        $this->stats = $this->calculateStats();
        parent::__construct(null);

        // ── Metric Cards Row ──
        $metricsRow = new \Ease\TWB5\Row();
        $metricsRow->addTagClass('g-3 mb-4');

        $successColor = $this->stats['success_rate'] >= 80 ? 'success' : ($this->stats['success_rate'] >= 50 ? 'warning' : 'danger');

        $metricsRow->addColumn(3, self::metricCard(_('Total Jobs'), number_format($this->stats['total_jobs']), 'primary', '📊'));
        $metricsRow->addColumn(3, self::metricCard(_('Success Rate'), $this->stats['success_rate'].'%', $successColor, '✅'));
        $metricsRow->addColumn(3, self::metricCard(_('Failed'), number_format($this->stats['failed_jobs']), 'danger', '❌'));
        $metricsRow->addColumn(3, self::metricCard(_('Running'), number_format($this->stats['running_jobs']), 'info', '🔄'));
        $metricsRow->addColumn(3, self::linkedMetricCard(
            _('Tasks'),
            number_format($this->stats['task_count']),
            'secondary',
            '📋',
            'tasks.php?runtemplate_id='.$this->runtemplate->getMyKey(),
        ));

        $this->addItem($metricsRow);

        // ── Info Cards Row ──
        $infoRow = new \Ease\TWB5\Row();
        $infoRow->addTagClass('g-3 mb-4');

        // Schedule card
        $scheduleCard = self::infoCard(_('Schedule'));
        $interval = (string) $this->runtemplate->getDataValue('interv');
        $scheduleCard->addItem(self::infoLine(_('Interval'), self::formatInterval($interval)));

        if ($cron = (string) $this->runtemplate->getDataValue('cron')) {
            $scheduleCard->addItem(self::infoLine(_('Cron'), new \Ease\Html\PairTag('code', [], $cron)));
        }

        $delay = (int) ($this->runtemplate->getDataValue('delay') ?? 0);

        if ($delay > 0) {
            $scheduleCard->addItem(self::infoLine(_('Delay'), self::formatDuration($delay)));
        }

        $executor = (string) $this->runtemplate->getDataValue('executor');
        $scheduleCard->addItem(self::infoLine(_('Executor'), $executor ?: 'Native'));
        $infoRow->addColumn(4, $scheduleCard);

        // Timing card
        $timingCard = self::infoCard(_('Timing'));

        if ($this->stats['last_run']) {
            $lastRunDate = new \DateTime($this->stats['last_run']);
            $timingCard->addItem(self::infoLine(_('Last Run'), $lastRunDate->format('Y-m-d H:i').' ('.self::formatAge($lastRunDate).')'));
        } else {
            $timingCard->addItem(self::infoLine(_('Last Run'), _('Never')));
        }

        // Calculate next scheduled run from interval/cron + delay
        $nextRunLabel = self::calculateNextRun($interval, (string) $this->runtemplate->getDataValue('cron'), (int) ($this->runtemplate->getDataValue('delay') ?? 0));

        if ($nextRunLabel) {
            $timingCard->addItem(self::infoLine(_('Next Run'), $nextRunLabel));
        } elseif ($interval === 'n') {
            $timingCard->addItem(self::infoLine(_('Next Run'), _('Manual only')));
        }

        $infoRow->addColumn(4, $timingCard);

        // Lifecycle card
        $lifecycleCard = self::infoCard(_('Lifecycle'));
        $createdRaw = (string) $this->runtemplate->getDataValue($this->runtemplate->createColumn);
        $updatedRaw = (string) $this->runtemplate->getDataValue($this->runtemplate->lastModifiedColumn);

        if ($createdRaw) {
            $cd = new \DateTime($createdRaw);
            $lifecycleCard->addItem(self::infoLine(_('Created'), $cd->format('Y-m-d').' ('.self::formatAge($cd).')'));
        }

        if ($updatedRaw) {
            $ud = new \DateTime($updatedRaw);
            $lifecycleCard->addItem(self::infoLine(_('Updated'), $ud->format('Y-m-d').' ('.self::formatAge($ud).')'));
        }

        $lifecycleCard->addItem(self::infoLine(_('Successful'), number_format($this->stats['successful_jobs'])));
        $lifecycleCard->addItem(self::infoLine(_('Failed'), number_format($this->stats['failed_jobs'])));
        $infoRow->addColumn(4, $lifecycleCard);

        $this->addItem($infoRow);

        // ── Job Visualization + Chart ──
        $chartsRow = new \Ease\TWB5\Row();
        $chartsRow->addTagClass('mb-3');
        $chartsRow->addColumn(8, new \MultiFlexi\Ui\RunTemplateJobsLastMonthChart($this->runtemplate, ['style' => 'width: 100%;']));
        $chartsRow->addColumn(4, new \MultiFlexi\Ui\JobGraphWidget($this->runtemplate, 20, 10));
        $this->addItem($chartsRow);
    }

    private static function metricCard(string $label, string $value, string $context, string $icon): \Ease\Html\DivTag
    {
        $card = new \Ease\Html\DivTag(null, ['class' => 'card border-0 shadow-sm h-100']);
        $body = new \Ease\Html\DivTag(null, ['class' => 'card-body py-3']);
        $body->addItem(new \Ease\Html\DivTag(
            [$icon, ' ', new \Ease\Html\SpanTag($label, ['class' => 'text-muted'])],
            ['class' => 'small mb-1'],
        ));
        $body->addItem(new \Ease\Html\DivTag($value, [
            'class' => 'text-'.$context,
            'style' => 'font-size: 1.75rem; font-weight: 700; line-height: 1.2;',
        ]));
        $card->addItem($body);

        return $card;
    }

    private static function linkedMetricCard(string $label, string $value, string $context, string $icon, string $href): \Ease\Html\DivTag
    {
        $card = new \Ease\Html\DivTag(null, ['class' => 'card border-0 shadow-sm h-100']);
        $body = new \Ease\Html\DivTag(null, ['class' => 'card-body py-3']);
        $body->addItem(new \Ease\Html\DivTag(
            [$icon, ' ', new \Ease\Html\SpanTag($label, ['class' => 'text-muted'])],
            ['class' => 'small mb-1'],
        ));
        $body->addItem(new \Ease\Html\ATag($href, $value, [
            'class' => 'text-'.$context.' text-decoration-none',
            'style' => 'font-size: 1.75rem; font-weight: 700; line-height: 1.2; display: block;',
        ]));
        $card->addItem($body);

        return $card;
    }

    private static function infoCard(string $title): \Ease\Html\DivTag
    {
        $body = new \Ease\Html\DivTag(null, ['class' => 'card border-0 shadow-sm h-100']);
        $inner = $body->addItem(new \Ease\Html\DivTag(null, ['class' => 'card-body py-3']));
        $inner->addItem(new \Ease\Html\H6Tag($title, ['class' => 'card-title text-muted text-uppercase small fw-bold mb-3']));

        return $inner;
    }

    private static function infoLine(string $label, $value): \Ease\Html\DivTag
    {
        $line = new \Ease\Html\DivTag(null, ['class' => 'd-flex justify-content-between align-items-center mb-2']);
        $line->addItem(new \Ease\Html\SpanTag($label, ['class' => 'text-muted small']));
        $line->addItem(new \Ease\Html\SpanTag($value, ['class' => 'fw-semibold small']));

        return $line;
    }

    private static function formatInterval(string $code): string
    {
        $map = [
            'n' => _('Manual'),
            'h' => _('Hourly'),
            'd' => _('Daily'),
            'w' => _('Weekly'),
            'm' => _('Monthly'),
            'c' => _('Custom (cron)'),
        ];

        return $map[$code] ?? $code;
    }

    private static function formatDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        $parts = [];

        if ($h) {
            $parts[] = $h.'h';
        }

        if ($m) {
            $parts[] = $m.'m';
        }

        if ($s) {
            $parts[] = $s.'s';
        }

        return implode(' ', $parts);
    }

    private static function formatAge(\DateTime $date): string
    {
        $diff = (new \DateTime('now'))->diff($date);
        $parts = [];

        if ($diff->y) {
            $parts[] = $diff->y.'y';
        }

        if ($diff->m) {
            $parts[] = $diff->m.'mo';
        }

        if ($diff->d) {
            $parts[] = $diff->d.'d';
        }

        if (!$parts && $diff->h) {
            $parts[] = $diff->h.'h';
        }

        if (!$parts && $diff->i) {
            $parts[] = $diff->i.'m';
        }

        return $parts ? implode(' ', $parts) : _('just now');
    }

    /**
     * Calculate the next scheduled run time from interval code or cron expression.
     * Mirrors the scheduler logic: cron next run + delay seconds offset.
     */
    private static function calculateNextRun(string $interval, string $cron, int $delay = 0): ?string
    {
        // Map interval codes to cron expressions (same as Scheduler::$intervCron)
        $intervalCronMap = [
            'i' => '* * * * *',
            'h' => '0 * * * *',
            'd' => '0 0 * * *',
            'w' => '0 0 * * 1',
            'm' => '0 0 1 * *',
            'y' => '0 0 1 1 *',
        ];

        $cronExpr = '';

        if ($interval === 'c' && !empty($cron)) {
            $cronExpr = $cron;
        } elseif (isset($intervalCronMap[$interval])) {
            $cronExpr = $intervalCronMap[$interval];
        }

        if (empty($cronExpr)) {
            return null;
        }

        try {
            $expression = new \Cron\CronExpression($cronExpr);
            $nextRun = $expression->getNextRunDate();

            // Apply startup delay (same as CronScheduler: modify('+N seconds'))
            if ($delay > 0) {
                $nextRun->modify('+'.$delay.' seconds');
            }

            return $nextRun->format('Y-m-d H:i');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function calculateStats(): array
    {
        $rtId = $this->runtemplate->getMyKey();

        // One aggregation query instead of six.
        // CASE WHEN ... THEN 1 END (no ELSE) works on MySQL, PostgreSQL and SQLite:
        // COUNT() ignores NULLs, so the missing ELSE NULL is intentional.
        // "end" as a column name inside the WHEN condition is unambiguous to all three
        // parsers: the CASE-closing END can only appear after a THEN-result, not inside
        // the boolean condition — the same reason it already works in WHERE clauses.
        $row = $this->runtemplate->getFluentPDO(true)
            ->from('job')
            ->select([
                'COUNT(*) AS total_jobs',
                'COUNT(CASE WHEN exitcode = 0 THEN 1 END) AS successful_jobs',
                'COUNT(CASE WHEN exitcode IS NOT NULL AND exitcode <> 0 THEN 1 END) AS failed_jobs',
                'COUNT(CASE WHEN begin IS NOT NULL AND end IS NULL THEN 1 END) AS running_jobs',
                'MAX(begin) AS last_run',
            ], true)
            ->where('runtemplate_id', $rtId)
            ->fetch();

        $totalJobs      = (int) ($row['total_jobs']     ?? 0);
        $successfulJobs = (int) ($row['successful_jobs'] ?? 0);
        $failedJobs     = (int) ($row['failed_jobs']    ?? 0);
        $runningJobs    = (int) ($row['running_jobs']   ?? 0);
        $successRate    = $totalJobs > 0 ? round(($successfulJobs / $totalJobs) * 100, 1) : 0;
        $lastRun        = $row['last_run'] ?? null;

        $taskCount = 0;

        try {
            $tasker    = new \MultiFlexi\Task();
            $taskCount = (int) $tasker->listingQuery()->where('runtemplate_id', $rtId)->count();
        } catch (\Exception) {
        }

        return [
            'total_jobs'     => $totalJobs,
            'successful_jobs' => $successfulJobs,
            'failed_jobs'    => $failedJobs,
            'running_jobs'   => $runningJobs,
            'success_rate'   => $successRate,
            'last_run'       => $lastRun,
            'task_count'     => $taskCount,
        ];
    }
}
