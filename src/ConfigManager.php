<?php
declare(strict_types=1);

namespace VGT\SecurityLockdown;

if (!defined('ABSPATH')) {
    exit;
}

final class ConfigManager {
    private array $state = [];
    private string $config_file;

    public function __construct() {
        $this->init_secure_storage();
        $this->reload();
    }

    private function init_secure_storage(): void {
        // VGT OMEGA STRICT: Physische Isolation vom Datenbank-Layer.
        // Keine SQL-Injection in Third-Party-Plugins kann den Lockdown aufheben.
        $upload_dir = wp_upload_dir();
        $state_dir = $upload_dir['basedir'] . '/vgt-omega-state';
        
        if (!is_dir($state_dir)) {
            mkdir($state_dir, 0700, true);
            file_put_contents($state_dir . '/.htaccess', "Order Deny,Allow\nDeny from all");
            file_put_contents($state_dir . '/index.php', "<?php // VGT SILENCE");
        }
        
        $this->config_file = $state_dir . '/vgt-matrix.php';
    }

    public function reload(): void {
        $default = [
            'is_locked' => false,
            'master_hash' => '', // Argon2id Hash
            'emergency_trigger' => 'emergency=relock?7',
            'cookie_duration' => 1800, // 30 Min
            'hide_dashboard' => false,
            'dashboard_reveal_hash' => '', // SHA256 Token
            'panic_hash' => '', // SHA256 Token
            'whitelist_routes' => [
                '/wp-json/contact-form-7/v1/contact-forms/.*/feedback',
                '/wp-json/wc/v3/products',
                '/wp-json/wc/store/cart',
            ]
        ];

        // L1: Object Cache (Redis/Memcached - Distributed Truth)
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            $cached_state = wp_cache_get('vgt_global_matrix', 'vgt_security');
            if (is_array($cached_state)) {
                $this->state = wp_parse_args($cached_state, $default);
                return;
            }
        }

        // L2: File System (Single Node Truth)
        if (file_exists($this->config_file)) {
            $saved_state = include $this->config_file;
            if (is_array($saved_state)) {
                $this->state = wp_parse_args($saved_state, $default);
                // Sync L1 falls Cache leer war, aber aktiv ist
                if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
                    wp_cache_set('vgt_global_matrix', $this->state, 'vgt_security');
                }
                return;
            }
        }
        
        // L3: Database Fallback (I/O Blocked/Read-Only Container Truth)
        $db_state = get_option('vgt_omega_fallback_state');
        if ($db_state) {
            $decoded_state = @unserialize(base64_decode($db_state));
            if (is_array($decoded_state)) {
                $this->state = wp_parse_args($decoded_state, $default);
                return;
            }
        }

        $this->state = $default;
    }

    public function get(string $key) {
        return $this->state[$key] ?? null;
    }

    public function set(string $key, $value): void {
        $this->state[$key] = $value;
    }

    public function save(): void {
        $export = var_export($this->state, true);
        $content = "<?php\n// VGT OMEGA PROTOCOL MATRIX\n// DO NOT EDIT MANUALLY.\nif (!defined('ABSPATH')) exit;\nreturn $export;\n";
        
        $temp_file = $this->config_file . '.tmp';
        
        // Unterdrücke I/O Fehler bei Read-Only Containern (z.B. Kubernetes)
        if (@file_put_contents($temp_file, $content, LOCK_EX)) {
            @rename($temp_file, $this->config_file);
            // OPcache Invalidation für sofortige System-Reaktion
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($this->config_file, true);
            }
        }

        // VGT KERNEL: Verteiltes Syncing erzwingen.
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            wp_cache_set('vgt_global_matrix', $this->state, 'vgt_security');
        } else {
            // Letzter Fallback auf DB, isoliert durch base64/serialize Obfuscation
            // um einfache SQLi-Scanner abzuwehren, falls File-System Read-Only ist.
            update_option('vgt_omega_fallback_state', base64_encode(serialize($this->state)), false);
        }
    }

    public function getAll(): array {
        return $this->state;
    }
}