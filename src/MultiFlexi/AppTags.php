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
 * Tags declared by application definitions (apps.tags, comma separated).
 *
 * Tags are matched case-insensitively ("Ipex" and "IPEX" are one tag); the
 * first spelling found is the one displayed.
 *
 * @no-named-arguments
 */
class AppTags
{
    /**
     * URL / data source parameter carrying the selected tags (comma separated).
     */
    public const PARAM = 'apptags';

    /**
     * @var null|array<int, array<int, string>> app ID => its tags
     */
    private static ?array $appTags = null;

    /**
     * All distinct tags, sorted case-insensitively.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $tags = [];

        foreach (self::byApp() as $appTags) {
            foreach ($appTags as $tag) {
                $tags[mb_strtolower($tag)] ??= $tag;
            }
        }

        ksort($tags, \SORT_NATURAL);

        return array_values($tags);
    }

    /**
     * IDs of applications having every one of the given tags.
     *
     * @param array<int, string> $tags
     *
     * @return array<int, int>
     */
    public static function appIdsWithAll(array $tags): array
    {
        $wanted = array_unique(array_map('mb_strtolower', self::parse($tags)));
        $ids = [];

        foreach (self::byApp() as $appId => $appTags) {
            $have = array_map('mb_strtolower', $appTags);

            if (array_diff($wanted, $have) === []) {
                $ids[] = $appId;
            }
        }

        return $ids;
    }

    /**
     * Normalise a tag list given as array or comma separated string.
     *
     * @param array<int, string>|string|null $tags
     *
     * @return array<int, string>
     */
    public static function parse($tags): array
    {
        if ($tags === null) {
            return [];
        }

        $list = \is_array($tags) ? $tags : explode(',', (string) $tags);

        return array_values(array_unique(array_filter(array_map(static fn ($tag): string => trim((string) $tag), $list), static fn (string $tag): bool => $tag !== '')));
    }

    /**
     * Tags selected in the current request (?apptags=A,B).
     *
     * @return array<int, string>
     */
    public static function fromRequest(): array
    {
        $value = $_GET[self::PARAM] ?? null;

        return self::parse(\is_array($value) || \is_string($value) ? $value : null);
    }

    /**
     * Query string fragment ("&apptags=…") keeping the current tag selection in links.
     */
    public static function queryFragment(): string
    {
        $tags = self::fromRequest();

        return $tags === [] ? '' : '&'.http_build_query([self::PARAM => implode(',', $tags)]);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function byApp(): array
    {
        if (self::$appTags === null) {
            self::$appTags = [];
            $rows = (new Application())->getFluentPDO()->from('apps')->select(['id', 'tags'], true)->fetchAll();

            foreach ($rows as $row) {
                self::$appTags[(int) $row['id']] = self::parse($row['tags'] ?? '');
            }
        }

        return self::$appTags;
    }
}
