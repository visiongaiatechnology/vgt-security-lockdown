<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** * @var bool $is_locked
 * @var string $emergency_trigger
 * @var bool $hide_dashboard
 */

$status_color = $is_locked ? '#ff0055' : '#00ffcc';
$status_text = $is_locked ? 'LOCKDOWN ENGAGED' : 'SYSTEM ONLINE';
$action_url = esc_url(admin_url('admin-post.php'));
$nonce_field = wp_nonce_field('vgt_save_nonce', 'vgt_nonce', true, false);
?>

<!-- VGT KERNEL: SHADOW DOM MOUNT POINT -->
<div id="vgt-omega-mount"></div>

<script>
(function() {
    const mountPoint = document.getElementById('vgt-omega-mount');
    // Closed Shadow DOM isoliert die UI komplett vom restlichen WP Admin CSS
    const shadow = mountPoint.attachShadow({ mode: 'closed' });
    
    const template = document.createElement('template');
    template.innerHTML = `
        <style>
            :host {
                display: block;
                all: initial; /* CSS Bleed Reset */
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            }
            
            * {
                box-sizing: border-box;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            .vgt-engine {
                background-color: #030305;
                color: #e2e8f0;
                min-height: calc(100vh - 32px);
                padding: 48px;
                background-image: 
                    radial-gradient(circle at 50% 0%, rgba(0, 255, 204, 0.03) 0%, transparent 40%),
                    radial-gradient(circle at 100% 100%, rgba(255, 0, 85, 0.02) 0%, transparent 40%);
                font-size: 14px;
            }

            .vgt-container {
                max-width: 1024px;
                margin: 0 auto;
            }

            .vgt-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-end;
                border-bottom: 1px solid rgba(255,255,255,0.05);
                padding-bottom: 32px;
                margin-bottom: 40px;
            }

            .vgt-title {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
                letter-spacing: -0.02em;
                color: #ffffff;
                display: flex;
                align-items: center;
                gap: 16px;
            }

            .status-orb {
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background-color: <?php echo $status_color; ?>;
                box-shadow: 0 0 16px <?php echo $status_color; ?>;
            }

            .vgt-version {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 11px;
                padding: 4px 8px;
                background: rgba(255,255,255,0.05);
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 4px;
                color: #94a3b8;
                letter-spacing: 0.05em;
            }

            .vgt-status-text {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 13px;
                color: <?php echo $status_color; ?>;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                font-weight: 600;
            }

            .vgt-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 24px;
            }

            .vgt-card {
                background: #08080b;
                border: 1px solid rgba(255,255,255,0.06);
                border-radius: 12px;
                padding: 32px;
                display: flex;
                flex-direction: column;
                transition: transform 0.2s, border-color 0.2s;
            }

            .vgt-card:hover {
                border-color: rgba(255,255,255,0.1);
            }

            .card-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 24px;
            }

            .card-header svg {
                width: 18px;
                height: 18px;
                color: #00ffcc;
            }
            
            .card-danger svg { color: #ff0055; }

            .card-title {
                font-size: 14px;
                font-weight: 600;
                color: #fff;
                margin: 0;
                letter-spacing: 0.02em;
            }

            .card-desc {
                color: #64748b;
                line-height: 1.6;
                margin: 0 0 24px 0;
                font-size: 13px;
                flex-grow: 1;
            }

            .vgt-input-group {
                margin-bottom: 20px;
            }

            .vgt-input-group:last-child {
                margin-bottom: 0;
            }

            .vgt-label {
                display: block;
                font-size: 12px;
                font-weight: 500;
                color: #94a3b8;
                margin-bottom: 8px;
            }

            .vgt-input {
                width: 100%;
                background: #030305;
                border: 1px solid rgba(255,255,255,0.1);
                border-radius: 6px;
                padding: 12px 16px;
                color: #fff;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 13px;
                transition: all 0.2s;
                outline: none;
            }

            .vgt-input:focus {
                border-color: #00ffcc;
                box-shadow: 0 0 0 1px #00ffcc, 0 0 16px rgba(0, 255, 204, 0.1);
            }

            /* Ultra-Modern Toggle */
            .toggle-wrapper {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 16px;
                background: rgba(255,255,255,0.02);
                border: 1px solid rgba(255,255,255,0.04);
                border-radius: 8px;
                cursor: pointer;
                user-select: none;
            }

            .toggle-label {
                font-weight: 500;
                color: #fff;
                font-size: 13px;
            }

            .toggle-switch {
                position: relative;
                width: 40px;
                height: 22px;
                background: rgba(255,255,255,0.1);
                border-radius: 11px;
                transition: 0.3s;
            }

            .toggle-switch::after {
                content: '';
                position: absolute;
                top: 2px;
                left: 2px;
                width: 18px;
                height: 18px;
                background: #fff;
                border-radius: 50%;
                transition: 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            }

            input[type="checkbox"] {
                display: none;
            }

            input[type="checkbox"]:checked + .toggle-switch {
                background: #00ffcc;
            }

            .card-danger input[type="checkbox"]:checked + .toggle-switch {
                background: #ff0055;
            }

            input[type="checkbox"]:checked + .toggle-switch::after {
                transform: translateX(18px);
                background: #000;
            }

            .vgt-actions {
                margin-top: 48px;
                display: flex;
                justify-content: flex-end;
            }

            .vgt-btn {
                background: #fff;
                color: #000;
                border: none;
                border-radius: 6px;
                padding: 14px 32px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 10px;
                transition: all 0.2s;
            }

            .vgt-btn:hover {
                background: #00ffcc;
                transform: translateY(-1px);
                box-shadow: 0 4px 20px rgba(0, 255, 204, 0.2);
            }

            .vgt-btn svg {
                width: 16px;
                height: 16px;
            }
        </style>

        <div class="vgt-engine">
            <div class="vgt-container">
                <form method="POST" action="<?php echo $action_url; ?>" id="vgt-form">
                    <input type="hidden" name="action" value="vgt_save_settings">
                    <?php echo $nonce_field; ?>

                    <div class="vgt-header">
                        <h1 class="vgt-title">
                            <div class="status-orb"></div>
                            VGT INTELLIGENCE
                            <span class="vgt-version">v2.0.3</span>
                        </h1>
                        <div class="vgt-status-text"><?php echo $status_text; ?></div>
                    </div>

                    <div class="vgt-grid">
                        <!-- State Matrix -->
                        <div class="vgt-card card-danger">
                            <div class="card-header">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <h3 class="card-title">Global State Override</h3>
                            </div>
                            <p class="card-desc">Abschottung der Architektur. Blockiert REST/AJAX und erzwingt das Master-Protokoll für alle Nodes.</p>
                            
                            <label class="toggle-wrapper">
                                <span class="toggle-label">Engage Total Lockdown</span>
                                <input type="checkbox" name="is_locked" value="1" <?php checked($is_locked); ?>>
                                <div class="toggle-switch"></div>
                            </label>
                        </div>

                        <!-- Cryptography -->
                        <div class="vgt-card">
                            <div class="card-header">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                <h3 class="card-title">Cryptographic Matrix</h3>
                            </div>
                            
                            <div class="vgt-input-group">
                                <label class="vgt-label">Argon2id Master Hash</label>
                                <input type="password" name="new_master_pw" class="vgt-input" placeholder="Leave blank to maintain state" autocomplete="new-password">
                            </div>
                            
                            <div class="vgt-input-group">
                                <label class="vgt-label">Emergency Trigger URI</label>
                                <input type="text" name="emergency_trigger" class="vgt-input" value="<?php echo esc_attr($emergency_trigger); ?>">
                            </div>
                        </div>

                        <!-- Stealth -->
                        <div class="vgt-card">
                            <div class="card-header">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <h3 class="card-title">Stealth Operations</h3>
                            </div>
                            <p class="card-desc">Eliminiert das Dashboard aus dem DOM-Tree der Administration. Zugriff nur via kryptografischem Hash-Reveal.</p>
                            
                            <label class="toggle-wrapper">
                                <span class="toggle-label">Obfuscate Control Panel</span>
                                <input type="checkbox" name="hide_dashboard" value="1" <?php checked($hide_dashboard); ?>>
                                <div class="toggle-switch"></div>
                            </label>
                        </div>
                    </div>

                    <div class="vgt-actions">
                        <button type="submit" class="vgt-btn">
                            Compile Protocol
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    shadow.appendChild(template.content.cloneNode(true));
})();
</script>