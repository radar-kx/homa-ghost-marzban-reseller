<?php

declare(strict_types=1);

$installed = __DIR__.'/storage/app/installed.lock';

if (! is_file($installed)) {
    header('Location: install.php', true, 302);
    exit;
}

require __DIR__.'/public/index.php';
