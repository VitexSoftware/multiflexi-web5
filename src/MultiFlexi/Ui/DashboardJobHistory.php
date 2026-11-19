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
 * Job history spiral (jobgraph.php, 1 px = 1 job) with a legend including counts.
 *
 * @no-named-arguments
 */
class DashboardJobHistory extends \Ease\Html\DivTag
{
    /**
     * Side of the spiral image in pixels; the newest jobs that fit the spiral are shown.
     */
    private const SIDE = 100;

    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(DashboardData $data, array $properties = [])
    {
        $params = ['width' => self::SIDE, 'height' => self::SIDE];

        if ($data->getCompanyId() !== null) {
            $params['company_id'] = $data->getCompanyId();
        }

        // Count exactly the jobs drawn: the spiral holds fewer than SIDE * SIDE
        $counts = $data->historyCounts(JobGraph::spiralCapacity(self::SIDE, self::SIDE));
        $total = array_sum($counts);

        $legend = [
            ['success', _('Success (0)'), 'rgb(110,183,135)'],
            ['fail', _('Failed'), 'rgb(214,104,98)'],
            ['noexec', _('Not runnable (127)'), 'rgb(214,190,110)'],
            ['exception', _('Exception (255)'), 'rgb(145,132,217)'],
            ['waiting', _('Waiting'), 'rgb(147,151,171)'],
        ];
        $legendHtml = '';

        foreach ($legend as [$key, $label, $color]) {
            $legendHtml .= '<span class="nc-legend-item"><i class="nc-dot" style="background:'.$color.'"></i>'
                .'<span class="nc-grow">'.htmlspecialchars($label).'</span><span class="nc-muted nc-num">'.number_format($counts[$key], 0, ',', "\u{a0}").'</span></span>';
        }

        $title = _('Each pixel is one job, the oldest in the centre, newer ones spiral outwards');

        parent::__construct(
            '<div class="nc-section-head"><h2 class="nc-h2">'.htmlspecialchars(_('Job history')).'</h2>'
            .'<span class="nc-muted nc-num">'.htmlspecialchars(sprintf(_('%s latest jobs'), number_format($total, 0, ',', "\u{a0}"))).'</span></div>'
            .'<div class="nc-spiral"><img src="jobgraph.php?'.htmlspecialchars(http_build_query($params)).'" alt="'.htmlspecialchars(_('Job History Graph')).'" title="'.htmlspecialchars($title).'">'
            .'<span class="nc-muted nc-small">'.htmlspecialchars(_('1 pixel = 1 job · oldest in the centre, newer ones spiral outwards')).'</span></div>'
            .'<div class="nc-legend nc-legend-grid">'.$legendHtml.'</div>',
            $properties + ['class' => 'nc-panel nc-panel-narrow'],
        );
    }
}
