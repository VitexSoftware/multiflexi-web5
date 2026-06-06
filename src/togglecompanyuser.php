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

$companyId = \Ease\TWB5\WebPage::getRequestValue('company_id', 'int');
$userId = \Ease\TWB5\WebPage::getRequestValue('user_id', 'int');
$state = \Ease\TWB5\WebPage::getRequestValue('state') === 'true';

$result = 400;

if ($companyId && $userId) {
    // Enforce access control - user must have access to the company
    if (!\MultiFlexi\Security\CompanyAccessControl::currentUserCanAccessCompany($companyId)) {
        http_response_code(403);
        echo json_encode(['result' => 'error', 'message' => _('You do not have access to this company')]);

        exit;
    }

    $company = new \MultiFlexi\Company($companyId);
    $user = new \MultiFlexi\User($userId);

    if ($company->getMyKey() && $user->getMyKey()) {
        $companyUser = new \MultiFlexi\CompanyUser($company);
        $assignedRaw = $companyUser->getAssigned()->fetchAll('user_id');
        $assigned = empty($assignedRaw) ? [] : array_keys($assignedRaw);

        $isCurrentlyAssigned = \in_array($userId, $assigned, true);

        if ($state && !$isCurrentlyAssigned) {
            $result = $companyUser->assignUser($userId) ? 200 : 500;
        } elseif (!$state && $isCurrentlyAssigned) {
            $result = $companyUser->removeUser($userId) ? 200 : 500;
        } else {
            $result = 200;
        }
    } else {
        $result = 404;
    }
}

http_response_code($result);
header('Content-Type: application/json');
echo json_encode(['result' => $result === 200 ? 'success' : 'error']);
