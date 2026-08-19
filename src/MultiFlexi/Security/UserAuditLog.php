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

/**
 * AuditLogEntry scoped to the current session user.
 *
 * Every query produced by this engine is automatically restricted to rows
 * whose user_id matches the logged-in user — server-side, so it cannot be
 * bypassed via URL tampering. Used on home.php for "My Recent Actions",
 * the counterpart of MultiFlexi\UserLogger for the structured
 * create/update/delete audit trail.
 */
class UserAuditLog extends AuditLogEntry
{
    public function listingQuery(): \Envms\FluentPDO\Queries\Select
    {
        return parent::listingQuery()->where(
            'security_audit_log.user_id',
            \Ease\Shared::user()->getUserID(),
        );
    }
}
