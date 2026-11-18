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
 * Grid of company cards summarising the last 30 days; a card opens company.php.
 *
 * @no-named-arguments
 */
class DashboardCompanyGrid extends \Ease\Html\DivTag
{
    private const MAX_ISSUES = 3;

    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(DashboardData $data, array $properties = [])
    {
        $stats = $data->stats();
        $cards = '';

        foreach ($data->companies() as $company) {
            $id = (int) $company['id'];
            $counts = $stats->company($id);
            $ok = $counts['success'];
            $fail = $counts['fail'] + $counts['exception'];
            $total = $ok + $fail;
            $rate = $total > 0 ? (int) round($ok / $total * 100) : null;
            $href = 'company.php?id='.$id;
            $name = (string) $company['name'];

            $logo = !empty($company['logo'])
                ? '<img src="'.htmlspecialchars((string) $company['logo']).'" alt="" class="nc-avatar-img">'
                : htmlspecialchars(self::initials($name));

            $issues = '';
            $apps = $counts['apps'];
            arsort($apps);
            $apps = \array_slice($apps, 0, self::MAX_ISSUES, true);

            if ($apps !== []) {
                $names = $data->appNames(array_keys($apps));
                $issues = '<div class="nc-issues">';

                foreach ($apps as $appId => $n) {
                    $issues .= '<div class="nc-issue"><i class="bi bi-exclamation-circle nc-fail-text"></i>'
                        .'<span class="nc-issue-app">'.htmlspecialchars($names[$appId] ?? '#'.$appId).'</span>'
                        .'<span class="nc-fail-text">'.$n.'×</span></div>';
                }

                $issues .= '</div>';
            }

            $okWidth = $total > 0 ? round($ok / $total * 100, 2) : 0;
            $failWidth = $total > 0 ? round($fail / $total * 100, 2) : 0;

            $cards .= '<div class="nc-company-wrap">'
                .'<a href="'.htmlspecialchars($href).'" class="nc-company" title="'.htmlspecialchars(sprintf(_('Open company %s'), $name)).'">'
                .'<div class="nc-company-head"><div class="nc-avatar">'.$logo.'</div>'
                .'<div class="nc-company-id"><span class="nc-company-name">'.htmlspecialchars($name).'</span>'
                .'<span class="nc-muted">'.htmlspecialchars(sprintf(_('%d apps'), $data->appCount($id))).'</span></div>'
                .'<span class="nc-company-rate'.($rate !== null && $rate < 80 ? ' nc-fail-text' : '').'">'.($rate === null ? '—' : $rate."\u{a0}%").'</span></div>'
                .'<div class="nc-bar"><div class="nc-bar-ok" style="width:'.$okWidth.'%"></div><div class="nc-bar-fail" style="width:'.$failWidth.'%"></div></div>'
                .'<div class="nc-company-legend"><span><i class="nc-dot nc-dot-ok"></i>'.$ok.' '.htmlspecialchars(_('success')).'</span>'
                .'<span><i class="nc-dot nc-dot-fail"></i>'.$fail.' '.htmlspecialchars(_('failed')).'</span></div>'
                .$issues
                .'</a>'
                .'</div>';
        }

        parent::__construct(
            '<div class="nc-section-head"><h2 class="nc-h2">'.htmlspecialchars(_('Companies')).'</h2><span class="nc-muted">'.htmlspecialchars(_('Click a company to open it')).'</span></div>'
            .'<div class="nc-company-grid">'.$cards.'</div>',
            $properties + ['class' => 'nc-section'],
        );
    }

    private static function initials(string $name): string
    {
        $letters = '';

        foreach (\array_slice(preg_split('/\s+/u', trim($name)) ?: [], 0, 2) as $word) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $letters === '' ? '?' : $letters;
    }
}
