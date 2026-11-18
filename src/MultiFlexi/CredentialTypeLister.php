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
 * Description of CredentialTypeLister.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class CredentialTypeLister extends CredentialType
{
    /**
     * @param array $columns
     *
     * @return array
     */
    public function columns($columns = [])
    {
        return parent::columns([
            ['name' => 'id', 'type' => 'text', 'label' => _('ID'),
                'detailPage' => 'credentialtype.php', 'valueColumn' => 'credential_type.id', 'idColumn' => 'credential_type.id', ],
            ['name' => 'logo', 'type' => 'text', 'label' => _('Prototype'), 'searchable' => false],
            ['name' => 'name', 'type' => 'text', 'label' => _('Name')],
            ['name' => 'uuid', 'type' => 'text', 'label' => _('UUID')],
            ['name' => 'company_id', 'type' => 'text', 'label' => _('Company')],
        ]);
    }

    #[\Override]
    public function completeDataRow(array $dataRowRaw): array
    {
        $this->setData($dataRowRaw);
        $data = parent::completeDataRow($dataRowRaw);

        // This lister instance is reused for every row and getPrototype() caches the
        // first prototype it resolves, so resolve the prototype on a fresh object per
        // row (otherwise every type without its own logo shows the first row's logo).
        $rowType = new CredentialType();
        $rowType->setData($dataRowRaw);
        $helper = $rowType->getPrototype();

        $prototypeInfo = self::prototypes()[(string) ($dataRowRaw['prototype'] ?? '')] ?? null;

        if (empty($data['logo'])) {
            $helperLogo = $helper ? (string) $helper->logo() : '';

            // A prototype without a PHP class resolves to the generic "Common" logo;
            // prefer the logo stored with the prototype record in that case.
            if (($helperLogo === '' || $helperLogo === 'CommonCredentialType.svg') && !empty($prototypeInfo['logo'])) {
                $helperLogo = $prototypeInfo['logo'];
            }

            // An empty name makes logoimage.php serve its placeholder icon
            $data['logo'] = $helperLogo;
        }

        if (empty($data['name']) && $helper) {
            $data['name'] = $helper->name();
        }

        $data['name'] = (string) ($data['name'] ?? '');

        // Bare filenames (dev tree or deb-installed credential-prototype logos under
        // /usr/share/multiflexi/images) are served through logoimage.php.
        $logoSrc = str_starts_with((string) $data['logo'], 'data:')
            ? $data['logo']
            : 'logoimage.php?file='.rawurlencode((string) $data['logo']);

        // The logo column links to the credential prototype the type is based on
        $prototypeCode = (string) ($dataRowRaw['prototype'] ?? '');
        $prototypeId = self::prototypes()[$prototypeCode]['id'] ?? null;
        $logoImg = new \Ease\Html\ImgTag($logoSrc, $data['name'], ['title' => htmlspecialchars($prototypeCode !== '' ? $prototypeCode : $data['name']), 'style' => 'height: 50px;']);

        $data['logo'] = (string) ($prototypeId !== null
            ? new \Ease\Html\ATag(
                'credentialprototype.php?id='.$prototypeId,
                [$logoImg, ' ', new \Ease\Html\SmallTag(htmlspecialchars($prototypeCode))],
                ['title' => _('Show credential prototype'), 'class' => 'text-decoration-none'],
            )
            : new \Ease\Html\ATag('credentialtype.php?id='.$this->getMyKey(), $logoImg));

        $data['company_id'] = (string) new Ui\CompanyLinkButton(new Company($dataRowRaw['company_id']), ['style' => 'height: 50px;']);

        return $data;
    }
    /**
     * Credential prototypes by code: ID and logo file (loaded once per request).
     *
     * @return array<string, array{id: int, logo: string}>
     */
    private static function prototypes(): array
    {
        static $prototypes = null;

        if ($prototypes === null) {
            $prototypes = [];

            foreach ((new CredentialProtoType())->getColumnsFromSQL(['id', 'code', 'logo']) as $row) {
                $prototypes[(string) $row['code']] = ['id' => (int) $row['id'], 'logo' => (string) ($row['logo'] ?? '')];
            }
        }

        return $prototypes;
    }
}
