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

/**
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023-2024 Vitex Software
 */

namespace MultiFlexi\Ui;

/**
 * Description of CompanyPanel.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CompanyPanel extends \Ease\TWB5\Panel
{
    /**
     * @param \MultiFlexi\Company $company
     * @param mixed               $content
     * @param mixed               $footer
     */
    public function __construct($company, $content = null, $footer = null)
    {
        $cid = $company->getMyKey();
        $headRow = new \Ease\TWB5\Row(null, 0, ['class' => 'g-2 align-items-center']);

        $headRow->addItem(new \Ease\Html\DivTag(
            new \Ease\Html\ATag('company.php?id='.$cid, [new CompanyLogo($company, ['style' => 'height: 52px; width: auto; max-width: 140px; object-fit: contain;', 'class' => 'img-thumbnail shadow-sm'])]),
            ['class' => 'col-auto text-center'],
        ));

        $headRow->addItem(new \Ease\Html\DivTag([
            new \Ease\Html\H2Tag($company->getRecordName() ?: $company->getDataValue('code'), ['class' => 'mb-0 fs-5']),
            new \Ease\Html\SmallTag($company->getDataValue('code'), ['class' => 'text-muted d-block small']),
        ], ['class' => 'col']));

        $actionsRow = new \Ease\TWB5\Row(null, 0, ['class' => 'g-1']);

        foreach ([
            ['companysetup.php?id='.$cid, '🛠️ '._('Setup'), 'outline-secondary'],
            ['companyapps.php?company_id='.$cid, '📌 '._('Applications'), 'outline-secondary'],
            ['activation-wizard.php?company='.$cid, '🧙 '._('Wizard'), 'outline-primary'],
            ['companycreds.php?company_id='.$cid, '🔐 '._('Credentials'), 'outline-secondary'],
            ['joblist.php?company_id='.$cid, '🏁 '._('Jobs'), 'outline-info'],
            ['companyuser.php?company_id='.$cid, '📌 '._('Access Rights'), 'outline-warning'],
        ] as [$url, $label, $style]) {
            $actionsRow->addItem(new \Ease\Html\DivTag(
                new \Ease\TWB5\LinkButton($url, $label, $style.' btn-sm w-100 shadow-sm'),
                ['class' => 'col-6 col-md-4'],
            ));
        }

        $headRow->addItem(new \Ease\Html\DivTag($actionsRow, ['class' => 'col-12 col-md-6']));

        parent::__construct($headRow, 'default', $content, $footer);
    }
}
