<?php
declare(strict_types=1);

namespace VGT\SecurityLockdown;

if (!defined('ABSPATH')) {
    exit;
}

final class AuthHandler implements ModuleInterface {
    public const COOKIE_NAME = 'vgt_omega_auth';
    private const MAX_ATTEMPTS = 3;
    private const BAN_TIME = 86400;

    private ConfigManager $config;
    private static ?string $resolved_ip = null;

    public function __construct(ConfigManager $config) {
        $this->config = $config;
    }

    public function register(): void {}

    public function is_unlocked(): bool {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }

        // VGT KERNEL: Strict Base64 Decoding zur Vermeidung von Payload-Injection
        $cookie_data = base64_decode($_COOKIE[self::COOKIE_NAME], true);
        if ($cookie_data === false) {
            return false;
        }

        $parts = explode('|', $cookie_data);

        if (count($parts) !== 3) {
            return false;
        }
        
        $exp = (int)$parts[0];
        $derived_token = $parts[1];
        $signature = $parts[2];

        if (time() > $exp) {
            return false;
        }

        $master_hash = $this->config->get('master_hash');
        if (empty($master_hash)) {
            return false;
        }

        // VGT KERNEL: Verifiziere das abgeleitete Token, NICHT den nativen Hash.
        // Verhindert Offline-Cracking, falls der Cookie abgefangen wird.
        $salt = defined('AUTH_KEY') ? AUTH_KEY : __FILE__;
        $expected_token = hash_hmac('sha256', $master_hash, $salt);
        
        if (!hash_equals($expected_token, $derived_token)) {
            return false;
        }

        $expected_signature = hash_hmac('sha256', $exp . '|' . $derived_token, $salt);
        return hash_equals($expected_signature, $signature);
    }

    private function is_trusted_proxy(string $ip): bool {
        $trusted_proxies = [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', 
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', 
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13', 
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', 
            '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32'
        ];

        foreach ($trusted_proxies as $cidr) {
            if ($this->cidr_match($ip, $cidr)) {
                return true;
            }
        }
        return false;
    }

    /**
     * VGT OMEGA KERNEL: Ultra-High-Performance CIDR Matcher.
     * Nutzt native binäre Memory-Vergleiche (Zero-String-Allocation für Bitmasking).
     */
    private function cidr_match(string $ip, string $cidr): bool {
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0];
        $mask = isset($parts[1]) ? (int)$parts[1] : null;

        $ip_packed = inet_pton($ip);
        $subnet_packed = inet_pton($subnet);

        if ($ip_packed === false || $subnet_packed === false) {
            return false;
        }

        $ip_len = strlen($ip_packed);
        $subnet_len = strlen($subnet_packed);

        if ($ip_len !== $subnet_len) {
            return false;
        }

        if ($mask === null) {
            return $ip_packed === $subnet_packed;
        }

        $bytes = (int)($mask / 8);
        $bits = $mask % 8;

        if ($bytes > 0 && strncmp($ip_packed, $subnet_packed, $bytes) !== 0) {
            return false;
        }

        if ($bits > 0) {
            $ip_byte = ord($ip_packed[$bytes]);
            $subnet_byte = ord($subnet_packed[$bytes]);
            $bitmask = 0xff << (8 - $bits);
            
            if (($ip_byte & $bitmask) !== ($subnet_byte & $bitmask)) {
                return false;
            }
        }

        return true;
    }

    private function get_client_ip(): string {
        if (self::$resolved_ip !== null) {
            return self::$resolved_ip;
        }

        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->is_trusted_proxy($remote_addr)) {
            $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'];
            foreach ($headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $ips = array_map('trim', explode(',', $_SERVER[$header]));
                    
                    // VGT KERNEL: Anti-Spoofing. Right-to-Left Evaluierung.
                    // Der am weitesten rechts stehende Eintrag (der nicht der Proxy selbst ist) ist der echte Client.
                    $ips = array_reverse($ips);
                    foreach ($ips as $ip) {
                        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                            if (!$this->is_trusted_proxy($ip)) {
                                self::$resolved_ip = $ip;
                                return self::$resolved_ip;
                            }
                        }
                    }
                }
            }
        }

        self::$resolved_ip = $remote_addr;
        return self::$resolved_ip;
    }

    private function get_secure_state_dir(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/vgt-omega-state';
    }

    private function get_cache_key(string $ip): string {
        $salt = defined('AUTH_KEY') ? AUTH_KEY : __FILE__;
        return 'vgt_ban_' . hash('sha256', $ip . $salt);
    }

    private function enforce_rate_limit(string $ip): void {
        $cache_key = $this->get_cache_key($ip);
        $attempts = 0;

        // L1: APCu (Zero Network I/O, Zero Disk I/O)
        if (function_exists('apcu_fetch')) {
            $attempts = (int)apcu_fetch($cache_key);
        } 
        // L2: WP Object Cache (Redis/Memcached)
        elseif (wp_using_ext_object_cache()) {
            $attempts = (int)wp_cache_get($cache_key, 'vgt_security');
        } 
        // L3: Disk Fallback (Isoliert)
        else {
            $file = $this->get_secure_state_dir() . '/' . $cache_key . '.lock';
            if (file_exists($file)) {
                $data = @file_get_contents($file);
                if ($data) {
                    $parts = explode('|', $data);
                    if (time() - (int)($parts[1] ?? 0) < self::BAN_TIME) {
                        $attempts = (int)($parts[0] ?? 0);
                    }
                }
            }
        }

        if ($attempts >= self::MAX_ATTEMPTS) {
            header('HTTP/1.1 429 Too Many Requests');
            wp_die('ACCESS DENIED. NETWORK NODE BLACKLISTED.', 'VGT OVERWATCH', ['response' => 429]);
        }
    }

    private function record_failed_attempt(string $ip): int {
        $cache_key = $this->get_cache_key($ip);
        $attempts = 1;

        // TIER 1: APCu (Atomar via apcu_inc)
        if (function_exists('apcu_inc')) {
            $attempts = apcu_inc($cache_key, 1, $success, self::BAN_TIME);
            if (!$success) {
                apcu_store($cache_key, 1, self::BAN_TIME);
                $attempts = 1;
            }
            return (int)$attempts;
        } 
        
        // TIER 2: WP Object Cache (Atomar via wp_cache_incr)
        if (wp_using_ext_object_cache()) {
            $attempts = wp_cache_incr($cache_key, 1, 'vgt_security');
            
            if ($attempts === false) {
                wp_cache_set($cache_key, 1, 'vgt_security', self::BAN_TIME);
                $attempts = 1;
            }
            return (int)$attempts;
        } 

        // TIER 3: Physisches File System (Chirurgische Isolation & Atomares Rename)
        $file = $this->get_secure_state_dir() . '/' . $cache_key . '.lock';
        $lock_file = $file . '.lock';
        $fp = @fopen($lock_file, 'c+');
        
        if ($fp && flock($fp, LOCK_EX)) {
            if (file_exists($file)) {
                $data = file_get_contents($file);
                if ($data) {
                    $parts = explode('|', $data);
                    if (time() - (int)($parts[1] ?? 0) < self::BAN_TIME) {
                        $attempts = (int)($parts[0] ?? 0) + 1;
                    }
                }
            }
            
            $tmp_file = $file . '.' . bin2hex(random_bytes(8)) . '.tmp';
            file_put_contents($tmp_file, $attempts . '|' . time());
            rename($tmp_file, $file);
            chmod($file, 0600);
            
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lock_file);
        }

        return $attempts;
    }

    public function process_emergency_auth(): void {
        $ip = $this->get_client_ip();
        $this->enforce_rate_limit($ip);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // VGT OMEGA KERNEL: Strikte CSRF & Replay-Attack Prevention
            if (!isset($_POST['vgt_csrf_token'], $_COOKIE['vgt_csrf_session'])) {
                header('HTTP/1.1 403 Forbidden');
                wp_die('OMEGA PROTOCOL: CSRF TOKEN MISSING. CONNECTION DROPPED.', 'VGT OVERWATCH', ['response' => 403]);
            }

            if (!hash_equals($_COOKIE['vgt_csrf_session'], $_POST['vgt_csrf_token'])) {
                header('HTTP/1.1 403 Forbidden');
                wp_die('OMEGA PROTOCOL: CSRF TOKEN MISMATCH. CONNECTION DROPPED.', 'VGT OVERWATCH', ['response' => 403]);
            }

            // Invalidate CSRF Session sofort nach Nutzung (Single-Use)
            setcookie('vgt_csrf_session', '', time() - 3600, '/', '', is_ssl(), true);

            if (isset($_POST['vgt_master_key'])) {
                $password = $_POST['vgt_master_key'];
                $hash = $this->config->get('master_hash');

                if (!empty($hash) && password_verify($password, $hash)) {
                    $exp = time() + (int)$this->config->get('cookie_duration');
                    $salt = defined('AUTH_KEY') ? AUTH_KEY : __FILE__;
                    
                    // VGT KERNEL FIX: Hashableitung. Der Argon2id-Hash verlässt NIEMALS den Server.
                    $derived_token = hash_hmac('sha256', $hash, $salt);
                    $payload = $exp . '|' . $derived_token;
                    $signature = hash_hmac('sha256', $payload, $salt);
                    
                    $cookie_value = base64_encode($payload . '|' . $signature);

                    setcookie(self::COOKIE_NAME, $cookie_value, [
                        'expires' => $exp,
                        'path' => '/',
                        'secure' => is_ssl(),
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]);

                    $cache_key = $this->get_cache_key($ip);
                    if (function_exists('apcu_delete')) {
                        apcu_delete($cache_key);
                    }
                    if (wp_using_ext_object_cache()) {
                        wp_cache_delete($cache_key, 'vgt_security');
                    }
                    @unlink($this->get_secure_state_dir() . '/' . $cache_key . '.lock');

                    wp_safe_redirect(admin_url());
                    exit;
                } else {
                    usleep(random_int(200000, 400000)); // Timing-Attack Mitigation
                    $attempts = $this->record_failed_attempt($ip);
                    error_log(sprintf("VGT LOCKDOWN: Critical Auth Failure. IP: %s, Attempts: %d", $ip, $attempts));
                }
            }
        }

        $this->render_emergency_ui();
        exit;
    }

    private function render_emergency_ui(): void {
        // Generiere kryptografisch sicheren CSRF Token
        $csrf_token = bin2hex(random_bytes(32));
        setcookie('vgt_csrf_session', $csrf_token, [
            'expires' => time() + 900, // 15 Minuten Lebensdauer
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        header('HTTP/1.1 403 Forbidden');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; font-src 'self' data:;");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>VGT // OMEGA KERNEL</title>
            <style>
                :root {
                    --vgt-cyan: #00ffcc;
                    --vgt-dark: #050505;
                    --vgt-panel: rgba(10, 10, 12, 0.85);
                    --font-mono: 'SF Mono', 'Consolas', 'Menlo', monospace;
                }
                body {
                    margin: 0;
                    height: 100vh;
                    background-color: var(--vgt-dark);
                    background-image: radial-gradient(circle at 50% 50%, #111818 0%, #000000 100%);
                    color: var(--vgt-cyan);
                    font-family: var(--font-mono);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    overflow: hidden;
                    -webkit-font-smoothing: antialiased;
                }
                .overlay-grid {
                    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
                    background-image: linear-gradient(rgba(0, 255, 204, 0.03) 1px, transparent 1px),
                                      linear-gradient(90deg, rgba(0, 255, 204, 0.03) 1px, transparent 1px);
                    background-size: 30px 30px;
                    pointer-events: none;
                    z-index: 1;
                }
                .terminal-card {
                    position: relative;
                    z-index: 2;
                    background: var(--vgt-panel);
                    border: 1px solid rgba(0, 255, 204, 0.2);
                    padding: 48px;
                    width: 100%;
                    max-width: 420px;
                    border-radius: 4px;
                    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8), 
                                inset 0 0 0 1px rgba(255, 255, 255, 0.05);
                    backdrop-filter: blur(16px);
                    -webkit-backdrop-filter: blur(16px);
                    transform: translateY(0);
                    animation: materialize 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                .status-badge {
                    display: inline-block;
                    padding: 4px 10px;
                    background: rgba(255, 0, 85, 0.1);
                    color: #ff0055;
                    border: 1px solid rgba(255, 0, 85, 0.3);
                    font-size: 11px;
                    letter-spacing: 2px;
                    text-transform: uppercase;
                    margin-bottom: 24px;
                }
                h2 { margin: 0 0 8px 0; font-size: 24px; font-weight: 500; letter-spacing: 1px; }
                p { color: rgba(255, 255, 255, 0.5); font-size: 13px; margin: 0 0 32px 0; line-height: 1.5; }
                input[type="password"] {
                    width: 100%; padding: 16px; background: rgba(0, 0, 0, 0.5);
                    border: 1px solid rgba(0, 255, 204, 0.3); color: #fff;
                    font-family: var(--font-mono); font-size: 16px; box-sizing: border-box;
                    transition: all 0.3s ease; outline: none; border-radius: 2px;
                }
                input[type="password"]:focus {
                    border-color: var(--vgt-cyan); box-shadow: 0 0 15px rgba(0, 255, 204, 0.2);
                    background: rgba(0, 20, 15, 0.8);
                }
                button {
                    margin-top: 24px; width: 100%; padding: 16px; background: transparent;
                    color: var(--vgt-cyan); border: 1px solid var(--vgt-cyan);
                    font-family: var(--font-mono); font-size: 14px; font-weight: 600;
                    letter-spacing: 2px; text-transform: uppercase; cursor: pointer;
                    transition: all 0.3s ease; position: relative; overflow: hidden;
                }
                button:hover { background: var(--vgt-cyan); color: #000; box-shadow: 0 0 20px rgba(0, 255, 204, 0.4); }
                @keyframes materialize {
                    0% { opacity: 0; transform: translateY(20px) scale(0.98); }
                    100% { opacity: 1; transform: translateY(0) scale(1); }
                }
            </style>
        </head>
        <body>
            <div class="overlay-grid"></div>
            <div class="terminal-card">
                <div class="status-badge">System Locked</div>
                <h2>OMEGA PROTOCOL</h2>
                <p>Kritische Infrastruktur isoliert. Autorisierung erforderlich zur Wiederherstellung der Knoten-Integrität.</p>
                <form method="POST">
                    <input type="hidden" name="vgt_csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="password" name="vgt_master_key" placeholder="Enter cryptographic sequence..." required autocomplete="off" autofocus>
                    <button type="submit">Execute Unlock</button>
                </form>
            </div>
        </body>
        </html>
        <?php
    }
}