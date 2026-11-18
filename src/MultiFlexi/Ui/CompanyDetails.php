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

use MultiFlexi\Company;

/**
 * Readable company record for the quick-view drawer: logo, labelled values,
 * empty fields skipped, settings shown as formatted JSON.
 *
 * @no-named-arguments
 */
class CompanyDetails extends \Ease\Html\DivTag
{
    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(Company $company, array $properties = [])
    {
        $data = (array) ($company->getData() ?? []);
        $name = (string) ($data['name'] ?? '');
        $html = '<div class="nc-detail-head">'.new CompanyLogo($company, ['style' => 'height: 56px; width: auto; max-width: 120px; object-fit: contain;'])
            .'<div><div class="nc-detail-name">'.htmlspecialchars($name).'</div>'
            .(empty($data['slug']) ? '' : '<span class="nc-tag">'.htmlspecialchars((string) $data['slug']).'</span>').'</div></div>';

        $yes = '✅ '._('Yes');
        $no = '⛔ '._('No');
        $rows = [
            [_('ID'), htmlspecialchars((string) ($data['id'] ?? ''))],
            [_('Company ID'), htmlspecialchars((string) ($data['ic'] ?? ''))],
            [_('Email'), empty($data['email']) ? '' : '<a href="mailto:'.htmlspecialchars((string) $data['email']).'">'.htmlspecialchars((string) $data['email']).'</a>'],
            [_('Enabled'), isset($data['enabled']) ? ((int) $data['enabled'] ? $yes : $no) : ''],
            [_('Set up'), isset($data['setup']) ? ((int) $data['setup'] ? $yes : $no) : ''],
            [_('Customer'), empty($data['customer']) ? '' : '<a href="customer.php?id='.(int) $data['customer'].'">#'.(int) $data['customer'].'</a>'],
            [_('Zabbix host'), htmlspecialchars((string) ($data['zabbix_host'] ?? ''))],
            [_('Created'), htmlspecialchars((string) ($data['DatCreate'] ?? ''))],
            [_('Updated'), htmlspecialchars((string) ($data['DatUpdate'] ?? ''))],
            [_('Data retained until'), htmlspecialchars((string) ($data['retention_until'] ?? ''))],
            [_('Marked for deletion'), !empty($data['marked_for_deletion']) ? $yes : ''],
        ];

        // Every value above is already HTML-safe (escaped or fixed markup)
        $dl = '<dl class="nc-detail-list">';

        foreach ($rows as [$label, $value]) {
            if ($value === '') {
                continue;
            }

            $dl .= '<dt>'.htmlspecialchars((string) $label).'</dt><dd>'.$value.'</dd>';
        }

        $dl .= '</dl>';

        $settings = $data['settings'] ?? null;
        $settingsHtml = '';

        if (\is_string($settings) && $settings !== '' && $settings !== '[]' && $settings !== '{}' && $settings !== 'null') {
            $decoded = json_decode($settings, true);
            $pretty = $decoded === null ? $settings : json_encode($decoded, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
            $settingsHtml = '<h3 class="nc-detail-sub">'.htmlspecialchars(_('Settings')).'</h3><pre class="nc-detail-pre">'.htmlspecialchars((string) $pretty).'</pre>';
        }

        parent::__construct($html.$dl.$settingsHtml, $properties + ['class' => 'nc-detail']);
    }
}
