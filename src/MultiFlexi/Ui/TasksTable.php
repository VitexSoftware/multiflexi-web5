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
 * DataTables-powered task listing with state row colours and state filter.
 */
class TasksTable extends \Ease\Html\DivTag
{
    private static array $stateBadge = [
        'fulfilled' => ['success', '✅'],
        'fulfilled_late' => ['warning', '⏰'],
        'failed' => ['danger', '❌'],
        'missed' => ['secondary', '🕳️'],
        'open' => ['info', '🔵'],
        'running' => ['primary', '▶️'],
    ];
    /**
     * Row tint per state. Bootstrap's table-* variants keep a fixed light
     * background even under data-bs-theme="dark", so rows are tinted with the
     * theme-aware *-bg-subtle colours instead (see self::rowCss()).
     */
    private static array $rowClass = [
        'fulfilled' => 'task-row-success',
        'fulfilled_late' => 'task-row-warning',
        'failed' => 'task-row-danger',
        'missed' => 'task-row-secondary',
        'open' => '',
        'running' => 'task-row-info',
    ];

    /**
     * @param array<int, string> $appTags show only tasks of applications having all these tags
     */
    public function __construct(?string $stateFilter = null, ?int $runtemplateId = null, array $appTags = [])
    {
        parent::__construct(null, ['id' => 'tasks-table-wrapper']);

        try {
            $tasker = new \MultiFlexi\Task();

            $query = $tasker->getFluentPDO()
                ->from('task')
                ->select(<<<'EOD'
task.id, task.state, task.window_start, task.window_end, task.deadline,
                     task.attempts, task.fulfilled_at, task.fulfilled_by_job_id,
                     task.runtemplate_id,
                     runtemplate.name as rt_name,
                     company.id as company_id, company.name as company_name,
                     apps.name as app_name
EOD,)
                ->leftJoin('runtemplate ON runtemplate.id = task.runtemplate_id')
                ->leftJoin('company ON company.id = runtemplate.company_id')
                ->leftJoin('apps ON apps.id = runtemplate.app_id')
                ->orderBy('task.id DESC')
                ->limit(500);

            if (!empty($stateFilter) && $stateFilter !== 'all') {
                if ($stateFilter === 'open') {
                    $query->where("task.state IN ('open','running')");
                } else {
                    $query->where('task.state', $stateFilter);
                }
            }

            if ($runtemplateId !== null) {
                $query->where('task.runtemplate_id', $runtemplateId);
            }

            if ($appTags !== []) {
                $appIds = \MultiFlexi\AppTags::appIdsWithAll($appTags);
                $query->where($appIds === [] ? '1=0' : 'runtemplate.app_id IN ('.implode(',', $appIds).')');
            }

            $tasks = $query->fetchAll();

            if (empty($tasks)) {
                $this->addItem(new \Ease\TWB5\Alert('info', _('No tasks match the current filter.')));

                return;
            }

            $table = new \Ease\TWB5\Table(null, ['id' => 'tasks-dt', 'class' => 'table table-bordered table-hover table-sm w-100']);
            $table->addRowHeaderColumns([
                _('ID'),
                _('RunTemplate'),
                _('Company / App'),
                _('Window'),
                _('Deadline'),
                _('State'),
                _('Att.'),
                _('Fulfilled by'),
            ]);

            foreach ($tasks as $task) {
                $state = $task['state'];
                $rowCls = self::$rowClass[$state] ?? '';
                [$badgeType, $icon] = self::$stateBadge[$state] ?? ['secondary', '?'];

                // RunTemplate link
                if ($task['runtemplate_id'] && $task['rt_name']) {
                    $rtLink = new \Ease\Html\ATag('runtemplate.php?id='.$task['runtemplate_id'], '⚗️ '.htmlspecialchars($task['rt_name']));
                } elseif ($task['runtemplate_id']) {
                    $rtLink = new \Ease\Html\SpanTag('#'.$task['runtemplate_id'], ['class' => 'text-muted']);
                } else {
                    $rtLink = '—';
                }

                // Company / App
                $parts = [];

                if ($task['company_name']) {
                    $parts[] = '🏢 '.htmlspecialchars($task['company_name']);
                }

                if ($task['app_name']) {
                    $parts[] = '🧩 '.htmlspecialchars($task['app_name']);
                }

                $companyApp = $parts ? implode(' / ', $parts) : '—';

                // Window
                $ws = $task['window_start'] ? (new \DateTime($task['window_start']))->format('m-d H:i') : '—';
                $we = $task['window_end'] ? (new \DateTime($task['window_end']))->format('m-d H:i') : '—';
                $window = new \Ease\Html\SpanTag($ws.' → '.$we, ['class' => 'text-nowrap small']);

                // Deadline
                $dl = $task['deadline'] ? (new \DateTime($task['deadline']))->format('m-d H:i') : '—';
                $dlSpan = new \Ease\Html\SpanTag($dl, ['class' => 'small'.(\in_array($state, ['failed', 'missed'], true) ? ' text-danger fw-bold' : '')]);

                // State badge
                $badge = new \Ease\TWB5\Badge($icon.' '._($state), $badgeType);

                // Fulfilled by Job
                if ($task['fulfilled_by_job_id']) {
                    $fulfilledAt = $task['fulfilled_at'] ? (new \DateTime($task['fulfilled_at']))->format('H:i:s') : '';
                    $fulfilledCell = new \Ease\Html\DivTag([
                        new \Ease\Html\ATag('job.php?id='.$task['fulfilled_by_job_id'], '🏁 #'.$task['fulfilled_by_job_id']),
                        new \Ease\Html\SpanTag(' '.$fulfilledAt, ['class' => 'text-muted small d-block']),
                    ]);
                } else {
                    $fulfilledCell = '—';
                }

                // Add row with class
                $tr = new \Ease\Html\TrTag(null, ['class' => $rowCls]);
                $tr->addItem(new \Ease\Html\TdTag(new \Ease\Html\ATag('task.php?id='.$task['id'], '#'.$task['id'])));
                $tr->addItem(new \Ease\Html\TdTag($rtLink));
                $tr->addItem(new \Ease\Html\TdTag($companyApp));
                $tr->addItem(new \Ease\Html\TdTag($window));
                $tr->addItem(new \Ease\Html\TdTag($dlSpan));
                $tr->addItem(new \Ease\Html\TdTag($badge));
                $tr->addItem(new \Ease\Html\TdTag((string) $task['attempts'], ['class' => 'text-center']));
                $tr->addItem(new \Ease\Html\TdTag($fulfilledCell));
                $table->addItem($tr);
            }

            $this->addItem($table);

            WebPage::singleton()->addCss(self::rowCss());

            WebPage::singleton()->addJavaScript(<<<'JS'

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        if ($.fn.DataTable.isDataTable('#tasks-dt')) return;
        $('#tasks-dt').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            columnDefs: [{ orderable: false, targets: [5] }]
        });
    });
})();

JS);
        } catch (\Exception $e) {
            $this->addItem(new \Ease\TWB5\Alert('danger', _('Error loading tasks: ').$e->getMessage()));
        }
    }

    /**
     * State tints that follow the active colour theme; --bs-table-bg-type keeps
     * Bootstrap's hover highlight working on top of the tint.
     */
    private static function rowCss(): string
    {
        $css = '';

        foreach (['success', 'warning', 'danger', 'secondary', 'info'] as $variant) {
            $css .= '#tasks-dt tr.task-row-'.$variant.' > * { --bs-table-bg-type: var(--bs-'.$variant.'-bg-subtle); }'."\n";
        }

        return $css;
    }
}
