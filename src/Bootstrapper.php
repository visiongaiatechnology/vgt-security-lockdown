<?php
declare(strict_types=1);

namespace VGT\SecurityLockdown;

if (!defined('ABSPATH')) {
    exit;
}

final class Bootstrapper {
    private static bool $is_booted = false;

    public static function boot(): void {
        if (self::$is_booted) return;

        $config = new ConfigManager();
        $auth   = new AuthHandler($config);
        
        $modules = [
            new Firewall($config, $auth),
            new AdminUI($config),
            new CLICommand($config),
            $auth // Auth registriert falls in Zukunft Hooks nötig sind
        ];

        foreach ($modules as $module) {
            $module->register();
        }

        // MU-Plugin Replikation Hook - Nutzt die Konstante aus dem Entry-Point
        register_activation_hook(VGT_LOCKDOWN_FILE, [self::class, 'deploy_mu_loader']);

        self::$is_booted = true;
    }

    public static function deploy_mu_loader(): void {
        if (!file_exists(WPMU_PLUGIN_DIR)) {
            @mkdir(WPMU_PLUGIN_DIR, 0755, true);
        }
        $loader_path = WPMU_PLUGIN_DIR . '/vgt-loader.php';
        $plugin_file = VGT_LOCKDOWN_FILE;
        
        // VGT KERNEL: Deterministische Pfad-Auflösung, unabhängig von Dritt-Plugin-Manipulationen
        $upload_dir = defined('UPLOADS') ? ABSPATH . UPLOADS : WP_CONTENT_DIR . '/uploads';
        $state_dir = $upload_dir . '/vgt-omega-state/vgt-matrix.php';
        
        $loader_code = "<?php
// VGT OMEGA PROTOCOL LOADER
// ABSOLUTE INTERCEPTION MODE
if (file_exists('$state_dir')) {
    \$vgt_state = include '$state_dir';
    if (is_array(\$vgt_state) && !empty(\$vgt_state['is_locked'])) {
        \$uri = \$_SERVER['REQUEST_URI'] ?? '';
        \$trigger = \$vgt_state['emergency_trigger'] ?? '';
        if (\$trigger !== '' && strpos(\$uri, \$trigger) !== false) {
            // Bypass granted
        } elseif (empty(\$_COOKIE['vgt_omega_auth'])) {
            header('HTTP/1.1 403 Forbidden');
            die('OMEGA PROTOCOL: KERNEL PANIC. SYSTEM HALTED.');
        }
    }
}

if (file_exists('$plugin_file')) {
    require_once '$plugin_file';
}
";
        @file_put_contents($loader_path, $loader_code);
    }
}