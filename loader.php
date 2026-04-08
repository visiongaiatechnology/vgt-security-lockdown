<?php
/**
 * Plugin Name: VGT Security Lockdown BETA
 * Description: OMEGA PROTOCOL. Kompromisslose System-Abschottung. Modulare Architektur.
 * Version: 2.0.1
 * Author URI: https://visiongaiatechnology.de
 * License: AGPLv3
 * Requires PHP: 7.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Direkter Zugriff verboten
}

// 1. KERN-KONSTANTEN DEFINIEREN
define('VGT_LOCKDOWN_FILE', __FILE__);
define('VGT_LOCKDOWN_DIR', __DIR__);

// 2. HIGH-PERFORMANCE PSR-4 AUTOLOADER (ZERO-DEPENDENCY)
spl_autoload_register(static function (string $class): void {
    $prefix = 'VGT\\SecurityLockdown\\';
    $base_dir = VGT_LOCKDOWN_DIR . '/src/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// 3. SYSTEM INITIALISIERUNG
\VGT\SecurityLockdown\Bootstrapper::boot();