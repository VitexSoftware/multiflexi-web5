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
 * Description of CustomAppConfigForm.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class RuntemplateConfigForm extends EngineForm
{
    public function __construct(\MultiFlexi\RunTemplate $engine)
    {
        parent::__construct($engine, null, ['method' => 'post', 'action' => 'runtemplate.php', 'enctype' => 'multipart/form-data']);

        $defaults = $engine->getAppEnvironment();
        $appRequirements = $engine->getApplication()->getRequirements();
        $customized = $engine->getRuntemplateEnvironment();

        $fieldsOf = [];
        $fieldSource = [];
        $credSource = [];

        $credentialProvidersAvailable = \MultiFlexi\Requirement::getCredentialProviders();
        $credentialTypesAvailable = \MultiFlexi\Requirement::getCredentialTypes($engine->getCompany());
        $credentialsAvailable = \MultiFlexi\Requirement::getCredentials($engine->getCompany());
        $credentialsAssigned = $engine->getAssignedCredentials();

        $credData = [];

        $this->addCSS(<<<'CSS'
            .runtemplate-config-form .form-group { margin-bottom: 0.75rem; padding: 0.5rem; border-radius: 4px; transition: background-color 0.2s; }
            .runtemplate-config-form .form-group:hover { background-color: #f8f9fa; }
            .runtemplate-config-form label { font-size: 0.9rem; margin-bottom: 0.2rem; display: block; }
            .runtemplate-config-form .form-control-sm { height: calc(1.5em + 0.5rem + 2px); padding: 0.25rem 0.5rem; font-size: 0.875rem; }
            .required-field { border-left: 3px solid #dc3545 !important; }
            .secret-field { border-left: 3px solid #343a40 !important; }
            .expiring-field { border-left: 3px solid #ffc107 !important; }
            .required-field.secret-field { border-left: 3px solid #dc3545 !important; border-right: 3px solid #343a40 !important; }
            .required-field.expiring-field { border-left: 3px solid #dc3545 !important; border-right: 3px solid #ffc107 !important; }
            .field-flags { display: inline; margin-left: 0.4rem; }
            .field-flags .badge { font-size: 0.7rem; margin-left: 0.15rem; vertical-align: middle; }
CSS);
        $this->addTagClass('runtemplate-config-form');

        $this->addItem(new RuntemplateRequirementsChoser($engine));

        $appFields = \MultiFlexi\Conffield::getAppConfigs($engine->getApplication());
        $runTemplateFields = $engine->getEnvironment();

        $appFields->takeValues($customized);

        // Configuration option categories as defined by the application schema
        // (multiflexi-core schema/application.json: environment.*.category).
        $categoryOrder = ['API', 'Database', 'Behavior', 'Security', 'Other'];
        $categoryOf = $this->readFieldCategories($engine->getApplication());

        $categoryBuckets = [];

        foreach ($categoryOrder as $categoryName) {
            $categoryBuckets[$categoryName] = new \Ease\Html\DivTag(null, ['class' => 'config-category-fields']);
        }

        foreach ($appFields as $fieldName => $field) {
            // Prefer the category stored with the field; fall back to the app
            // definition file for apps imported before the category column existed.
            $fieldCategory = $field->getCategory();

            if ($fieldCategory === '' || !isset($categoryBuckets[$fieldCategory])) {
                $fieldCategory = $categoryOf[$fieldName] ?? 'Other';
            }

            if (!isset($categoryBuckets[$fieldCategory])) {
                $fieldCategory = 'Other';
            }

            $bucket = $categoryBuckets[$fieldCategory];

            $inputCaption = new \Ease\Html\StrongTag($fieldName);
            $isBool = $field->getType() === 'bool';

            if ($isBool) {
                $input = new \Ease\TWB5\Widgets\Toggle($fieldName, $field->getValue() === 'true', 'true', ['data-size' => 'small']);
            } elseif ($field->isMultiLine()) {
                $input = new \Ease\Html\TextareaTag($fieldName, $field->getValue(), ['class' => 'form-control form-control-sm', 'rows' => 4]);
            } else {
                $input = new \Ease\Html\InputTag($fieldName, $field->getValue(), ['type' => $field->getType(), 'class' => 'form-control form-control-sm']);
            }

            $runTemplateField = $runTemplateFields->getFieldByCode($fieldName);

            if ($runTemplateField) { // Filed by Credential
                $runTemplateFieldSource = $runTemplateField->getSource();

                if (!empty($runTemplateFieldSource) && \Ease\Euri::isValid($runTemplateFieldSource)) {
                    try {
                        $credential = \Ease\Euri::toObject($runTemplateFieldSource);
                    } catch (\InvalidArgumentException $e) {
                        $credential = null;
                    }

                    if ($credential && ($credential::class === 'MultiFlexi\\Credential') && $credential->getMyKey()) {
                        $credentialType = $credential->getCredentialType();

                        $credentialLink = new \Ease\Html\ATag('credential.php?id='.$credential->getMyKey(), new \Ease\Html\SmallTag($credential->getRecordName()));

                        $formIcon = new \Ease\Html\ImgTag('images/'.$runTemplateField->getLogo(), (string) $credentialType->getRecordName(), ['height' => 20, 'title' => $credentialType->getRecordName()]);

                        $credentialTypeLink = new \Ease\Html\ATag('credentialtype.php?id='.$credentialType->getMyKey(), $formIcon);

                        $inputCaption = new \Ease\Html\SpanTag([$credentialTypeLink, new \Ease\Html\StrongTag($fieldName), '&nbsp;', $credentialLink]);

                        if (!$isBool) {
                            $input->setTagProperty('disabled', '1');
                            $input->setValue($credential->getDataValue($fieldName));
                        }

                        $field->setDescription($credentialType->getFields()->getField($fieldName)->getDescription());
                    }
                }

                if ($isBool) {
                    $formGroup = $bucket->addItem(new \Ease\Html\DivTag(
                        [new \Ease\Html\LabelTag($fieldName, $inputCaption, ['class' => 'form-label']), $input],
                        ['class' => 'mb-3'],
                    ));
                } else {
                    $formGroup = $bucket->addItem(new \Ease\TWB5\InputGroup($inputCaption, $input, (string) $runTemplateField->getValue()));
                }
            } else { // Simple Fields
                if ($isBool) {
                    $formGroup = $bucket->addItem(new \Ease\Html\DivTag(
                        [new \Ease\Html\LabelTag($fieldName, $fieldName, ['class' => 'form-label']), $input],
                        ['class' => 'mb-3'],
                    ));
                } else {
                    $formGroup = $bucket->addItem(new \Ease\TWB5\InputGroup($fieldName, $input, (string) $field->getDefaultValue()));
                }
            }

            $flags = new \Ease\Html\SpanTag(null, ['class' => 'field-flags']);
            $styleTarget = ($formGroup instanceof \Ease\TWB5\InputGroup) ? $formGroup->inputGroup : $formGroup;

            if ($field->isRequired()) {
                $styleTarget->addTagClass('required-field');
                $flags->addItem(new \Ease\TWB5\Badge('danger', _('required')));
            }

            if ($field->isSecret()) {
                $styleTarget->addTagClass('secret-field');
                $flags->addItem(new \Ease\TWB5\Badge('dark', '🔒 '._('secret')));
            }

            if ($field->isExpiring()) {
                $styleTarget->addTagClass('expiring-field');
                $flags->addItem(new \Ease\TWB5\Badge('warning', '⏳ '._('expiring')));
            }

            if ($field->isMultiLine()) {
                $flags->addItem(new \Ease\TWB5\Badge('info', _('multiline')));
            }

            if (!empty($flags->pageParts)) {
                $formGroup->addItem($flags);
            }

            $hint = $field->getHint();

            if (!empty($hint)) {
                $formGroup->addItem(new \Ease\Html\SmallTag($hint, ['class' => 'form-text text-muted']));
            }
        }

        // $this->addItem( new RuntemplateTopicsChooser('topics', $engine)); //TODO

        // Lay the categorised fields out with a Scrollspy sidebar for navigation.
        $categoryIcons = [
            'API' => 'hdd-network',
            'Database' => 'database',
            'Behavior' => 'sliders',
            'Security' => 'shield-lock',
            'Other' => 'three-dots',
        ];

        $categoryNav = new \Ease\Html\DivTag(null, ['class' => 'list-group', 'id' => 'configCategoryNav']);
        $categorySections = new \Ease\Html\DivTag(null, ['class' => 'config-category-sections', 'tabindex' => '0']);
        $firstCategory = true;

        foreach ($categoryOrder as $categoryName) {
            if (empty($categoryBuckets[$categoryName]->pageParts)) {
                continue; // no fields in this category
            }

            $sectionId = 'cfg-cat-'.$categoryName;
            $heading = new \Ease\TWB5\Widgets\BsIcon($categoryIcons[$categoryName]).'&nbsp;'._($categoryName);

            $categoryNav->addItem(new \Ease\Html\ATag(
                '#'.$sectionId,
                $heading,
                ['class' => 'list-group-item list-group-item-action'.($firstCategory ? ' active' : '')],
            ));

            $categorySections->addItem(new \Ease\Html\DivTag([
                new \Ease\Html\H4Tag($heading, ['class' => 'config-category-title']),
                $categoryBuckets[$categoryName],
            ], ['id' => $sectionId, 'class' => 'config-category-section']));

            $firstCategory = false;
        }

        $layoutRow = new \Ease\TWB5\Row();
        $layoutRow->addColumn(3, new \Ease\Html\DivTag($categoryNav, ['class' => 'config-category-nav']));
        $layoutRow->addColumn(9, $categorySections);
        $this->addItem($layoutRow);

        $this->addCSS(<<<'CSS'
            .config-category-nav { position: sticky; top: 80px; }
            .config-category-section { scroll-margin-top: 80px; padding-top: 0.5rem; }
            .config-category-section + .config-category-section { margin-top: 1.5rem; }
            .config-category-title { margin: 0.25rem 0 0.75rem; padding-bottom: 0.3rem; border-bottom: 1px solid #dee2e6; }
CSS);
        WebPage::singleton()->addJavaScript(<<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    if (window.bootstrap && bootstrap.ScrollSpy) {
        new bootstrap.ScrollSpy(document.body, { target: '#configCategoryNav', rootMargin: '0px 0px -40%' });
    }
});
JS);

        $this->addItem(new \Ease\Html\InputHiddenTag('app_id', $engine->getDataValue('app_id')));
        $this->addItem(new \Ease\Html\InputHiddenTag('company_id', $engine->getDataValue('company_id')));

        $saveRow = new \Ease\TWB5\Row();
        $saveColumn = $saveRow->addColumn(8, new \Ease\TWB5\SubmitButton(_('Save'), 'success btn-lg w-100'));
        $saveRow->addColumn(4, new \Ease\TWB5\LinkButton('actions.php?id='.$engine->getMyKey(), '🛠️&nbsp;'._('Actions'), 'secondary btn-lg w-100'));

        $appSetupCommand = $engine->getApplication()->getDataValue('setup');

        if (!empty($appSetupCommand)) {
            $saveColumn->addItem(new \Ease\TWB5\Alert('info', 'ℹ️&nbsp;'._('After saving configuration, the following setup command will be executed:').'<br><code>'.htmlspecialchars((string) $appSetupCommand, \ENT_QUOTES | \ENT_HTML5, 'UTF-8').'</code>'));
        }

        $this->addItem($saveRow);
    }

    /**
     * Build a field-name → category map from the application definition file.
     *
     * Used as a fallback for applications imported before the conffield
     * category column existed (the category is then read straight from the
     * *.multiflexi.app.json definition on disk).
     *
     * @return array<string, string>
     */
    private function readFieldCategories(\MultiFlexi\Application $application): array
    {
        $categories = [];
        $deffile = (string) $application->getDataValue('deffile');

        if ($deffile === '' || !is_file($deffile)) {
            return $categories;
        }

        $appDef = json_decode((string) file_get_contents($deffile), true);

        if (!\is_array($appDef) || empty($appDef['environment']) || !\is_array($appDef['environment'])) {
            return $categories;
        }

        foreach ($appDef['environment'] as $envKey => $envCfg) {
            if (\is_array($envCfg) && !empty($envCfg['category'])) {
                $categories[$envKey] = (string) $envCfg['category'];
            }
        }

        return $categories;
    }
}
