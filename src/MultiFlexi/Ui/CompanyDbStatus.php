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
 * Description of DbStatus.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class CompanyDbStatus extends \Ease\TWB5\Row
{
    /**
     * Show status of database.
     *
     * @param mixed $company
     */
    public function __construct($company)
    {
        parent::__construct();
        $companyId = $company->getMyKey();
        $jobs = (string) (new \MultiFlexi\Job())->listingQuery()->where('company_id', $companyId)->count();
        $jobsSuccess = (string) (new \MultiFlexi\Job())->listingQuery()->where('company_id', $companyId)->where('exitcode', 0)->count();
        $jobsUnfinished = (string) (new \MultiFlexi\Job())->listingQuery()->where('company_id', $companyId)->where('end', null)->count();
        $apps = (new \MultiFlexi\CompanyApp($company))->getAssigned()->count();
        $periodical = (string) (new \MultiFlexi\RunTemplate())->listingQuery()->where('company_id', $companyId)->count();
        //        $customers = (string) (new \MultiFlexi\Customer())->listingQuery()->count();
        //        $companies = (string) (new \MultiFlexi\Company())->listingQuery()->count();
        //        $apps = (string) (new \MultiFlexi\Application())->listingQuery()->count();
        //        $assigned = (string) (new \MultiFlexi\RunTemplate())->listingQuery()->count();

        $this->addColumn(2, new \Ease\Html\ButtonTag(
            [_('Apps').'&nbsp;', new \Ease\TWB5\Badge($apps, 'info', ['class' => 'rounded-pill'])],
            ['class' => 'btn btn-default', 'type' => 'button'],
        ));
        $this->addColumn(2, new \Ease\Html\ButtonTag(
            [_('Periodical').'&nbsp;', new \Ease\TWB5\Badge($periodical, 'info', ['class' => 'rounded-pill'])],
            ['class' => 'btn btn-default', 'type' => 'button'],
        ));
        $this->addColumn(2, new \Ease\Html\ButtonTag(
            [_('Jobs Total').'&nbsp;', new \Ease\TWB5\Badge($jobs, 'info', ['class' => 'rounded-pill'])],
            ['class' => 'btn btn-default', 'type' => 'button'],
        ));
        $this->addColumn(2, new \Ease\TWB5\LinkButton(
            'joblist.php?filter=success&company_id='.$companyId,
            [_('Success Jobs').'&nbsp;', new \Ease\TWB5\Badge($jobsSuccess, 'success', ['class' => 'rounded-pill'])],
            'success',
            ['title' => _('View successful jobs'), 'id' => 'successjobscompanybutton'],
        ));
        $this->addColumn(2, new \Ease\TWB5\LinkButton(
            'joblist.php?filter=failed&company_id='.$companyId,
            [_('Failed Jobs').'&nbsp;', new \Ease\TWB5\Badge($jobs - $jobsSuccess - $jobsUnfinished, 'danger', ['class' => 'rounded-pill'])],
            'danger',
            ['title' => _('View failed jobs'), 'id' => 'failedjobscompanybutton'],
        ));
        $this->addColumn(2, new \Ease\TWB5\LinkButton(
            'joblist.php?filter=scheduled&company_id='.$companyId,
            [_('Unfinished Jobs').'&nbsp;', new \Ease\TWB5\Badge($jobsUnfinished, 'warning', ['class' => 'rounded-pill'])],
            'warning',
            ['title' => _('View unfinished jobs'), 'id' => 'unfinishedjobscompanybutton'],
        ));
        //        $this->addColumn(2, new \Ease\Html\ButtonTag(
        //            [_('Customers') . '&nbsp;', new \Ease\TWB5\Badge($customers, 'info', ['class' => 'rounded-pill'])],
        //            ['class' => 'btn btn-default', 'type' => 'button']
        //        ));
        //        $this->addColumn(2, new \Ease\Html\ButtonTag(
        //            [_('Companies') . '&nbsp;', new \Ease\TWB5\Badge($companies, 'success', ['class' => 'rounded-pill'])],
        //            ['class' => 'btn btn-default', 'type' => 'button']
        //        ));
        //        $this->addColumn(2, new \Ease\Html\ButtonTag(
        //            [_('Assigned') . '&nbsp;', new \Ease\TWB5\Badge($assigned, 'success', ['class' => 'rounded-pill'])],
        //            ['class' => 'btn btn-default', 'type' => 'button']
        //        ));
    }
}
