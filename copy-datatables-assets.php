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

/*
 * Copy DataTables + Buttons front-end assets from vendor/ into the web-served
 * src/ directories. Run automatically by composer install/update. The exact,
 * mutually-compatible versions (DataTables 2.x core + Buttons 3.x) are pinned
 * in composer.lock so every checkout produces identical assets.
 */
$root = __DIR__;

$copies = [
    'vendor/datatables.net/datatables.net/js/dataTables.js' => 'src/js/jquery.dataTables.js',
    'vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.js' => 'src/js/dataTables.bootstrap5.js',
    'vendor/datatables.net/datatables.net-bs5/css/dataTables.bootstrap5.css' => 'src/css/dataTables.bootstrap5.css',
    'vendor/datatables.net/datatables.net-buttons/js/dataTables.buttons.js' => 'src/js/dataTables.buttons.js',
    'vendor/datatables.net/datatables.net-buttons/js/buttons.html5.js' => 'src/js/buttons.html5.js',
    'vendor/datatables.net/datatables.net-buttons/js/buttons.print.js' => 'src/js/buttons.print.js',
    'vendor/datatables.net/datatables.net-buttons/js/buttons.colVis.js' => 'src/js/buttons.colVis.js',
    'vendor/datatables.net/datatables.net-buttons-bs5/js/buttons.bootstrap5.js' => 'src/js/buttons.bootstrap5.js',
    'vendor/datatables.net/datatables.net-buttons-bs5/css/buttons.bootstrap5.css' => 'src/css/buttons.bootstrap5.css',
];

$status = 0;

foreach ($copies as $from => $to) {
    $src = $root.'/'.$from;
    $dst = $root.'/'.$to;

    if (!is_file($src)) {
        fwrite(\STDERR, "[copy-datatables-assets] missing source: {$from}\n");
        $status = 1;

        continue;
    }

    if (!copy($src, $dst)) {
        fwrite(\STDERR, "[copy-datatables-assets] failed: {$from} -> {$to}\n");
        $status = 1;

        continue;
    }

    echo "[copy-datatables-assets] {$to}\n";
}

exit($status);
