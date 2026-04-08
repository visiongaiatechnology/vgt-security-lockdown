<?php
declare(strict_types=1);

namespace VGT\SecurityLockdown;

if (!defined('ABSPATH')) {
    exit;
}

final class Firewall implements ModuleInterface {
    private ConfigManager $config;
    private AuthHandler $auth;

    public function __construct(ConfigManager $config, AuthHandler $auth) {
        $this->config = $config;
        $this->auth = $auth;
    }

    public function register(): void {
        add_action('plugins_loaded', [$this, 'intercept_request'], 0);
        add_action('init', [$this, 'enforce_lockdown'], 0);
        add_action('rest_api_init', [$this, 'filter_rest_api'], 0);
        add_filter('xmlrpc_enabled', '__return_false');
    }

    public function intercept_request(): void {
        if (file_exists(WPMU_PLUGIN_DIR . '/vgt-bypass.php')) {
            return; 
        }

        $panic_hash = $this->config->get('panic_hash');
        
        // VGT KERNEL: State-Mutation nur über verborgene POST-Requests.
        // GET-Requests für Panic-Trigger abgelehnt (CSRF & History Leakage Prevention).
        if (!empty($panic_hash) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $provided_panic = $_POST['vgt_panic_override'] ?? '';
            if (hash_equals($panic_hash, hash('sha256', (string)$provided_panic))) {
                $this->config->set('is_locked', true);
                $this->config->save();
                wp_die('OMEGA PROTOCOL ENGAGED. SYSTEM LOCKED.', 'VGT SECURITY', ['response' => 403]);
            }
        }

        $trigger = $this->config->get('emergency_trigger');
        if (is_string($trigger) && $trigger !== '') {
            $trigger_parts = explode('=', $trigger, 2);
            if (count($trigger_parts) === 2) {
                // Der Trigger öffnet die Auth-UI, mutiert keinen State. GET ist sicher.
                if (isset($_GET[$trigger_parts[0]]) && $_GET[$trigger_parts[0]] === $trigger_parts[1]) {
                    $this->auth->process_emergency_auth();
                }
            }
        }
    }

    public function enforce_lockdown(): void {
        if (!$this->config->get('is_locked')) return;
        if ($this->auth->is_unlocked()) return;

        $is_ajax = wp_doing_ajax();
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';

        // Blockiere Admin-Zugriff, erlaube AJAX für nachgelagerte Whitelist-Prüfung
        if (is_admin() && !$is_ajax) {
            wp_logout();
            wp_die('SYSTEM LOCKED. MASTER AUTH REQUIRED.', 'VGT SECURITY', ['response' => 403]);
        }

        // VGT OMEGA STRICT: POST & AJAX-Traffic blockiert. admin-ajax.php erfordert Whitelisting.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $is_ajax) {
            if (!$this->is_route_whitelisted($request_uri)) {
                
                $ajax_action = $_REQUEST['action'] ?? '';
                if ($is_ajax && !empty($ajax_action) && $this->is_route_whitelisted('ajax:' . $ajax_action)) {
                    return; // O(1) Whitelist Match
                }

                if ($is_ajax) {
                    wp_send_json_error(['message' => 'OMEGA PROTOCOL: AJAX MUTATION BLOCKED.'], 403);
                }
                wp_die('MUTATION BLOCKED.', 'VGT SECURITY', ['response' => 403]);
            }
        }
    }

    public function filter_rest_api(): void {
        if (!$this->config->get('is_locked') || $this->auth->is_unlocked()) return;

        add_filter('rest_pre_dispatch', function($result, $server, $request) {
            if ($request->get_method() !== 'GET') {
                if (!$this->is_route_whitelisted($request->get_route())) {
                    return new \WP_Error('vgt_rest_locked', 'REST MUTATION BLOCKED.', ['status' => 403]);
                }
            }
            return $result;
        }, 10, 3);
    }

    private function is_route_whitelisted(string $route): bool {
        static $exact_routes = null;
        static $pattern_routes = [];

        if ($exact_routes === null) {
            $exact_routes = [];
            $whitelist = $this->config->get('whitelist_routes');
            
            if (is_array($whitelist)) {
                foreach ($whitelist as $pattern) {
                    if (strpos($pattern, '*') === false) {
                        $exact_routes[$pattern] = true;
                    } else {
                        $pattern_routes[] = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
                    }
                }
            }
        }

        // O(1) Hashmap Lookup
        if (isset($exact_routes[$route])) {
            return true;
        }

        // Fallback O(n) Regex Evaluation
        foreach ($pattern_routes as $regex) {
            if (preg_match($regex, $route)) {
                return true;
            }
        }
        
        return false;
    }
}