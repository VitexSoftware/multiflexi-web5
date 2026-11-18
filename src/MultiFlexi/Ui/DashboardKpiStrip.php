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
 * Header of the dashboard: kicker, title and four KPIs of the last 30 days.
 *
 * @no-named-arguments
 */
class DashboardKpiStrip extends \Ease\Html\DivTag
{
    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     * @param string               $lead       Raw HTML replacing the kicker + title block (e.g. a company header)
     */
    public function __construct(DashboardData $data, array $properties = [], string $lead = '')
    {
        $kpis = $data->scopedStats()->kpis();
        $rate = $kpis['successRate'];
        $items = [
            [_('Runs'), number_format($kpis['runs'], 0, ',', "\u{a0}"), ''],
            [_('Success rate'), $rate === null ? '—' : $rate."\u{a0}%", ''],
            [_('Failures'), (string) $kpis['failures'], $kpis['failures'] > 0 ? 'nc-fail-text' : ''],
            [_('Queued'), (string) $data->queueCount(), ''],
        ];

        $html = ($lead !== '' ? $lead : '<div><div class="nc-kicker">'.htmlspecialchars(_('Overview')).'</div>'
            .'<h1 class="nc-title">'.htmlspecialchars(_('Jobs in the last 30 days')).'</h1></div>')
            .'<div class="nc-kpis">';

        foreach ($items as [$label, $value, $class]) {
            $html .= '<div class="nc-kpi"><span class="nc-kpi-label">'.htmlspecialchars($label).'</span>'
                .'<span class="nc-kpi-value '.$class.'">'.htmlspecialchars($value).'</span></div>';
        }

        parent::__construct($html.'</div>', $properties + ['class' => 'nc-header']);
    }
}
