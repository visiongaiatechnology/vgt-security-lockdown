<?php
declare(strict_types=1);

namespace VGT\SecurityLockdown;

if (!defined('ABSPATH')) {
    exit;
}

interface ModuleInterface {
    public function register(): void;
}