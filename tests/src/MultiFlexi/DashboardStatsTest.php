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

namespace MultiFlexi\Test;

use MultiFlexi\DashboardStats;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MultiFlexi\DashboardStats
 */
class DashboardStatsTest extends TestCase
{
    private \DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new \DateTimeImmutable('2026-11-18');
    }

    public function testClassify(): void
    {
        $this->assertSame('success', DashboardStats::classify(0));
        $this->assertSame('success', DashboardStats::classify('0'));
        $this->assertSame('exception', DashboardStats::classify(255));
        $this->assertSame('waiting', DashboardStats::classify(null));
        $this->assertSame('waiting', DashboardStats::classify(-1));
        $this->assertSame('fail', DashboardStats::classify(1));
        $this->assertSame('fail', DashboardStats::classify(127));
    }

    public function testEmptyWindowHas30Days(): void
    {
        $stats = new DashboardStats([], $this->today);
        $days = $stats->daily();

        $this->assertCount(30, $days);
        $this->assertSame('2026-10-20', array_key_first($days));
        $this->assertSame('2026-11-18', array_key_last($days));
        $this->assertSame(['runs' => 0, 'successRate' => null, 'failures' => 0], $stats->kpis());
    }

    public function testAggregation(): void
    {
        $rows = [
            ['begin' => '2026-11-18 10:00:00', 'exitcode' => 0, 'company_id' => 1, 'app_id' => 5],
            ['begin' => '2026-11-18 11:00:00', 'exitcode' => 0, 'company_id' => 1, 'app_id' => 5],
            ['begin' => '2026-11-18 12:00:00', 'exitcode' => 1, 'company_id' => 2, 'app_id' => 7],
            ['begin' => '2026-11-17 09:00:00', 'exitcode' => 255, 'company_id' => 2, 'app_id' => 7],
            ['begin' => '2026-11-17 09:30:00', 'exitcode' => null, 'company_id' => 2, 'app_id' => 8],
            ['begin' => '2026-09-01 09:30:00', 'exitcode' => 1, 'company_id' => 2, 'app_id' => 8], // outside window
            ['begin' => null, 'exitcode' => 1, 'company_id' => 2, 'app_id' => 8], // never started
        ];
        $stats = new DashboardStats($rows, $this->today);

        $this->assertSame(['runs' => 5, 'successRate' => 50, 'failures' => 2], $stats->kpis());
        $this->assertSame(['success' => 2, 'fail' => 1, 'waiting' => 0, 'exception' => 0], $stats->daily()['2026-11-18']);
        $this->assertSame(1, $stats->daily()['2026-11-17']['exception']);
        $this->assertSame(1, $stats->daily()['2026-11-17']['waiting']);
    }

    public function testCompanyBreakdown(): void
    {
        $rows = [
            ['begin' => '2026-11-18 10:00:00', 'exitcode' => 0, 'company_id' => 1, 'app_id' => 5],
            ['begin' => '2026-11-18 12:00:00', 'exitcode' => 1, 'company_id' => 2, 'app_id' => 7],
            ['begin' => '2026-11-18 13:00:00', 'exitcode' => 1, 'company_id' => 2, 'app_id' => 7],
        ];
        $stats = new DashboardStats($rows, $this->today);

        $this->assertSame(1, $stats->company(1)['success']);
        $this->assertSame([7 => 2], $stats->company(2)['apps']);
        $this->assertSame(0, $stats->company(99)['success']);
        $this->assertSame([], $stats->company(99)['apps']);
    }
}
