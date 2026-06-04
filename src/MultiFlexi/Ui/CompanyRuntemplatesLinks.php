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
 * Description of CompanyRuntemplatesLinks.
 *
 * @author Vitex <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class CompanyRuntemplatesLinks extends \Ease\Html\DivTag
{
    public function __construct(\MultiFlexi\Company $company, \MultiFlexi\Application $application, array $properties = [])
    {
        $runTemplater = new \MultiFlexi\RunTemplate();
        $runtemplatesRaw = $runTemplater->listingQuery()->where('active', true)->where('app_id', $application->getMyKey())->where('company_id', $company->getMyKey());
        $jobber = new \MultiFlexi\Job();

        $runtemplatesDiv = new \Ease\Html\DivTag();

        if ($runtemplatesRaw->count()) {
            WebPage::singleton()->addCss(<<<'CSS'
                .runtemplate-compact-group .btn { padding: 0.1rem 0.4rem; font-size: 0.75rem; line-height: 1.5; }
                .runtemplate-compact-id { font-weight: 500; background-color: #f1f3f5; color: #495057; border-color: #dee2e6; }
                .runtemplate-compact-status { min-width: 24px; text-align: center; }
                .runtemplate-compact-queued { padding: 0.1rem 0.3rem !important; }
CSS);

            foreach ($runtemplatesRaw as $runtemplateData) {
                // Get last finished job
                $lastFinishedJob = $jobber->listingQuery()
                    ->select(['id', 'exitcode'], true)
                    ->where(['runtemplate_id' => $runtemplateData['id']])
                    ->where('exitcode IS NOT NULL')
                    ->order('id DESC')
                    ->limit(1)
                    ->fetch();

                // Check for queued jobs (begin is null)
                $queuedJob = $jobber->listingQuery()
                    ->select(['id', 'schedule'])
                    ->where(['runtemplate_id' => $runtemplateData['id'], 'begin' => null])
                    ->order('id DESC')
                    ->limit(1)
                    ->fetch();

                $group = new \Ease\Html\DivTag(null, ['class' => 'btn-group runtemplate-compact-group shadow-sm mr-2 mb-2', 'role' => 'group']);

                // RunTemplate Link
                $group->addItem(new \Ease\Html\ATag(
                    'runtemplate.php?id='.$runtemplateData['id'],
                    '⚗️ #'.$runtemplateData['id'],
                    [
                        'class' => 'btn runtemplate-compact-id',
                        'data-bs-toggle' => 'popover',
                        'data-bs-trigger' => 'hover focus',
                        'data-bs-html' => 'true',
                        'data-bs-placement' => 'top',
                        'data-bs-content' => $runtemplateData['name'],
                    ],
                ));

                // Exit Code / Last Status
                if ($lastFinishedJob) {
                    $group->addItem(new \Ease\Html\ATag(
                        'job.php?id='.$lastFinishedJob['id'],
                        new ExitCode($lastFinishedJob['exitcode'], ['class' => 'rounded-pill']),
                        [
                            'class' => 'btn btn-outline-secondary runtemplate-compact-status',
                            'data-bs-toggle' => 'popover',
                            'data-bs-trigger' => 'hover focus',
                            'data-bs-html' => 'true',
                            'data-bs-placement' => 'top',
                            'data-bs-content' => _('Last finished job exit code').': '.$lastFinishedJob['exitcode'],
                        ],
                    ));
                } else {
                    $group->addItem(new \Ease\Html\SpanTag('🪤', [
                        'class' => 'btn btn-outline-light disabled runtemplate-compact-status',
                        'data-bs-toggle' => 'popover',
                        'data-bs-trigger' => 'hover focus',
                        'data-bs-html' => 'true',
                        'data-bs-placement' => 'top',
                        'data-bs-content' => _('No jobs yet'),
                    ]));
                }

                // Queued Status (Hourglass)
                if ($queuedJob) {
                    $scheduledAt = new \DateTime($queuedJob['schedule']);
                    $now = new \DateTime();
                    $diff = $now->diff($scheduledAt);
                    $timeInfo = $diff->invert ? _('Late by') : _('Scheduled for');
                    $group->addItem(new \Ease\Html\ATag(
                        'job.php?id='.$queuedJob['id'],
                        '⌛',
                        [
                            'class' => 'btn btn-info runtemplate-compact-queued',
                            'data-bs-toggle' => 'popover',
                            'data-bs-trigger' => 'hover focus',
                            'data-bs-html' => 'true',
                            'data-bs-placement' => 'top',
                            'data-bs-content' => sprintf(_('%s: %s (in %s)'), _('Job is scheduled in queue'), $queuedJob['schedule'], self::secondsToHuman((int) abs($scheduledAt->getTimestamp() - $now->getTimestamp()))),
                        ],
                    ));
                }

                $runtemplatesDiv->addItem($group);
            }

            WebPage::singleton()->addJavaScript(<<<'JS'
                document.querySelectorAll('.runtemplate-compact-group [data-bs-toggle="popover"]').forEach(function (el) {
                    if (!bootstrap.Popover.getInstance(el)) {
                        new bootstrap.Popover(el, { container: 'body' });
                    }
                });
JS);
        } else {
            $runtemplatesDiv->addItem(new \Ease\Html\ATag('runtemplate.php?new=1&app_id='.$application->getMyKey().'&company_id='.$company->getMyKey(), '➕', ['class' => 'btn btn-outline-primary btn-sm rounded-circle']));
        }

        parent::__construct($runtemplatesDiv, $properties);
    }

    /**
     * Convert seconds to human readable string.
     */
    public static function secondsToHuman(int $seconds): string
    {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@{$seconds}");

        return $dtF->diff($dtT)->format('%ad %hh %im %ss');
    }

    public function count(): int
    {
        if (isset($this->pageParts[0]) && \is_object($this->pageParts[0]) && method_exists($this->pageParts[0], 'getItemsCount')) {
            return $this->pageParts[0]->getItemsCount();
        }

        return 0;
    }
}
