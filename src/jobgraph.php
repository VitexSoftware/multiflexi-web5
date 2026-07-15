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

$companyId = WebPage::getRequestValue('company_id', 'int');
$runtemplateId = WebPage::getRequestValue('runtemplate_id', 'int');
$appId = WebPage::getRequestValue('app_id', 'int');
$width = WebPage::getRequestValue('width', 'int');
$height = WebPage::getRequestValue('height', 'int');

$accessibleCompanies = \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies();

$jobber = new \MultiFlexi\Job();

// Build query based on provided parameters
$query = $jobber->listingQuery()->select('exitcode', true)->limit($width * $height)->orderBy('id DESC');

if ($runtemplateId) {
    $runtemplate = new \MultiFlexi\RunTemplate($runtemplateId);
    \MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess((int) $runtemplate->getDataValue('company_id'));
    $query->where('runtemplate_id', $runtemplateId);
} elseif ($companyId) {
    \MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess($companyId);
    $query->where('company_id', $companyId);

    if ($appId) {
        $query->where('app_id', $appId);
    }
} elseif (!empty($accessibleCompanies)) {
    $query->where('company_id', $accessibleCompanies);

    if ($appId) {
        $query->where('app_id', $appId);
    }
} else {
    $query->where('1=0');
}

$todaysJobs = $query->fetchAll();

$jobGraph = new JobGraph($width, $height, $todaysJobs);
$base64Image = $jobGraph->generateImage();

header('Content-Type: image/png');

echo $jobGraph->getImage();
