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

namespace MultiFlexi;

/**
 * Database access for the main dashboard (main.php).
 *
 * Everything is limited to the companies the current user may access (RBAC),
 * and optionally narrowed to one company chosen in the company grid.
 *
 * @no-named-arguments
 */
class DashboardData
{
    public const QUEUE_LIMIT = 200;
    private const WINDOW_DAYS = 30;
    private ?DashboardStats $stats = null;

    /**
     * @var null|array<int, array<string, mixed>>
     */
    private ?array $companies = null;
    private Job $jobber;

    /**
     * @param null|int $companyId Narrow everything to this company (must be accessible)
     */
    public function __construct(private readonly ?int $companyId = null)
    {
        $this->jobber = new Job();
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    /**
     * Company IDs in scope: all accessible ones, or just the selected one.
     *
     * @return array<int, int>
     */
    public function scopeCompanyIds(): array
    {
        $accessible = array_map('intval', \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies());

        if ($this->companyId !== null) {
            return \in_array($this->companyId, $accessible, true) ? [$this->companyId] : [];
        }

        return $accessible;
    }

    /**
     * Companies the user may access (card grid is never narrowed by the filter).
     *
     * @return array<int, array<string, mixed>>
     */
    public function companies(): array
    {
        if ($this->companies === null) {
            $accessible = array_map('intval', \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies());

            if ($accessible === []) {
                $this->companies = [];
            } else {
                $this->companies = (new Company())->listingQuery()
                    ->where('id IN ('.implode(',', $accessible).')')
                    ->fetchAll();
            }
        }

        return $this->companies;
    }

    /**
     * Statistics for all accessible companies over the last 30 days.
     *
     * The card grid shows every company, so the company breakdown always
     * comes from the unfiltered stats; {@see self::scopedStats()} is the
     * narrowed variant for the KPIs and the daily chart.
     */
    public function stats(): DashboardStats
    {
        if ($this->stats === null) {
            $this->stats = new DashboardStats($this->jobRows(array_map('intval', \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies())), new \DateTimeImmutable('today'), self::WINDOW_DAYS);
        }

        return $this->stats;
    }

    public function scopedStats(): DashboardStats
    {
        if ($this->companyId === null) {
            return $this->stats();
        }

        return new DashboardStats($this->jobRows($this->scopeCompanyIds()), new \DateTimeImmutable('today'), self::WINDOW_DAYS);
    }

    /**
     * Number of apps assigned to a company.
     */
    public function appCount(int $companyId): int
    {
        $company = new Company($companyId);

        return \count((new CompanyApp($company))->getAssigned()->fetchAll());
    }

    /**
     * Application names by ID.
     *
     * @param array<int, int> $appIds
     *
     * @return array<int, string>
     */
    public function appNames(array $appIds): array
    {
        if ($appIds === []) {
            return [];
        }

        $names = [];

        foreach ($this->jobber->getFluentPDO(true)->from('apps')->select(['id', 'name'], true)->where('id', array_map('intval', $appIds))->fetchAll() as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * Counts of the newest $limit jobs by state (legend of the job history spiral).
     *
     * @return array{success: int, fail: int, noexec: int, exception: int, waiting: int}
     */
    public function historyCounts(int $limit): array
    {
        $counts = ['success' => 0, 'fail' => 0, 'noexec' => 0, 'exception' => 0, 'waiting' => 0];
        $ids = $this->scopeCompanyIds();

        if ($ids === []) {
            return $counts;
        }

        $rows = $this->jobber->listingQuery()->select('exitcode', true)->where('company_id', $ids)->orderBy('id DESC')->limit($limit)->fetchAll();

        foreach ($rows as $row) {
            $exitcode = $row['exitcode'];

            if ($exitcode === null) {
                ++$counts['waiting'];
            } elseif ((int) $exitcode === 0) {
                ++$counts['success'];
            } elseif ((int) $exitcode === 127) {
                ++$counts['noexec'];
            } elseif ((int) $exitcode === 255) {
                ++$counts['exception'];
            } else {
                ++$counts['fail'];
            }
        }

        return $counts;
    }

    /**
     * Upcoming scheduled jobs (soonest first), narrowed to the selected company.
     *
     * @return array<int, array<string, mixed>>
     */
    public function queueRows(): array
    {
        $query = (new ScheduleLister())->listingQuery()->orderBy('schedule.after ASC')->limit(self::QUEUE_LIMIT);

        if ($this->companyId !== null) {
            $query->where('runtemplate.company_id', $this->companyId);
        }

        return $query->fetchAll();
    }

    /**
     * Total number of queued jobs in scope.
     */
    public function queueCount(): int
    {
        $query = (new ScheduleLister())->listingQuery()->select(null)->select('COUNT(*) AS cnt');

        if ($this->companyId !== null) {
            $query->where('runtemplate.company_id', $this->companyId);
        }

        $row = $query->fetch();

        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * @param array<int, int> $companyIds
     *
     * @return array<int, array<string, mixed>>
     */
    private function jobRows(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $since = (new \DateTimeImmutable('today'))->modify('-'.(self::WINDOW_DAYS - 1).' days')->format('Y-m-d');

        return $this->jobber->getFluentPDO(true)
            ->from('job')
            ->select(['begin', 'exitcode', 'company_id', 'app_id'], true)
            ->where('begin >= ?', $since)
            ->where('job.company_id IN ('.implode(',', $companyIds).')')
            ->order('begin')
            ->fetchAll();
    }
}
