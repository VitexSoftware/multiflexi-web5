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
 * Narrows a DataTable lister to records of applications having all selected tags.
 *
 * The selection travels to ajax2db.php as the "_apptags" condition (added to
 * $this->filter by setAppTags(), so DBDataTable puts it in the data source URL).
 *
 * @no-named-arguments
 */
trait AppTagFilterable
{
    /**
     * @var array<int, string>
     */
    public array $appTags = [];

    /**
     * @param array<int, string> $tags
     */
    public function setAppTags(array $tags): void
    {
        $this->appTags = AppTags::parse($tags);

        if ($this->appTags === []) {
            unset($this->filter['_apptags']);
        } else {
            $this->filter['_apptags'] = implode(',', $this->appTags);
        }
    }

    /**
     * Take the "_apptags" condition out of DataTable conditions (it is no column).
     *
     * @param array<string, mixed> $conditions
     *
     * @return array<string, mixed>
     */
    protected function takeAppTagsFromConditions(array $conditions): array
    {
        if (\array_key_exists('_apptags', $conditions)) {
            $this->appTags = AppTags::parse(\is_string($conditions['_apptags']) ? $conditions['_apptags'] : null);
            unset($conditions['_apptags']);
        }

        return $conditions;
    }

    /**
     * @param string $appIdColumn qualified application ID column, e.g. "job.app_id"
     */
    protected function applyAppTagFilter(\Envms\FluentPDO\Queries\Select $query, string $appIdColumn): \Envms\FluentPDO\Queries\Select
    {
        if ($this->appTags !== []) {
            $appIds = AppTags::appIdsWithAll($this->appTags);
            $query->where($appIds === [] ? '1=0' : $appIdColumn.' IN ('.implode(',', $appIds).')');
        }

        return $query;
    }
}
