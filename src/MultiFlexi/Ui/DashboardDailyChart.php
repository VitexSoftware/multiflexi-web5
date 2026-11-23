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

/**
 * Stacked bars of job states per day (last 30 days) as plain HTML/CSS.
 *
 * @no-named-arguments
 */
class DashboardDailyChart extends \Ease\Html\DivTag
{
    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(DashboardData $data, array $properties = [])
    {
        $days = $data->scopedStats()->daily();
        $max = 0;

        foreach ($days as $counts) {
            $max = max($max, array_sum($counts));
        }

        $step = self::niceStep($max);
        $top = $step * 4;

        $axis = '';

        for ($i = 4; $i >= 0; --$i) {
            $axis .= '<span>'.($i * $step).'</span>';
        }

        $bars = '';
        $labels = '';
        $n = 0;

        foreach ($days as $date => $counts) {
            $day = new \DateTimeImmutable($date);
            $title = sprintf(
                '%s — %s %d, %s %d, %s %d, %s %d',
                $day->format('j. n.'),
                _('success'),
                $counts['success'],
                _('failed'),
                $counts['fail'],
                _('waiting'),
                $counts['waiting'],
                _('exception'),
                $counts['exception'],
            );
            $pct = static fn (int $v): string => number_format($v / $top * 100, 2, '.', '').'%';
            $bars .= '<a class="nc-bar-col" href="jobs.php?filter='.rawurlencode('day:'.$date).'" title="'.htmlspecialchars($title).'">'
                .'<div class="nc-seg nc-seg-fail" style="height:'.$pct($counts['fail']).'"></div>'
                .'<div class="nc-seg nc-seg-ok" style="height:'.$pct($counts['success']).'"></div>'
                .'<div class="nc-seg nc-seg-exc" style="height:'.$pct($counts['exception']).'"></div>'
                .'<div class="nc-seg nc-seg-wait" style="height:'.$pct($counts['waiting']).'"></div></a>';
            $labels .= '<span>'.($n % 5 === 0 ? $day->format('j. n.') : '').'</span>';
            ++$n;
        }

        $legend = [
            [_('Success'), 'var(--color-ok)'],
            [_('Failed'), 'var(--color-fail)'],
            [_('Waiting'), 'var(--color-neutral-600)'],
            [_('Exception'), 'var(--color-exception)'],
        ];
        $legendHtml = '';

        foreach ($legend as [$label, $color]) {
            $legendHtml .= '<span class="nc-legend-item"><i class="nc-dot" style="background:'.$color.'"></i>'.htmlspecialchars($label).'</span>';
        }

        parent::__construct(
            '<div class="nc-section-head"><h2 class="nc-h2">'.htmlspecialchars(_('Runs per day')).'</h2><span class="nc-muted">'.htmlspecialchars(_('last 30 days')).'</span></div>'
            .'<div class="nc-chart"><div class="nc-chart-axis">'.$axis.'</div>'
            .'<div class="nc-chart-main"><div class="nc-chart-plot">'.$bars.'</div><div class="nc-chart-labels">'.$labels.'</div></div></div>'
            .'<div class="nc-legend">'.$legendHtml.'</div>',
            $properties + ['class' => 'nc-panel nc-panel-wide'],
        );
    }

    /**
     * Axis step so that four steps cover the busiest day (1, 2, 5 × 10^n).
     */
    public static function niceStep(int $max): int
    {
        if ($max <= 4) {
            return 1;
        }

        $raw = $max / 4;
        $magnitude = 10 ** (int) floor(log10($raw));

        foreach ([1, 2, 5, 10] as $m) {
            if ($raw <= $m * $magnitude) {
                return (int) ($m * $magnitude);
            }
        }

        return (int) (10 * $magnitude);
    }
}
