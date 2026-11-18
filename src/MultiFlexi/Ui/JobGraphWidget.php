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
 * Job Graph Widget Component.
 *
 * Displays a visual grid of recent job exit codes, scoped to a single
 * RunTemplate, a single Company (optionally filtered to one App), or
 * (when nothing is given) to every company the signed-in user may see.
 *
 * @author vitex
 */
class JobGraphWidget extends \Ease\Html\DivTag
{
    private ?\MultiFlexi\RunTemplate $runtemplate;
    private ?int $companyId;
    private ?int $appId;
    private int $width;
    private int $height;

    /**
     * Constructor.
     *
     * @param null|\MultiFlexi\RunTemplate $runtemplate RunTemplate to scope the graph to (takes precedence over companyId/appId)
     * @param null|int                     $companyId   Company to scope the graph to (ignored if $runtemplate is given)
     * @param null|int                     $appId       App to further scope the graph to within $companyId
     * @param int                          $width       Grid width in cells (default: 20)
     * @param int                          $height      Grid height in cells (default: 10)
     * @param array                        $properties  HTML element properties
     * @param bool                         $all         Show all jobs in scope; grid size is computed from job count
     */
    public function __construct(
        ?\MultiFlexi\RunTemplate $runtemplate = null,
        ?int $companyId = null,
        ?int $appId = null,
        int $width = 20,
        int $height = 10,
        array $properties = [],
        bool $all = false,
    ) {
        $this->runtemplate = $runtemplate;
        $this->companyId = $companyId;
        $this->appId = $appId;
        $this->width = $width;
        $this->height = $height;

        parent::__construct(null, $properties);

        $params = ['width' => $width, 'height' => $height];

        if ($runtemplate) {
            $params['runtemplate_id'] = $runtemplate->getMyKey();
        } elseif ($companyId) {
            $params['company_id'] = $companyId;

            if ($appId) {
                $params['app_id'] = $appId;
            }
        } elseif ($appId) {
            $params['app_id'] = $appId;
        }

        if ($all) {
            $params['all'] = 1;
        }

        $graphUrl = 'jobgraph.php?'.http_build_query($params);

        $imgTag = new \Ease\Html\ImgTag(
            $graphUrl,
            _('Job History Graph'),
            [
                'class' => 'rounded',
                'style' => 'height: 212px; width: auto; max-width: 100%; image-rendering: pixelated;',
                'title' => _('Visual representation of recent job executions. Each cell represents a job, colored by exit code.'),
            ],
        );

        $this->addItem($imgTag);

        // Add legend
        $this->addItem(self::createLegend());
    }

    /**
     * Create legend explaining the color codes.
     */
    private static function createLegend(): \Ease\Html\DivTag
    {
        $legend = new \Ease\Html\DivTag(null, ['class' => 'mt-2 small text-body-secondary']);

        foreach ([
            ['rgb(110,183,135)', _('Success (0)')],
            ['rgb(214,104,98)', _('Failed')],
            ['rgb(214,190,110)', _('Not runnable (127)')],
            ['rgb(145,132,217)', _('Exception (255)')],
            ['rgb(147,151,171)', _('Waiting')],
        ] as [$color, $label]) {
            $legend->addItem(new \Ease\Html\SpanTag('<i style="display:inline-block;width:8px;height:8px;border-radius:2px;background:'.$color.';margin-right:5px;"></i>'.htmlspecialchars((string) $label), ['class' => 'me-3']));
        }

        return $legend;
    }
}
