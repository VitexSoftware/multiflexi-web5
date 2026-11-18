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
 * Read-only overview of what a Credential Prototype offers.
 *
 * @no-named-arguments
 */
class CredentialPrototypeInfo extends \Ease\TWB5\Card
{
    public function __construct(\MultiFlexi\credentialTypeInterface $prototype)
    {
        parent::__construct(null, ['class' => 'mb-3']);

        $this->addItem(new \Ease\Html\H4Tag(htmlspecialchars($prototype->name(), \ENT_QUOTES), ['class' => 'card-header']));
        $body = new \Ease\Html\DivTag(null, ['class' => 'card-body']);

        $description = $prototype->description();

        if ($description !== '') {
            $body->addItem(new \Ease\Html\PTag(htmlspecialchars($description, \ENT_QUOTES), ['class' => 'lead']));
        }

        $meta = new \Ease\Html\DlTag(null, ['class' => 'row']);
        $rows = [
            _('Class') => htmlspecialchars(\Ease\Functions::baseClassName($prototype), \ENT_QUOTES),
            _('UUID') => htmlspecialchars($prototype->uuid(), \ENT_QUOTES),
        ];

        foreach (['version' => _('Version'), 'url' => _('URL'), 'homepage' => _('Homepage'), 'tags' => _('Tags')] as $key => $label) {
            $value = method_exists($prototype, 'getDataValue') ? $prototype->getDataValue($key) : null;

            if (!empty($value)) {
                $rows[$label] = preg_match('#^https?://#i', (string) $value) ? new \Ease\Html\ATag(htmlspecialchars((string) $value, \ENT_QUOTES), htmlspecialchars((string) $value, \ENT_QUOTES), ['target' => '_blank', 'rel' => 'noopener noreferrer']) : htmlspecialchars((string) $value, \ENT_QUOTES);
            }
        }

        $rows[_('Availability check')] = (new \ReflectionMethod($prototype, 'checkAvailability'))->getDeclaringClass()->getName() === \MultiFlexi\CredentialProtoType::class
            ? _('Not implemented (never blocks jobs)')
            : _('Implemented (live check before job start)');

        foreach ($rows as $label => $value) {
            $meta->addItem(new \Ease\Html\DtTag($label, ['class' => 'col-sm-3']));
            $meta->addItem(new \Ease\Html\DdTag($value, ['class' => 'col-sm-9']));
        }

        $body->addItem($meta);

        $body->addItem(self::fieldsTable(_('Fields provided'), $prototype->fieldsProvided()));
        $internal = $prototype->fieldsInternal();

        if (\count($internal->getFields())) {
            $body->addItem(self::fieldsTable(_('Prototype settings'), $internal));
        }

        $this->addItem($body);
    }

    private static function fieldsTable(string $title, \MultiFlexi\ConfigFields $fields): \Ease\Container
    {
        $box = new \Ease\Container(new \Ease\Html\H5Tag(htmlspecialchars($title, \ENT_QUOTES)));
        $table = new \Ease\Html\TableTag(null, ['class' => 'table table-sm table-striped']);
        $table->addRowHeaderColumns([_('Code'), _('Type'), _('Flags'), _('Name'), _('Description')]);

        foreach ($fields->getFields() as $field) {
            $flags = [];

            if ($field->isRequired()) {
                $flags[] = _('required');
            }

            if ($field->isSecret()) {
                $flags[] = _('secret');
            }

            $table->addRowColumns([
                new \Ease\Html\SpanTag(htmlspecialchars($field->getCode(), \ENT_QUOTES), ['style' => 'font-family: monospace']),
                htmlspecialchars($field->getType(), \ENT_QUOTES),
                implode(', ', $flags),
                htmlspecialchars($field->getName(), \ENT_QUOTES),
                htmlspecialchars(trim($field->getDescription().' '.$field->getHint()), \ENT_QUOTES),
            ]);
        }

        $box->addItem($table);

        return $box;
    }
}
