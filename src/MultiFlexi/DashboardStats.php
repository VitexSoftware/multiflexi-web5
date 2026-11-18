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
 * Pure aggregation of job rows for the dashboard (no database access).
 *
 * Input rows are arrays with the keys `begin` (datetime string or null),
 * `exitcode` (int|string|null), `company_id` and `app_id`.
 *
 * @no-named-arguments
 */
final class DashboardStats
{
    public const STATE_SUCCESS = 'success';
    public const STATE_FAIL = 'fail';
    public const STATE_WAITING = 'waiting';
    public const STATE_EXCEPTION = 'exception';

    /**
     * @var array<string, array{success: int, fail: int, waiting: int, exception: int}>
     */
    private array $days = [];

    /**
     * @var array<int, array{success: int, fail: int, waiting: int, exception: int, apps: array<int, int>}>
     */
    private array $companies = [];

    /**
     * @var array{success: int, fail: int, waiting: int, exception: int}
     */
    private array $totals = ['success' => 0, 'fail' => 0, 'waiting' => 0, 'exception' => 0];

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param int                              $window Length of the window in days (today included)
     */
    public function __construct(
        array $rows,
        private readonly \DateTimeImmutable $today,
        private readonly int $window = 30,
    ) {
        for ($i = $this->window - 1; $i >= 0; --$i) {
            $this->days[$today->modify("-{$i} days")->format('Y-m-d')] = self::emptyCounts();
        }

        foreach ($rows as $row) {
            if (empty($row['begin'])) {
                continue;
            }

            $date = substr((string) $row['begin'], 0, 10);

            if (!\array_key_exists($date, $this->days)) {
                continue;
            }

            $state = self::classify($row['exitcode'] ?? null);
            ++$this->days[$date][$state];
            ++$this->totals[$state];

            $companyId = (int) ($row['company_id'] ?? 0);

            if (!isset($this->companies[$companyId])) {
                $this->companies[$companyId] = self::emptyCounts() + ['apps' => []];
            }

            ++$this->companies[$companyId][$state];

            if ($state === self::STATE_FAIL || $state === self::STATE_EXCEPTION) {
                $appId = (int) ($row['app_id'] ?? 0);
                $this->companies[$companyId]['apps'][$appId] = ($this->companies[$companyId]['apps'][$appId] ?? 0) + 1;
            }
        }
    }

    /**
     * Map an exit code to a dashboard state.
     *
     * @param null|int|string $exitcode
     */
    public static function classify($exitcode): string
    {
        if ($exitcode === null || $exitcode === '' || (int) $exitcode === -1) {
            return self::STATE_WAITING;
        }

        $code = (int) $exitcode;

        if ($code === 0) {
            return self::STATE_SUCCESS;
        }

        if ($code === 255) {
            return self::STATE_EXCEPTION;
        }

        return self::STATE_FAIL;
    }

    /**
     * Per-day counts, oldest first, keyed by Y-m-d (every day of the window is present).
     *
     * @return array<string, array{success: int, fail: int, waiting: int, exception: int}>
     */
    public function daily(): array
    {
        return $this->days;
    }

    /**
     * @return array{runs: int, successRate: null|int, failures: int}
     */
    public function kpis(): array
    {
        $runs = array_sum($this->totals);
        $finished = $this->totals['success'] + $this->totals['fail'] + $this->totals['exception'];

        return [
            'runs' => $runs,
            // Waiting jobs are not finished yet, so they do not count against the rate
            'successRate' => $finished > 0 ? (int) round($this->totals['success'] / $finished * 100) : null,
            'failures' => $this->totals['fail'] + $this->totals['exception'],
        ];
    }

    /**
     * Counts for one company (zeros when it has no jobs in the window).
     *
     * @return array{success: int, fail: int, waiting: int, exception: int, apps: array<int, int>}
     */
    public function company(int $companyId): array
    {
        return $this->companies[$companyId] ?? (self::emptyCounts() + ['apps' => []]);
    }

    /**
     * @return array{success: int, fail: int, waiting: int, exception: int}
     */
    private static function emptyCounts(): array
    {
        return ['success' => 0, 'fail' => 0, 'waiting' => 0, 'exception' => 0];
    }
}
