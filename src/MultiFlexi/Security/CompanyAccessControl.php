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

namespace MultiFlexi\Security;

use MultiFlexi\Company;
use MultiFlexi\CompanyUser;
use MultiFlexi\Credential;
use MultiFlexi\User;

/**
 * Company-level RBAC enforcement.
 * Checks if user has access to companies and their resources.
 */
class CompanyAccessControl
{
    /**
     * Check if current user has access to a company.
     *
     * @param int $companyId Company ID
     *
     * @return bool True if user has access
     */
    public static function currentUserCanAccessCompany(int $companyId): bool
    {
        $userId = self::getCurrentUserId();

        if (!$userId) {
            return false;
        }

        return self::userCanAccessCompany($userId, $companyId);
    }

    /**
     * Check if a user has access to a company.
     *
     * @param int $userId    User ID
     * @param int $companyId Company ID
     *
     * @return bool True if user has access
     */
    public static function userCanAccessCompany(int $userId, int $companyId): bool
    {
        if (self::currentUserIsSuperAdmin()) {
            return true;
        }

        // Check if user is assigned to company via company_user
        $companyUser = new CompanyUser(new Company($companyId));
        $assigned = $companyUser->listingQuery()
            ->where('user_id', $userId)
            ->fetch();

        return null !== $assigned;
    }

    /**
     * Get companies accessible by current user.
     *
     * @return array Array of company IDs
     */
    public static function getCurrentUserAccessibleCompanies(): array
    {
        if (self::currentUserIsSuperAdmin()) {
            $company = new Company();
            $rows = $company->listingQuery()->select(['id'])->fetchAll();
            $ids = array_map('intval', array_column($rows, 'id'));

            return array_values(array_filter($ids, static fn ($id) => $id > 0));
        }

        $userId = self::getCurrentUserId();

        if (!$userId) {
            return [];
        }

        return self::getUserAccessibleCompanies($userId);
    }

    /**
     * Get companies accessible by a user.
     *
     * @param int $userId User ID
     *
     * @return array Array of company IDs (filtered to exclude empty/null values)
     */
    public static function getUserAccessibleCompanies(int $userId): array
    {
        $companyUser = new CompanyUser();
        $result = $companyUser->listingQuery()
            ->leftJoin('company ON company.id = company_user.company_id')
            ->where('user_id', $userId)
            ->where('company.id IS NOT NULL')
            ->select(['company_user.company_id'])
            ->fetchAll();

        // Normalize, de-duplicate and filter out invalid IDs
        $companies = array_map('intval', array_column($result, 'company_id'));
        $companies = array_values(array_unique($companies));

        return array_values(array_filter($companies, static function ($id) {
            return $id > 0;
        }));
    }

    /**
     * Check if current user can access a credential.
     *
     * @param int $credentialId Credential ID
     *
     * @return bool True if user has access
     */
    public static function currentUserCanAccessCredential(int $credentialId): bool
    {
        $credential = new Credential($credentialId);

        if (!$credential->getMyKey()) {
            return false; // Credential doesn't exist
        }

        $companyId = $credential->getDataValue('company_id');

        return self::currentUserCanAccessCompany((int) $companyId);
    }

    /**
     * Check if current user can access a job.
     *
     * @param int $jobId Job ID
     *
     * @return bool True if user has access
     */
    public static function currentUserCanAccessJob(int $jobId): bool
    {
        $job = new \MultiFlexi\Job($jobId);

        if (!$job->getMyKey()) {
            return false; // Job doesn't exist
        }

        $companyId = $job->getDataValue('company_id');

        return self::currentUserCanAccessCompany((int) $companyId);
    }

    /**
     * Enforce access check and redirect/exit if denied.
     *
     * @param int         $companyId Company ID
     * @param null|string $message   Custom denial message
     */
    public static function enforceCompanyAccess(int $companyId, ?string $message = null): void
    {
        if (!self::currentUserCanAccessCompany($companyId)) {
            self::denyAccess(
                $message ??
                sprintf(
                    _('You do not have access to company with ID %d'),
                    $companyId,
                ),
            );
        }
    }

    /**
     * Enforce access check for credential and redirect/exit if denied.
     *
     * @param int         $credentialId Credential ID
     * @param null|string $message      Custom denial message
     */
    public static function enforceCredentialAccess(int $credentialId, ?string $message = null): void
    {
        if (!self::currentUserCanAccessCredential($credentialId)) {
            self::denyAccess($message ?? _('You do not have access to this credential'));
        }
    }

    /**
     * Enforce access check for job and redirect/exit if denied.
     *
     * @param int         $jobId   Job ID
     * @param null|string $message Custom denial message
     */
    public static function enforceJobAccess(int $jobId, ?string $message = null): void
    {
        if (!self::currentUserCanAccessJob($jobId)) {
            self::denyAccess($message ?? _('You do not have access to this job'));
        }
    }

    /**
     * Get current user ID.
     *
     * @return null|int User ID or null if not logged in
     */
    private static function getCurrentUserId(): ?int
    {
        // TODO: Use (Shared::user())->getMyKey()

        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        if (isset($_SESSION['USER_ID']) && is_numeric($_SESSION['USER_ID'])) {
            return (int) $_SESSION['USER_ID'];
        }

        return null;
    }

    /**
     * Check whether the currently logged-in user holds the super_admin role.
     */
    private static function currentUserIsSuperAdmin(): bool
    {
        return isset($GLOBALS['rbac']) && $GLOBALS['rbac']->hasRole('super_admin');
    }

    /**
     * Display access denied message and exit.
     *
     * @param string $message Error message
     */
    private static function denyAccess(string $message): void
    {
        // Record the denial in the security audit log
        if (isset($GLOBALS['securityAuditLogger'])) {
            $GLOBALS['securityAuditLogger']->logEvent(
                'access_denied',
                $message,
                'medium',
                self::getCurrentUserId(),
                ['request_uri' => $_SERVER['REQUEST_URI'] ?? null],
            );
        }

        // Try to use WebPage if available
        if (class_exists('MultiFlexi\Ui\WebPage')) {
            $page = \MultiFlexi\Ui\WebPage::singleton();
            $page->addStatusMessage($message, 'danger');
            $page->addStatusMessage(_('Access Denied'), 'error');
            $page->redirect('companies.php');
        } else {
            // Fallback: simple redirect with message in session
            $_SESSION['access_denied_message'] = $message;
            header('Location: companies.php?access_denied=1');
        }

        exit;
    }
}
