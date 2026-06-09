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
 * Reusable slide-in filter drawer for plain table listings.
 *
 * Wraps a GET form inside a Bootstrap Offcanvas so listing pages can offer
 * filters without consuming page space. Pair it with triggerButton() placed
 * above the table.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class FilterOffCanvas extends \Ease\TWB5\OffCanvas
{
    /**
     * @param string $id        Unique offcanvas ID
     * @param array  $fields    Ready-made form rows (label + input markup)
     * @param string $action    Form action (current page when empty)
     * @param mixed  $title     Drawer title (defaults to "Filters")
     * @param string $placement start|end|top|bottom
     */
    public function __construct(string $id, array $fields, string $action = '', $title = null, string $placement = 'end')
    {
        parent::__construct($id, $title ?? _('Filters'), null, $placement);

        $form = new \Ease\TWB5\Form(['method' => 'get', 'action' => $action]);

        foreach ($fields as $field) {
            $form->addItem($field);
        }

        $form->addItem(new \Ease\Html\DivTag([
            new \Ease\TWB5\SubmitButton(new \Ease\TWB5\Widgets\BsIcon('funnel').'&nbsp;'._('Apply'), 'primary'),
            new \Ease\TWB5\LinkButton($action === '' ? \Ease\Document::phpSelf() : $action, _('Reset'), 'outline-secondary'),
        ], ['class' => 'd-grid gap-2 mt-3']));

        $this->body->addItem($form);
    }

    /**
     * Labelled text input wrapped in a Bootstrap mb-3 form row.
     *
     * @param string $name  Field name
     * @param string $label Field label
     * @param string $value Current value
     */
    public static function textField(string $name, string $label, string $value = ''): \Ease\Html\DivTag
    {
        return new \Ease\Html\DivTag([
            new \Ease\Html\LabelTag($name, $label, ['class' => 'form-label']),
            new \Ease\Html\InputTextTag($name, $value, ['class' => 'form-control', 'id' => $name]),
        ], ['class' => 'mb-3']);
    }
}
