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

require_once './init.php';
WebPage::singleton()->onlyForLogged();
$jobID = WebPage::singleton()->getRequestValue('id', 'int');

if ($jobID) {
    // Enforce access control - user must have access to the job's company
    \MultiFlexi\Security\CompanyAccessControl::enforceJobAccess($jobID);

    $jobber = new \MultiFlexi\Job($jobID);
    header('Content-Type: text/x-env');
    header('Content-Disposition: attachment; filename="multiflexi_job_'.$jobID.'.env"');
    echo $jobber->envFile();
}
