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
 * Dark Nocturne look for SVGGraph charts (page background, muted palette).
 *
 * The values mirror css/nocturne.css; SVGGraph needs literal colours, it cannot read CSS variables.
 *
 * @no-named-arguments
 */
final class ChartTheme
{
    /**
     * Page background (--color-bg): charts blend into the page.
     */
    public const BACKGROUND = '#161826';

    /**
     * Series colours: success, accent, amber, red, blue, teal, grey, violet, rose, sage.
     */
    public const PALETTE = ['#6eb787', '#8a7fd4', '#d6be6e', '#d66862', '#6aa5d6', '#5fb7ab', '#9397ab', '#a68bd0', '#c98fb0', '#7aa08a'];
    public const SUCCESS = '#6eb787';
    public const FAILURE = '#d66862';
    public const WARNING = '#d6be6e';
    public const INFO = '#6aa5d6';

    /**
     * Overlay the dark theme on chart settings (theme colours win over the given ones).
     *
     * @param array<string, mixed> $settings
     * @param string               $labelColour Data label colour: dark (default) for labels printed on bars and pie slices, light for labels on the page background (line charts)
     *
     * @return array<string, mixed>
     */
    public static function apply(array $settings, string $labelColour = self::BACKGROUND): array
    {
        return array_merge($settings, [
            'back_colour' => self::BACKGROUND,
            'back_stroke_colour' => self::BACKGROUND,
            'stroke_colour' => self::BACKGROUND,
            'axis_colour' => '#75798c',
            'axis_text_colour' => '#b2b6ca',
            'label_colour' => '#b2b6ca',
            'grid_colour' => '#2f323c',
            'legend_colour' => '#e9e9ed',
            'legend_back_colour' => '#232532',
            'legend_stroke_colour' => '#3f424d',
            'legend_shadow_opacity' => 0,
            'data_label_colour' => $labelColour,
            'data_label_back_colour' => 'none',
            'data_label_outline_colour' => 'none',
            'data_label_shadow_opacity' => 0,
            'graph_title_colour' => '#e9e9ed',
        ]);
    }
}
