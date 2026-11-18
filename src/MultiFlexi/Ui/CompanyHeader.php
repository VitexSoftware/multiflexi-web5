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
use MultiFlexi\DashboardData;

/**
 * Header of company.php: logo, name and code on the left, 30-day KPIs on the
 * right, and a toolbar with the company's sub-pages below.
 *
 * @no-named-arguments
 */
class CompanyHeader extends \Ease\Html\DivTag
{
    /**
     * @param array<string, mixed> $properties HTML properties of the wrapper element
     */
    public function __construct(Company $company, ?string $detailTrigger = null, array $properties = [])
    {
        $cid = (int) $company->getMyKey();
        $name = (string) ($company->getRecordName() ?: $company->getDataValue('code'));
        $code = (string) $company->getDataValue('code');

        $lead = '<div class="nc-company-title">'
            .'<a href="company.php?id='.$cid.'" class="nc-company-logo">'.new CompanyLogo($company, ['style' => 'height: 56px; width: auto; max-width: 120px; object-fit: contain;']).'</a>'
            .'<div><div class="nc-kicker">'.htmlspecialchars(_('Company')).'</div>'
            .'<h1 class="nc-title">'.htmlspecialchars($name).'</h1>'
            .($code !== '' && $code !== $name ? '<span class="nc-muted">'.htmlspecialchars($code).'</span>' : '').'</div></div>';

        $links = [
            ['companyapps.php?company_id='.$cid, '📌', _('Applications')],
            ['companycreds.php?company_id='.$cid, '🔐', _('Credentials')],
            ['jobs.php?company_id='.$cid, '🏁', _('Jobs')],
            ['activation-wizard.php?company='.$cid, '🧙', _('Wizard')],
            ['companyuser.php?company_id='.$cid, '🔑', _('Access Rights')],
            ['companysetup.php?id='.$cid, '🛠️', _('Setup')],
        ];
        $toolbar = '';

        foreach ($links as [$url, $icon, $label]) {
            $toolbar .= '<a class="btn btn-sm btn-outline-secondary" href="'.htmlspecialchars($url).'">'.$icon.'&nbsp;'.htmlspecialchars($label).'</a>';
        }

        $strip = new DashboardKpiStrip(new DashboardData($cid), [], $lead);

        parent::__construct(
            [$strip, '<div class="nc-toolbar">'.$toolbar.($detailTrigger ?? '').'</div>'],
            $properties + ['class' => 'nc-company-header'],
        );
    }
}
