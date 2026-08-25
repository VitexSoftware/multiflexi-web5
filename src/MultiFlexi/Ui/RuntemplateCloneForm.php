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

/**
 * Description of RuntemplateCloneForm.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class RuntemplateCloneForm extends SecureForm
{
    public function __construct(\MultiFlexi\RunTemplate $runtemplate)
    {
        $fallbackName = $runtemplate->getDataValue('name');

        if (empty($fallbackName)) {
            $fallbackName = (new \MultiFlexi\LocalizedApplication($runtemplate->getDataValue('app_id')))->getRecordName();
        }

        $clonename = $fallbackName.' '._('Clone');
        parent::__construct(['action' => 'runtemplateclone.php?id='.(string) $runtemplate->getMyKey(), 'class' => 'form-inline']);
        $this->addInput(new \Ease\Html\InputTextTag('clonename', $clonename), _('Save as copy').'&nbsp;', $clonename);
        $this->addItem(new \Ease\TWB5\SubmitButton('💕 '._('Clone'), 'success mb-2', ['type' => 'submit']));
    }

    /**
     * Move items to element root.
     */
    public function finalize(): void
    {
        $contents = $this->formDiv->pageParts;
        $this->emptyContents();
        $this->pageParts = $contents;
        parent::finalize();
    }
}
