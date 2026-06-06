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

use Ease\Html\ATag;
use Ease\Html\DivTag;
use Ease\TWB5\Table;
use Ease\TWB5\Widgets\Toggle;
use MultiFlexi\Company;
use MultiFlexi\CompanyUser;
use MultiFlexi\User;

/**
 * User company assignment widget - shows which companies a user is assigned to.
 *
 * @no-named-arguments
 */
class UserCompanyAssignment extends DivTag
{
    public function __construct(User $user, $properties = [])
    {
        $companyEngine = new Company();
        $allCompanies = $companyEngine->listingQuery()->select(['id', 'name', 'slug'], true)->orderBy('name')->fetchAll();

        $companyUser = new CompanyUser();
        $assignedCompanies = [];

        // Get all companies this user is assigned to
        foreach ($companyUser->listingQuery()->where('user_id', $user->getMyKey())->select(['company_id', 'role'])->fetchAll() as $assignment) {
            $assignedCompanies[(int) $assignment['company_id']] = $assignment['role'] ?? 'viewer';
        }

        $assignmentsTable = new Table(null, ['class' => 'table table-hover mb-0', 'id' => 'user-company-assignments-table']);
        $assignmentsTable->addRowHeaderColumns([_('Company'), _('Slug'), _('Role'), _('Assigned')], ['class' => 'thead-light']);

        foreach ($allCompanies as $companyData) {
            $companyId = (int) $companyData['id'];
            $role = $assignedCompanies[$companyId] ?? 'viewer';

            $toggle = new Toggle(
                'assign['.$companyId.']',
                \array_key_exists($companyId, $assignedCompanies),
                (string) $companyId,
                ['class' => 'user-company-assign-toggle', 'data-company-id' => $companyId, 'data-user-id' => $user->getMyKey()],
            );

            $assignmentsTable->addRowColumns([
                new ATag('company.php?id='.$companyId, (string) $companyData['name']),
                (string) ($companyData['slug'] ?? ''),
                $role,
                $toggle,
            ]);
        }

        $csrfToken = $GLOBALS['csrfProtection']->generateToken();

        $card = new \Ease\TWB5\Card(
            new \Ease\Html\DivTag([
                new \Ease\Html\H4Tag(_('Company Access Rights'), ['class' => 'card-title mb-0']),
                new \Ease\Html\SmallTag(_('Enable or disable user access to companies'), ['class' => 'text-muted']),
            ], ['class' => 'd-flex justify-content-between align-items-center mb-3']),
            ['class' => 'shadow-sm border-0 mt-4'],
        );

        $searchBox = new \Ease\Html\InputSearchTag('company_search', '', [
            'placeholder' => _('Search companies...'),
            'class' => 'form-control mb-3',
            'id' => 'user-company-search',
        ]);

        $cardBody = new \Ease\Html\DivTag([$searchBox, new \Ease\Html\DivTag($assignmentsTable, ['class' => 'table-responsive'])], ['class' => 'card-body']);
        $card->addItem($cardBody);

        WebPage::singleton()->addJavaScript(<<<'JS'
            $('#user-company-search').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#user-company-assignments-table tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
JS);

        WebPage::singleton()->addJavaScript(<<<JS
            $('.user-company-assign-toggle').change(function() {
                var toggle = $(this);
                var companyId = toggle.data('company-id');
                var userId = toggle.data('user-id');
                var state = toggle.prop('checked');

                $.post('togglecompanyuser.php', {
                    company_id: companyId,
                    user_id: userId,
                    state: state,
                    csrf_token: '{$csrfToken}'
                }, function(data) {
                    if (data.result === 'success') {
                        // Success
                    } else {
                        alert('Error updating assignment');
                        toggle.bootstrapToggle(state ? 'off' : 'on', true);
                    }
                }, 'json').fail(function() {
                    alert('Request failed');
                    toggle.bootstrapToggle(state ? 'off' : 'on', true);
                });
            });
JS);

        parent::__construct($card, $properties);
    }
}
