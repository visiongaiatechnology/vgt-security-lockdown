<?php
declare(strict_types=1);

namespace VGT\SecurityLockdown;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminUI implements ModuleInterface {
    private ConfigManager $config;

    public function __construct(ConfigManager $config) {
        $this->config = $config;
    }

    public function register(): void {
        if (!is_admin()) return;
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_vgt_save_settings', [$this, 'save_settings']);
    }

    public function register_menu(): void {
        if ($this->config->get('hide_dashboard')) {
            $reveal_provided = isset($_GET['vgt_reveal']) && hash_equals($this->config->get('dashboard_reveal_hash'), hash('sha256', $_GET['vgt_reveal']));
            $is_revealed_session = isset($_COOKIE['vgt_dashboard_revealed']);

            if ($reveal_provided) {
                setcookie('vgt_dashboard_revealed', '1', 0, '/', '', is_ssl(), true);
                wp_safe_redirect(admin_url('admin.php?page=vgt-lockdown'));
                exit;
            }

            if (!$is_revealed_session) {
                return; 
            }
        }

        add_menu_page(
            'VGT Security',
            'VGT Lockdown',
            'manage_options',
            'vgt-lockdown',
            [$this, 'render_dashboard'],
            'dashicons-shield',
            2
        );
    }

    public function render_dashboard(): void {
        $is_locked = $this->config->get('is_locked');
        $emergency_trigger = $this->config->get('emergency_trigger');
        $hide_dashboard = $this->config->get('hide_dashboard');
        
        $view_path = VGT_LOCKDOWN_DIR . '/src/Views/AdminDashboard.php';
        
        if (file_exists($view_path)) {
            require $view_path;
        } else {
            wp_die('VGT KERNEL ERROR: View Matrix missing. Integrity compromised.');
        }
    }

    public function save_settings(): void {
        if (!current_user_can('manage_options') || !isset($_POST['vgt_nonce']) || !wp_verify_nonce($_POST['vgt_nonce'], 'vgt_save_nonce')) {
            wp_die('Unauthorized');
        }

        $this->config->set('is_locked', isset($_POST['is_locked']));
        $this->config->set('hide_dashboard', isset($_POST['hide_dashboard']));
        $this->config->set('emergency_trigger', sanitize_text_field($_POST['emergency_trigger']));

        if (!empty($_POST['new_master_pw'])) {
            $this->config->set('master_hash', password_hash($_POST['new_master_pw'], PASSWORD_ARGON2ID));
        }

        $this->config->save();
        wp_safe_redirect(admin_url('admin.php?page=vgt-lockdown&updated=true'));
        exit;
    }
}