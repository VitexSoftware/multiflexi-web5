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
 * Logger scoped to the current session user.
 *
 * Unlike the base Logger, every query produced by this engine is
 * automatically restricted to rows whose user_id matches the
 * logged-in user.  This makes the filter tamper-proof — the
 * client cannot alter or remove it via URL parameters.
 *
 * Used on home.php for "My Recent Activity Log".
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class UserLogger extends Logger
{
    /**
     * {@inheritDoc}
     *
     * Adds a WHERE user_id = <current user> clause so that only
     * the logged-in user's log entries are returned.
     */
    public function listingQuery(): \Envms\FluentPDO\Queries\Select
    {
        return parent::listingQuery()->where(
            'log.user_id',
            \Ease\Shared::user()->getUserID(),
        );
    }
}
