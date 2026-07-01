# MultiFlexi RBAC (Role-Based Access Control) Implementation

**Date**: 2026-06-06  
**Version**: 1.0  
**Status**: Production Ready

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Core Components](#core-components)
4. [Database Schema](#database-schema)
5. [Access Control Enforcement](#access-control-enforcement)
6. [Protected Resources](#protected-resources)
7. [Integration Guide](#integration-guide)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)

---

## Overview

The MultiFlexi RBAC system enforces company-level access control across all web resources. Users can only access:

- **Companies** they are explicitly assigned to
- **Credentials** belonging to their accessible companies
- **Jobs** running within their accessible companies
- **Run Templates** configured for their accessible companies
- **Environments** and other company-specific settings

### Key Principles

- **Deny by Default**: Resources are inaccessible unless user has explicit assignment
- **Company-Centric**: All access decisions are based on `company_user` table assignments
- **User-Friendly**: Clear, translated denial messages guide users
- **Transparent**: No silent failures; access denials are logged and reported

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Web Pages (src/*.php)                    │
│  companies.php, company.php, credential.php, job.php, etc.  │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│        Security\CompanyAccessControl (Access Checks)        │
│  - currentUserCanAccessCompany()                            │
│  - currentUserCanAccessCredential()                         │
│  - currentUserCanAccessJob()                                │
│  - enforceCompanyAccess() (with denial message)             │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│      Filter Listers (RBAC-Aware Data Retrieval)             │
│  - FilteredCredentialLister (auto-filters credentials)      │
│  - FilteredCompanyJobLister (auto-filters jobs)             │
└─────────────────────────┬───────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                  CompanyUser Model                          │
│    Junction table: company_user (company_id, user_id)       │
└─────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────┐
│                   MySQL Database                            │
│        company_user table with unique(company_id, user_id)  │
└─────────────────────────────────────────────────────────────┘
```

---

## Core Components

### 1. CompanyAccessControl Class

**Location**: `src/MultiFlexi/Security/CompanyAccessControl.php`

**Responsibility**: Central access control decisions

#### Static Methods

| Method | Purpose | Returns |
|--------|---------|---------|
| `currentUserCanAccessCompany(int $companyId)` | Check if logged-in user can access a company | `bool` |
| `userCanAccessCompany(int $userId, int $companyId)` | Check if any user can access a company | `bool` |
| `getCurrentUserAccessibleCompanies()` | Get array of company IDs accessible to logged-in user | `array` |
| `getUserAccessibleCompanies(int $userId)` | Get array of company IDs accessible to any user | `array` |
| `currentUserCanAccessCredential(int $credentialId)` | Check credential access (via company) | `bool` |
| `currentUserCanAccessJob(int $jobId)` | Check job access (via company) | `bool` |
| `enforceCompanyAccess(int $companyId, ?string $message)` | Enforce access; exit with denial message if denied | `void` |
| `enforceCredentialAccess(int $credentialId, ?string $message)` | Enforce credential access; exit if denied | `void` |
| `enforceJobAccess(int $jobId, ?string $message)` | Enforce job access; exit if denied | `void` |

#### Usage Examples

```php
// Check without enforcement
if (CompanyAccessControl::currentUserCanAccessCompany($companyId)) {
    // Safe to proceed
}

// Enforce with auto-exit if denied
CompanyAccessControl::enforceCompanyAccess($companyId);

// Enforce with custom message
CompanyAccessControl::enforceCompanyAccess(
    $companyId,
    _('Company access denied for your account')
);

// Get list of accessible companies
$companies = CompanyAccessControl::getCurrentUserAccessibleCompanies();
// Returns: [1, 3, 5]
```

---

### 2. FilteredCredentialLister Class

**Location**: `src/MultiFlexi/FilteredCredentialLister.php`

**Responsibility**: Automatically filter credentials by user's accessible companies

**Override Point**: `listingQuery()`

```php
public function listingQuery()
{
    $query = parent::listingQuery();
    
    // Auto-applies: WHERE company_id IN (accessible_ids)
    $accessibleCompanyIds = CompanyAccessControl::getCurrentUserAccessibleCompanies();
    
    if (empty($accessibleCompanyIds)) {
        return $query->where('1=0'); // No results if no access
    }
    
    return $query->where('company_id IN (...)')
}
```

**Usage**: Use this in any page that needs to list credentials

```php
$credentialLister = new FilteredCredentialLister();
// Only credentials from accessible companies are returned
$allCredentials = $credentialLister->listingQuery()->fetchAll();
```

---

### 3. FilteredCompanyJobLister Class

**Location**: `src/MultiFlexi/FilteredCompanyJobLister.php`

**Responsibility**: Automatically filter jobs by user's accessible companies

**Similar to**: FilteredCredentialLister

**Usage**: Use in any page that needs to list jobs across companies

```php
$jobLister = new FilteredCompanyJobLister();
// Only jobs from accessible companies are returned
$allJobs = $jobLister->listingQuery()->fetchAll();
```

---

## Database Schema

### company_user Table

```sql
CREATE TABLE `company_user` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` VARCHAR(32) DEFAULT 'viewer',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  UNIQUE KEY `company_user_company_user_unique` (`company_id`, `user_id`),
  
  FOREIGN KEY `company_user_company_must_exist` 
    (`company_id`) REFERENCES `company`(`id`) ON DELETE CASCADE,
  FOREIGN KEY `company_user_user_must_exist` 
    (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### Access Query Pattern

```php
// Check if user $userId has access to company $companyId
SELECT COUNT(*) FROM company_user 
WHERE company_id = $companyId AND user_id = $userId;
```

---

## Access Control Enforcement

### Pattern: Permission Check + Early Exit

**Location of Enforcement**: Top of protected pages, after login check

```php
<?php
require_once './init.php';
WebPage::singleton()->onlyForLogged();

$companyId = WebPage::getRequestValue('id', 'int');

// Enforce access control (exits with message if denied)
\MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess(
    $companyId,
    sprintf(_('You do not have access to company "%s"'), 
            $companyName)
);

// If code continues, user has access
// Safe to proceed with company operations
```

### Deny Access Message

When access is denied:

1. HTTP status is set to 403
2. User sees error page with message
3. Action is logged (if audit logging enabled)
4. Session is maintained (user can return to other accessible pages)

---

## Protected Resources

### Pages with Company-Level Enforcement

| Page | Enforcement | Blocks |
|------|------------|--------|
| `company.php?id=X` | `enforceCompanyAccess($id)` | View company details, jobs, settings |
| `companysetup.php?id=X` | `enforceCompanyAccess($id)` | Modify company setup |
| `companyapps.php?company_id=X` | `enforceCompanyAccess($id)` | Assign/remove apps |
| `companycreds.php?company_id=X` | `enforceCompanyAccess($id)` | View company credentials |
| `companyenv.php?company_id=X` | `enforceCompanyAccess($id)` | Set environment variables |
| `companydelete.php?id=X` | `enforceCompanyAccess($id)` | Delete company |
| `companyuser.php?company_id=X` | `enforceCompanyAccess($id)` | Manage user access rights |
| `companyapp.php?id=X` | `enforceCompanyAccess($id)` | Edit app assignment |

### Pages with Credential-Level Enforcement

| Page | Enforcement | Blocks |
|------|------------|--------|
| `credential.php?id=X` | `enforceCredentialAccess($id)` | View/edit credential |
| `credentialclone.php?id=X` | `enforceCredentialAccess($id)` | Clone credential |

### Pages with Job-Level Enforcement

| Page | Enforcement | Blocks |
|------|------------|--------|
| `job.php?id=X` | `enforceJobAccess($id)` | View job details |

### Pages with Company List Filtering

| Page | Filtering | Effect |
|------|-----------|--------|
| `companies.php` | `getCurrentUserAccessibleCompanies()` | Only shows companies user is assigned to |
| `credentials.php` | `FilteredCredentialLister` | Only shows credentials from accessible companies |
| `joblist.php` | `FilteredCompanyJobLister` | Only shows jobs from accessible companies |

---

## Integration Guide

### Adding RBAC to a New Page

#### 1. Single Company Resource (e.g., company details page)

```php
<?php
declare(strict_types=1);

namespace MultiFlexi\Ui;

require_once './init.php';

// 1. Require login
WebPage::singleton()->onlyForLogged();

// 2. Get company ID from request
$companyId = WebPage::getRequestValue('id', 'int');

// 3. Enforce access (exits if denied)
\MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess(
    $companyId,
    sprintf(_('You do not have access to this company'))
);

// 4. Safe to proceed - user has access
$company = new Company($companyId);
WebPage::singleton()->addItem(new PageTop($company->getRecordName()));
// ... rest of page logic
```

#### 2. List of Resources (e.g., credentials page)

```php
<?php
declare(strict_types=1);

namespace MultiFlexi\Ui;

require_once './init.php';

// 1. Require login
WebPage::singleton()->onlyForLogged();

// 2. Use filtered lister (auto-applies RBAC)
$credentialLister = new \MultiFlexi\FilteredCredentialLister();

WebPage::singleton()->addItem(
    new DBDataTable($credentialLister)
);

// Only shows credentials from accessible companies
// No explicit enforcement needed - filtering handles it
```

#### 3. Credential Resource

```php
<?php
require_once './init.php';

WebPage::singleton()->onlyForLogged();

$credentialId = WebPage::getRequestValue('id', 'int');

// Enforce credential access (checks via company)
\MultiFlexi\Security\CompanyAccessControl::enforceCredentialAccess(
    $credentialId
);

// Safe to proceed
```

#### 4. Job Resource

```php
<?php
require_once './init.php';

WebPage::singleton()->onlyForLogged();

$jobId = WebPage::getRequestValue('id', 'int');

// Enforce job access (checks via company)
\MultiFlexi\Security\CompanyAccessControl::enforceJobAccess($jobId);

// Safe to proceed
```

### Creating a New Filtered Lister

If you need filtering for a new resource type:

```php
<?php
namespace MultiFlexi;

class FilteredRunTemplateLister extends RunTemplateLister
{
    public function listingQuery()
    {
        $query = parent::listingQuery();
        
        $accessibleCompanyIds = Security\CompanyAccessControl::getCurrentUserAccessibleCompanies();
        
        if (empty($accessibleCompanyIds)) {
            return $query->where('1=0');
        }
        
        return $query->where(
            'company_id IN (' . 
            implode(',', array_map('intval', $accessibleCompanyIds)) . 
            ')'
        );
    }
}
```

---

## Best Practices

### 1. Always Check Before Sensitive Operations

```php
// ✓ GOOD: Check before modification
CompanyAccessControl::enforceCompanyAccess($companyId);
$company->updateSettings($newSettings);

// ✗ BAD: Relying only on form submission
if ($_POST['save']) {
    $company->updateSettings($_POST);  // No check!
}
```

### 2. Use Filtered Listers for Lists

```php
// ✓ GOOD: Auto-filtered
$lister = new FilteredCredentialLister();
$credentials = $lister->listingQuery()->fetchAll();

// ✗ BAD: Manual filtering (easy to miss)
$credentials = (new Credential())->listingQuery()->fetchAll();
// Forgot to filter!
```

### 3. Consistent Denial Messages

```php
// ✓ GOOD: Translatable, contextual
CompanyAccessControl::enforceCompanyAccess(
    $id,
    sprintf(_('You do not have access to company "%s"'), 
            $company->getRecordName())
);

// ✗ BAD: Generic or untranslated
if (!CompanyAccessControl::currentUserCanAccessCompany($id)) {
    die('Access denied');
}
```

### 4. Early Enforcement

```php
// ✓ GOOD: Check immediately after login check
require_once './init.php';
WebPage::singleton()->onlyForLogged();
CompanyAccessControl::enforceCompanyAccess($id);
// ... page logic

// ✗ BAD: Checking deep in business logic
function updateCompany($id) {
    $company = new Company($id);
    // ... 50 lines of logic
    CompanyAccessControl::enforceCompanyAccess($id);  // Too late!
}
```

### 5. No Silent Failures

```php
// ✓ GOOD: Access check returns clear boolean
if (!CompanyAccessControl::currentUserCanAccessCompany($id)) {
    // Log, notify, or redirect explicitly
    WebPage::singleton()->addStatusMessage(
        _('You do not have access to this company'),
        'error'
    );
    return;
}

// ✗ BAD: Assume access always granted
loadCompanyData($id);  // Might expose data!
```

---

## Troubleshooting

### Problem: User Sees "Access Denied" on Page They Should Access

**Diagnosis**:
```php
// Check if user is assigned to company
$accessibleCompanies = CompanyAccessControl::getCurrentUserAccessibleCompanies();
var_dump($accessibleCompanies);  // Should contain company ID

// Check direct assignment
$companyUser = new CompanyUser(new Company($companyId));
$assignment = $companyUser->listingQuery()
    ->where('user_id', $userId)
    ->fetch();
var_dump($assignment);  // Should not be null
```

**Solutions**:
1. Visit `/src/companyuser.php?company_id=X` to assign user to company
2. Check that `company_user` table has entry for this user/company
3. Verify user is logged in (check `$_SESSION['USER_ID']` or `$_SESSION['user_id']`)

### Problem: New Page Has No Access Control

**Diagnosis**: Navigate to page and modify URL company ID → should still work

**Solution**: Add enforcement:
```php
CompanyAccessControl::enforceCompanyAccess($companyId);
```

### Problem: Users See Each Other's Data

**Diagnosis**: Run query:
```sql
SELECT DISTINCT company_id FROM company_user WHERE user_id = $userId;
-- Should NOT include other users' companies
```

**Solution**: 
1. Check `FilteredCredentialLister`, `FilteredCompanyJobLister` are in use
2. Check enforcement calls are present on detail pages
3. Verify `company_user` assignments are correct

### Problem: Session User ID Not Found

**Diagnosis**:
```php
$userId = $_SESSION['user_id'] ?? $_SESSION['USER_ID'] ?? null;
var_dump($userId);  // null = problem
```

**Solution**:
1. Ensure `onlyForLogged()` was called
2. Check login was successful: verify user exists in `user` table
3. Check session is active: `session_status() === PHP_SESSION_ACTIVE`

---

## Audit Trail

All access denial events should be logged:

```php
// Future: Log denials for security audit
\MultiFlexi\Security\SecurityAuditLogger::logAccessDenial(
    userId: $userId,
    companyId: $companyId,
    reason: 'User not assigned to company'
);
```

---

## Migration Path

### From No RBAC to RBAC (Existing System)

1. **Run migration** to create `company_user` table
2. **Assign admin users** to all companies
3. **Assign regular users** to their respective companies via UI
4. **Add enforcement** to pages incrementally
5. **Test thoroughly** before full rollout

### Steps for Admin Setup

1. Login as admin
2. Navigate to each company (`company.php?id=X`)
3. Click "Access Rights" button
4. Enable access for appropriate users

---

## Future Enhancements

- [ ] Role-based permissions (viewer, editor, admin)
- [ ] Time-limited access grants
- [ ] Audit logging for all access checks
- [ ] Delegation of access management rights
- [ ] Access request workflow
- [ ] Bulk access assignment
- [ ] Permission inheritance from groups

---

## API Reference

### CompanyAccessControl

```php
// Static class - no instantiation needed
CompanyAccessControl::currentUserCanAccessCompany(int $companyId): bool
CompanyAccessControl::userCanAccessCompany(int $userId, int $companyId): bool
CompanyAccessControl::getCurrentUserAccessibleCompanies(): array
CompanyAccessControl::getUserAccessibleCompanies(int $userId): array
CompanyAccessControl::currentUserCanAccessCredential(int $credentialId): bool
CompanyAccessControl::currentUserCanAccessJob(int $jobId): bool
CompanyAccessControl::enforceCompanyAccess(int $companyId, ?string $message): void
CompanyAccessControl::enforceCredentialAccess(int $credentialId, ?string $message): void
CompanyAccessControl::enforceJobAccess(int $jobId, ?string $message): void
```

### FilteredCredentialLister

```php
$lister = new FilteredCredentialLister();
$lister->listingQuery()  // Returns query filtered by accessible companies
$lister->columns()       // Same as parent
$lister->completeDataRow($row)  // Same as parent
```

### FilteredCompanyJobLister

```php
$lister = new FilteredCompanyJobLister();
$lister->listingQuery()  // Returns query filtered by accessible companies
```

---

## Support

For questions or issues:
1. Check this documentation first
2. Review implemented examples in `companies.php`, `company.php`, `credentials.php`
3. Enable debug logging: `$_ENV['APP_DEBUG'] = true`
4. Inspect session: `var_dump($_SESSION['user_id'])`

---

**Last Updated**: 2026-06-06  
**Maintained By**: MultiFlexi Development Team
