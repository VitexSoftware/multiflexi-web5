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
 * Form for editing an EventRule (event-to-RunTemplate mapping).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class EventRuleForm extends SecureForm
{
    /**
     * @param \MultiFlexi\EventRule $eventRule      EventRule object
     * @param array                 $formProperties Additional form properties
     */
    public function __construct(\MultiFlexi\EventRule $eventRule, array $formProperties = [])
    {
        $row1 = new \Ease\TWB5\Row();
        $row1->addColumn(4, new \Ease\TWB5\FormGroup(_('Event Source'), new EventSourceSelect('event_source_id', (int) $eventRule->getDataValue('event_source_id'))));
        $row1->addColumn(4, new \Ease\TWB5\FormGroup(_('Evidence Pattern'), new \Ease\Html\InputTextTag('evidence', $eventRule->getDataValue('evidence'), [], _('e.g. faktura-vydana or leave empty for any'))));
        $row1->addColumn(4, new \Ease\TWB5\FormGroup(_('Operation'), new OperationSelect('operation', $eventRule->getDataValue('operation') ?: 'any')));

        $row2 = new \Ease\TWB5\Row();
        $row2->addColumn(4, new \Ease\TWB5\FormGroup(_('RunTemplate ID'), new \Ease\Html\InputNumberTag('runtemplate_id', (string) $eventRule->getDataValue('runtemplate_id'))));
        $row2->addColumn(4, new \Ease\TWB5\FormGroup(_('Priority'), new \Ease\Html\InputNumberTag('priority', (string) ($eventRule->getDataValue('priority') ?: '0'))));
        $row2->addColumn(4, new \Ease\TWB5\FormGroup(
            _('Enabled'),
            new \Ease\Html\SelectTag('enabled', ['0' => _('No'), '1' => _('Yes')], (string) ($eventRule->getDataValue('enabled') ?: '1')),
        ));

        $row3 = new \Ease\TWB5\Row();
        $row3->addColumn(12, new \Ease\TWB5\FormGroup(
            _('Environment Variable Mapping (JSON)'),
            new \Ease\Html\TextareaTag('env_mapping', $eventRule->getDataValue('env_mapping') ?: '{"RECORD_ID": "recordid", "EVIDENCE": "evidence", "OPERATION": "operation"}', ['rows' => '4', 'class' => 'form-control']),
        ));

        $formContents = [$row1, $row2, $row3];

        parent::__construct(['action' => 'eventrule.php', 'method' => 'POST'], $formContents);

        $submitRow = new \Ease\TWB5\Row();

        $submitRow->addColumn(10, new \Ease\TWB5\SubmitButton('🍏 '._('Apply'), 'primary btn-lg w-100', ['title' => _('Apply changes')]));

        if (null === $eventRule->getMyKey()) {
            $submitRow->addColumn(2, new \Ease\TWB5\SubmitButton('⚰️ '._('Remove').' !', 'disabled btn-lg w-100', ['disabled' => 'true']));
        } else {
            $this->addItem(new \Ease\Html\InputHiddenTag('id', $eventRule->getMyKey()));

            if (WebPage::getRequestValue('remove') === 'true') {
                $submitRow->addColumn(2, new \Ease\TWB5\LinkButton('eventrule.php?delete='.$eventRule->getMyKey(), '⚰️ '._('Remove').' !', 'danger btn-lg w-100'));
            } else {
                $submitRow->addColumn(2, new \Ease\TWB5\LinkButton('eventrule.php?id='.$eventRule->getMyKey().'&remove=true', '⚰️ '._('Remove').' ?', 'warning btn-lg w-100'));
            }
        }

        $this->addItem($submitRow);
    }

    public function afterAdd(): void
    {
    }
}
