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

use Ease\Html\InputEmailTag;
use Ease\Html\InputHiddenTag;
use Ease\Html\InputTextTag;
use Ease\TWB5\SubmitButton;
use Ease\TWB5\Widgets\Toggle;

/**
 * Registered AbraFlexi instance editor Form.
 *
 * @author
 *
 * @no-named-arguments
 */
class CompanyEditorForm extends EngineForm
{
    /**
     * May the current user enable or disable a company?
     */
    public static function canChangeEnabled(): bool
    {
        return !\MultiFlexi\Security\RbacHelpers::isAvailable() || \MultiFlexi\Security\RbacHelpers::isCurrentUserAdmin();
    }

    public function afterAdd(): void
    {
        $this->setTagProperty('enctype', 'multipart/form-data');
        $this->addInput(new InputTextTag('name'), _('Company name'));
        $this->addInput(new InputTextTag('slug', null, ['maxlength' => 10, 'onkeyup' => 'this.value = this.value.toUpperCase();']), _('Company slug'));
        $this->addInput(new InputTextTag('ic'), _('Organization ID'));
        $this->addInput(new InputEmailTag('email'), _('Send notification to'));
        $this->addInput(new CustomerSelect('customer'), _('Customer'));
        $imgInput = $this->addInput(new \Ease\Html\InputFileTag('imageraw'), _('Company Logo'));

        if (\Ease\Shared::cfg('ZABBIX_SERVER')) {
            $this->addInput(new InputTextTag('zabbix_host'), _('Zabbix Host'), \Ease\Shared::cfg('ZABBIX_HOST'), sprintf(_('Override the default zabbix host %s'), \Ease\Shared::cfg('ZABBIX_HOST')));
        }

        // Enabling decides about running jobs and billing, so only an administrator may change it
        if (!empty($this->engine->getMyKey()) && self::canChangeEnabled()) {
            $this->addInput(new Toggle('enabled', (bool) $this->engine->getDataValue('enabled'), '1'), _('Enabled'), '', _('A disabled company runs no jobs and is not billed'));
        }

        $this->addItem(new SubmitButton(_('Save'), 'success btn-lg w-100', ['title' => _('Save company data'), 'id' => 'savecompanybutton']));

        if (null !== $this->engine->getDataValue('id')) {
            $this->addItem(new InputHiddenTag('id'));
        }
    }
}
