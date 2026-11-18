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
 * Description of JobChart.
 *
 * author vitex
 *
 * @no-named-arguments
 */
class JobChart extends \Ease\Html\DivTag
{
    protected \Ease\SQL\Engine $engine;

    public function __construct(\Ease\SQL\Engine $engine, $properties = [])
    {
        $this->engine = $engine;
        $allJobs = $this->getJobs()->fetchAll();
        $days = [];

        // Pre-populate all 30 days so days without jobs show as empty columns
        $today = new \DateTimeImmutable('today');

        for ($i = 29; $i >= 0; --$i) {
            $date = $today->modify("-{$i} days")->format('Y-m-d');
            $days[$date] = ['success' => 0, 'waiting' => 0, 'fail' => 0, 'exception' => 0];
        }

        foreach ($allJobs as $job) {
            if (empty($job['begin'])) {
                continue;
            }

            $date = current(explode(' ', $job['begin']));
            $exitCode = $job['exitcode'];

            switch ($exitCode) {
                case 0:
                    $state = 'success';

                    break;
                case 255:
                    $state = 'exception';

                    break;
                case null:
                case -1:
                    $state = 'waiting';

                    break;

                default:
                    $state = 'fail';

                    break;
            }

            // Skip jobs outside the 30-day window
            if (\array_key_exists($date, $days) === false) {
                continue;
            }

            ++$days[$date][$state];
        }

        $data = [];
        $count = 0;

        $todayStr = $today->format('Y-m-d');

        foreach ($days as $date => $day) {
            $data[] = [
                'day' => $count++,
                'date' => ($date === $todayStr) ? _('today') : substr($date, 2),
                'success' => $day['success'],
                'waiting' => $day['waiting'],
                'fail' => $day['fail'],
                'exception' => $day['exception'],
            ];
        }

        // Legend colours matching the graph (same order: waiting, fail, success, exception)
        $legendItems = [
            ['color' => '#75798c', 'label' => _('waiting')],
            ['color' => '#d66862', 'label' => _('fail')],
            ['color' => '#6eb787', 'label' => _('success')],
            ['color' => '#d6be6e', 'label' => _('exception')],
        ];

        $settings = [
            'auto_fit' => true,
            'graph_title' => _('Jobs in the last 30 days'),
            // Nocturne dark palette (see css/nocturne.css)
            'back_colour' => '#232532',
            'back_stroke_width' => 0,
            'back_stroke_colour' => '#232532',
            'graph_title_colour' => '#e9e9ed',
            'graph_title_font_weight' => 'normal',
            'axis_colour' => '#75798c',
            'axis_text_colour' => '#b2b6ca',
            'label_colour' => '#b2b6ca',
            'grid_colour' => '#3f424d',
            'bar_outline_colour' => 'none',
            'bar_stroke_width' => 0,
            'structured_data' => true,
            'legend_type' => 'none',
            'data_label_popfront' => true,
            'link_base' => './',
            'link_target' => '_top',
            'data_label_font_size' => 5,
            'axis_text_angle_h' => -90,
            'subdivision_size' => 5,
            'minimum_grid_spacing' => 20,
            'show_subdivisions' => true,
            'show_grid_subdivisions' => true,
            'grid_subdivision_colour' => '#2f323c',
            'show_data_labels' => true,
            'data_label_type' => [
                'box', 'linebox',
            ],
            'data_label_space' => 5,
            'data_label_min_space' => 15, // Minimum space between labels to prevent overlap
            'data_label_font_adjust' => 0.8, // Slightly smaller font for better fit
            'data_label_colour' => '#e9e9ed',
            'data_label_fill' => '#2f323c',
            'data_label_colour_outside' => '#e9e9ed',
            'data_label_back_colour_outside' => '#2f323c',
            'data_label_outline_colour' => '#75798c',
            'data_label_back_colour' => [
                '#2f323c', '#3a2c30', '#2a3a31', '#3a382a', '#2f323c', '#2f323c', '#2f323c', '#2f323c', '#2f323c', '#2f323c',
            ],
            'marker_size' => 3,
            'structure' => [
                'key' => 'date',
                'value' => ['waiting', 'fail', 'success', 'exception'],
                'tooltip' => ['waiting', 'fail', 'success', 'exception'],
                'axis_text' => 3,
            ],
        ];
        $links = ['success' => '?showonly=success', 'fail' => '?showonly=fail', 'waiting' => '?showonly=waiting', 'exception' => '?showonly=exception'];

        // Pastel colors: waiting (light blue), fail (light red), success (light green), exception (light gray)
        $colours = [
            ['#75798c', '#595d6c'], // waiting - neutral
            ['#d66862', '#b9524d'], // fail - red
            ['#6eb787', '#559a6e'], // success - green
            ['#d6be6e', '#b8a152'], // exception - amber
        ];

        $graph = new \Goat1000\SVGGraph\SVGGraph('100%', 212, $settings);
        $graph->values($data);
        $graph->colours($colours);
        $graph->links($links);

        // Build HTML legend to display beside the chart
        $legendHtml = '';

        foreach ($legendItems as $item) {
            $legendHtml .= sprintf(
                '<div class="d-flex align-items-center mb-1">'.
                '<span style="display:inline-block;width:14px;height:14px;background:%s;border-radius:2px;margin-right:6px;flex-shrink:0"></span>'.
                '<small class="text-body-secondary">%s</small>'.
                '</div>',
                $item['color'],
                htmlspecialchars($item['label']),
            );
        }

        $wrapper = '<div class="d-flex align-items-stretch w-100">'
            .'<div class="flex-grow-1" style="min-width:0">'.$graph->fetch('StackedBarGraph', false).'</div>'
            .'<div class="ps-2 ms-1 d-flex flex-column justify-content-center border-start" style="min-width:85px">'.$legendHtml.'</div>'
            .'</div>';

        parent::__construct($wrapper, $properties);
    }

    /**
     * @return \Envms\FluentPDO\Queries\Select
     */
    public function getJobs()
    {
        $since = (new \DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');
        $query = $this->engine->getFluentPDO(true)
            ->from('job')
            ->select(['begin', 'exitcode'], true)
            ->where('begin >= ?', $since);

        $accessibleCompanyIds = \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies();

        if (empty($accessibleCompanyIds)) {
            return $query->where('1=0')->order('begin');
        }

        return $query
            ->where('job.company_id IN ('.implode(',', array_map('intval', $accessibleCompanyIds)).')')
            ->order('begin');
    }
}
