<?php
declare(strict_types=1);

namespace VGT\SecurityLockdown;

if (!defined('ABSPATH')) {
    exit;
}

final class CLICommand implements ModuleInterface {
    private ConfigManager $config;

    public function __construct(ConfigManager $config) {
        $this->config = $config;
    }

    public function register(): void {
        if (!defined('WP_CLI') || !WP_CLI) return;

        \WP_CLI::add_command('vgt', [$this, 'handle_commands']);
    }

    public function handle_commands(array $args, array $assoc_args): void {
        $command = $args[0] ?? '';

        switch ($command) {
            case 'lock':
                $this->config->set('is_locked', true);
                $this->config->save();
                \WP_CLI::success('OMEGA PROTOCOL ENGAGED. System is locked.');
                break;
            case 'unlock':
                $this->config->set('is_locked', false);
                $this->config->save();
                \WP_CLI::success('Lockdown lifted. System normalized.');
                break;
            case 'set-master':
                if (!empty($args[1])) {
                    \WP_CLI::error('OPSEC VERSTOSS: Passwort niemals als Argument übergeben (.bash_history Leak). Nutze den interaktiven Modus.');
                }

                \WP_CLI::line('VGT KERNEL: Gebe die neue kryptografische Sequenz ein:');
                
                // Fallback-Logik für disable_functions
                $has_system = function_exists('system') && !in_array('system', array_map('trim', explode(',', ini_get('disable_functions'))));
                
                if ($has_system && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
                    system('stty -echo');
                    $password = trim(fgets(STDIN));
                    system('stty echo');
                    \WP_CLI::line('');
                } else {
                    \WP_CLI::warning('Terminal-Maskierung nicht verfügbar. Eingabe ist sichtbar.');
                    $password = trim(fgets(STDIN));
                }
                
                if (empty($password)) {
                    \WP_CLI::error('Sequenz ungültig. Abbruch.');
                }

                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $this->config->set('master_hash', $hash);
                $this->config->save();
                \WP_CLI::success('Master-Passwort aktualisiert (Argon2id Hash in isolierter Matrix gespeichert).');
                break;
            case 'status':
                $status = $this->config->get('is_locked') ? 'LOCKED' : 'OPEN';
                \WP_CLI::log("System Status: " . $status);
                break;
            default:
                \WP_CLI::error("Unbekannter Befehl. Nutze: lock, unlock, set-master, status");
        }
    }
}