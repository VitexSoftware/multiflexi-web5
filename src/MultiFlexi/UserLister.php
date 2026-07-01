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
 * User listing model for DataTable display.
 *
 * Extends DBEngine (instead of Ease\User) so the standard
 * DBDataTable widget can render user records.
 */
class UserLister extends DBEngine
{
    public string $myTable = 'user';
    public string $nameColumn = 'login';
    public ?string $keyword = 'user';

    public function __construct($init = null, array $filter = [])
    {
        $this->createColumn = 'DatCreate';
        $this->lastModifiedColumn = 'DatSave';
        parent::__construct($init, $filter);
    }

    /**
     * {@inheritDoc}
     */
    public function columns($columns = []): array
    {
        return parent::columns([
            'id' => ['name' => 'id', 'type' => 'int', 'hidden' => true, 'label' => _('ID')],
            'login' => ['name' => 'login', 'type' => 'text', 'label' => _('Login'),
                'detailPage' => 'user.php', 'idColumn' => 'id'],
            'firstname' => ['name' => 'firstname', 'type' => 'text', 'label' => _('First Name')],
            'lastname' => ['name' => 'lastname', 'type' => 'text', 'label' => _('Last Name')],
            'email' => ['name' => 'email', 'type' => 'text', 'label' => _('Email')],
            'enabled' => ['name' => 'enabled', 'type' => 'text', 'label' => _('Status')],
            'last_login_at' => ['name' => 'last_login_at', 'type' => 'datetime', 'label' => _('Last Login')],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function completeDataRow(array $dataRowRaw): array|string
    {
        if (isset($dataRowRaw['login'])) {
            $dataRowRaw['login'] = '<a href="user.php?id='.(int) $dataRowRaw['id'].'" class="fw-semibold">'.
                htmlspecialchars((string) $dataRowRaw['login'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'</a>';
        }

        if (isset($dataRowRaw['email']) && !empty($dataRowRaw['email'])) {
            $dataRowRaw['email'] = '<a href="mailto:'.htmlspecialchars((string) $dataRowRaw['email'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'">'.
                htmlspecialchars((string) $dataRowRaw['email'], \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'</a>';
        }

        if (isset($dataRowRaw['enabled'])) {
            $dataRowRaw['enabled'] = (bool) $dataRowRaw['enabled']
                ? '<span class="badge bg-success">'._('Active').'</span>'
                : '<span class="badge bg-secondary">'._('Disabled').'</span>';
        }

        if (isset($dataRowRaw['last_login_at'])) {
            $dataRowRaw['last_login_at'] = !empty($dataRowRaw['last_login_at'])
                ? (new \DateTime($dataRowRaw['last_login_at']))->format('Y-m-d H:i')
                : '<span class="text-muted">'._('Never').'</span>';
        }

        return parent::completeDataRow($dataRowRaw);
    }

    public function columnDefs(): string
    {
        return '';
    }

    public function foterCallback()
    {
        return '';
    }
}
