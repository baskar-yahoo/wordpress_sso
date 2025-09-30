<?php

namespace Webtrees\WordPressSso;

// For webtrees 2.1 and later.
if (!defined('WT_VERSION')) {
    return;
}

// The composer autoloader.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}

return new WordPressSsoModule();
