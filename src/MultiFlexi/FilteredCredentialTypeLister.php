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

namespace MultiFlexi;

/**
 * CredentialType lister filtered by user's accessible companies (RBAC).
 */
class FilteredCredentialTypeLister extends CredentialTypeLister
{
    /**
     * Override listingQuery to filter by accessible companies.
     */
    public function listingQuery(): \Envms\FluentPDO\Queries\Select
    {
        $query = parent::listingQuery();

        // Get accessible company IDs
        $accessibleCompanyIds = \MultiFlexi\Security\CompanyAccessControl::getCurrentUserAccessibleCompanies();

        if (empty($accessibleCompanyIds)) {
            // Return empty result if user has no access
            return $query->where('1=0');
        }

        // Filter credential types by accessible companies
        return $query->where('company_id IN ('.implode(',', array_map('intval', $accessibleCompanyIds)).')');
    }
}
