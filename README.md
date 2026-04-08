# 🔒 VGT Security Lockdown — Omega Protocol

[![License](https://img.shields.io/badge/License-AGPLv3-green?style=for-the-badge)](LICENSE)
[![Version](https://img.shields.io/badge/Version-2.0.4-brightgreen?style=for-the-badge)](#)
[![Status](https://img.shields.io/badge/Status-BETA-yellow?style=for-the-badge)](#)
[![Platform](https://img.shields.io/badge/Platform-WordPress-21759B?style=for-the-badge&logo=wordpress)](#)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=for-the-badge&logo=php)](#)
[![Auth](https://img.shields.io/badge/Auth-Argon2id_%2B_HMAC--SHA256-red?style=for-the-badge)](#)
[![Architecture](https://img.shields.io/badge/Architecture-MU--Plugin_Interceptor-orange?style=for-the-badge)](#)
[![VGT](https://img.shields.io/badge/VGT-VisionGaia_Technology-red?style=for-the-badge)](https://visiongaiatechnology.de)

> *"Absolute lockdown. No bypass. No compromise."*
> *AGPLv3 — For Humans, not for SaaS Corporations.*

---

> ## ⚠️ BETA NOTICE — READ BEFORE INSTALLING
>
> VGT Security Lockdown v2.0.4 is in **public beta**.
>
> | Notice | Detail |
> |---|---|
> | **Security** | As beta software, this plugin may still contain **unknown vulnerabilities**. Do not deploy on unsecured production systems without prior testing. |
> | **Web Server** | Currently optimized primarily for **Apache**. **Nginx/Apache hybrid setups** are supported but not yet fully tested. Pure Nginx instances may exhibit unexpected behavior. |
> | **Recovery** | Before activating, **ensure WP-CLI access is available** — in the event of misconfiguration, the dashboard cannot be recovered without CLI access (see [Emergency Recovery](#-recovery--emergency)). |
>
> **Feedback and bug reports are explicitly encouraged — PRs welcome.**

---

## 🔍 What is VGT Security Lockdown?

VGT Security Lockdown is not a conventional security plugin. It is a **Modular Interceptor** — an autonomous security core that embeds itself deep into the WordPress runtime on first activation and enforces absolute lockdown from there.

```
Standard WordPress Security:
→ Plugin can be deactivated via dashboard
→ Auth runs through WP-Core
→ REST/AJAX uncontrolled and open
→ Dashboard visible for reconnaissance

VGT Security Lockdown — Omega Protocol:
→ MU-Plugin replication → dashboard bypass systemically impossible
→ Argon2id Master-Hash + HMAC-SHA256 Token Derivation
→ All POST/PUT/DELETE + AJAX globally terminated in lockdown mode
→ Dashboard fully removed from DOM (cryptographic URI required)
→ O(1) CIDR Binary Matcher for IP-Whitelist lookups
→ Closed Shadow DOM — zero CSS interference possible
→ WP-CLI integration for headless recovery
→ Panic Trigger for immediate global lockdown
```

---

## 🏛️ Architecture — Three-Layer Interceptor

```
WordPress Bootstrap
        ↓
┌─────────────────────────────────────────────────┐
│  L1 — ABSOLUTE INTERCEPTION (MU-REPLICATION)    │
│  Self-replication into /mu-plugins              │
│  Execution before all plugins & themes          │
│  Dashboard deactivation → systemically blocked  │
└──────────────────────┬──────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│  L2 — ZERO-KNOWLEDGE AUTHENTICATION             │
│  Argon2id Master-Hash (stays in persistent mem) │
│  HMAC-SHA256 Token Derivation (time-limited)    │
│  Cookie-theft resistant via AUTH_KEY binding    │
└──────────────────────┬──────────────────────────┘
                       ↓
┌─────────────────────────────────────────────────┐
│  L3 — FIREWALL & MUTATION STOP                  │
│  O(1) CIDR Whitelist lookup via Hashmap         │
│  POST/PUT/DELETE → terminated (no whitelist)    │
│  AJAX globally blocked (no whitelist signature) │
│  REST API → isolated                            │
└─────────────────────────────────────────────────┘
```

---

## 💎 Feature Set

| Feature | Description |
|---|---|
| **MU-Plugin Replication** | Automatically replicates into `mu-plugins` on activation — lockdown core runs before any other plugin |
| **Argon2id Auth** | Hardware-resistant master hash. The hash never leaves persistent memory |
| **HMAC-SHA256 Tokens** | Temporary, cryptographically signed session tokens — time-limited and bound to `AUTH_KEY` |
| **O(1) CIDR Matcher** | Ultra-high-performance IP whitelist lookups via hashmap — no linear scanning |
| **AJAX/REST Isolation** | All state-changing requests globally terminated in lockdown mode (without explicit whitelist signature) |
| **Dashboard Obfuscation** | Control panel fully removed from the WordPress DOM — access only via cryptographic URI sequence |
| **Header Hardening** | Automatic injection of `Content-Security-Policy`, `X-Frame-Options: DENY`, `Strict-Transport-Security` |
| **Closed Shadow DOM** | UI isolation — zero CSS interference from WordPress core or other plugins possible |
| **WP-CLI Integration** | Full system control via command line for headless recovery scenarios |
| **Panic Trigger** | Physically isolated POST trigger for immediate global lockdown upon detecting active compromise |

---

## 🔐 Authentication Architecture

```
Master Password
      ↓
Argon2id Hash (hardware-resistant)
      ↓ stored in persistent memory — never transmitted
      ↓
Session Request
      ↓
HMAC-SHA256 Token Derivation
→ Time-limited
→ Bound to server-side AUTH_KEY
→ Cookie theft → useless without AUTH_KEY
      ↓
Session Validated
```

**Why Argon2id?**
Argon2id is memory-hard and GPU/ASIC-resistant. Even with direct database access, brute-forcing the master hash is computationally infeasible without significant dedicated hardware investment.

---

## 🛡️ Anti-Reconnaissance & Stealth

**Dashboard Obfuscation:**
The WordPress control panel is fully removed from the DOM. Standard paths (`/wp-admin`, `wp-login.php`) return no exploitable signals. Access requires a specific cryptographic URI sequence known only to the administrator.

**Header Hardening (automatically injected):**

| Header | Value | Protection |
|---|---|---|
| `Content-Security-Policy` | Restrictive policy | XSS, inline script injection |
| `X-Frame-Options` | `DENY` | Clickjacking |
| `Strict-Transport-Security` | `max-age=31536000` | Downgrade attacks |

---

## 🆘 Recovery & Emergency

> **Before activating:** Ensure WP-CLI access is available. In the event of misconfiguration, the dashboard cannot be recovered without CLI access.

### WP-CLI Commands

```bash
# Disable lockdown
wp vgt-lockdown disable

# Reset master password
wp vgt-lockdown reset-auth

# Query system status
wp vgt-lockdown status

# Add IP to whitelist
wp vgt-lockdown whitelist add YOUR.IP.HERE

# Manually remove MU-Plugin (emergency)
rm /wp-content/mu-plugins/vgt-lockdown-core.php
```

### Panic Trigger

```bash
# Immediate global lockdown via POST trigger
curl -X POST https://yourdomain.com/vgt-panic \
     --data "token=YOUR_PANIC_TOKEN"
```

> The Panic Trigger is physically isolated from WordPress core and can be activated even during an active dashboard compromise.

### Manual Removal (no WP-CLI available)

```bash
# 1. Remove MU-Plugin
rm /wp-content/mu-plugins/vgt-lockdown-core.php

# 2. Remove plugin directory
rm -rf /wp-content/plugins/vgt-security-lockdown/

# 3. Clear WordPress cache
wp cache flush
```

---

## ⚙️ Requirements & Compatibility

| Requirement | Minimum |
|---|---|
| **WordPress** | 6.0+ |
| **PHP** | 8.1+ (strict types enforced) |
| **PHP Extension** | `sodium` (Argon2id) |
| **PHP Extension** | `hash` (HMAC-SHA256) |
| **WP-CLI** | Strongly recommended (required for recovery) |

### Web Server Compatibility

| Web Server | Status |
|---|---|
| **Apache** | ✅ Fully supported |
| **Apache + Nginx Hybrid** | ✅ Supported (Beta) |
| **Pure Nginx** | ⚠️ Limited — unexpected behavior possible |
| **LiteSpeed** | ⚠️ Not yet tested |

> Pure Nginx setups may exhibit unexpected behavior with `.htaccess`-based rules. Nginx-native configuration support is planned for a future release.

---

## 🚀 Installation

```bash
# 1. Clone into WordPress plugins directory
cd /var/www/html/wp-content/plugins/
git clone https://github.com/visiongaiatechnology/vgt-security-lockdown

# 2. Ensure WP-CLI access before activating
wp cli info

# 3. Activate in WordPress Admin
# Plugins → VGT Security Lockdown → Activate

# 4. Set master password
# Settings → VGT Lockdown → Auth Setup

# 5. Configure IP whitelist
# Settings → VGT Lockdown → Whitelist
```

On first activation:

```
→ Argon2id master hash generated
→ MU-Plugin replication into /mu-plugins
→ Cryptographic URI sequence generated
→ CIDR whitelist hashmap initialized
→ Header hardening activated
→ Dashboard obfuscation applied
→ AJAX/REST isolation enabled
```

---

## 💰 Support the Project

[![Donate via PayPal](https://img.shields.io/badge/Donate-PayPal-00457C?style=for-the-badge&logo=paypal)](https://www.paypal.com/paypalme/dergoldenelotus)

| Method | Address |
|---|---|
| **PayPal** | [paypal.me/dergoldenelotus](https://www.paypal.com/paypalme/dergoldenelotus) |
| **Bitcoin** | `bc1q3ue5gq822tddmkdrek79adlkm36fatat3lz0dm` |
| **ETH** | `0xD37DEfb09e07bD775EaaE9ccDaFE3a5b2348Fe85` |
| **USDT (ERC-20)** | `0xD37DEfb09e07bD775EaaE9ccDaFE3a5b2348Fe85` |

---

## 🔗 VGT Ecosystem

| Tool | Type | Purpose |
|---|---|---|
| 🔒 **VGT Security Lockdown** | **Absolute Lockdown** | MU-Interceptor, Zero-Knowledge Auth, Panic Trigger — you are here |
| ⚔️ **[VGT Sentinel](https://github.com/visiongaiatechnology/vgt-sentinel)** | **WAF / IDS Framework** | Zero-Trust WordPress Security Suite |
| 🛡️ **[VGT Myrmidon](https://github.com/visiongaiatechnology/vgtmyrmidon)** | **ZTNA** | Zero Trust Device Registry & Cryptographic Integrity Verification |
| ☠️ **[VGT KillerDom](https://github.com/visiongaiatechnology/killerdom)** | **WAF Research Engine** | Polyglot Regex Annihilation Core — PHP, Python, Go, Rust |
| ⚡ **[VGT Auto-Punisher](https://github.com/visiongaiatechnology/vgt-auto-punisher)** | **IDS** | L4+L7 Hybrid IDS — attackers terminated before they even knock |
| 🌐 **[VGT Global Threat Sync](https://github.com/visiongaiatechnology/vgt-global-threat-sync)** | **Preventive** | Daily threat feed — block known attackers before they arrive |
| 🔥 **[VGT Windows Firewall Burner](https://github.com/visiongaiatechnology/vgt-windows-burner)** | **Windows** | 280,000+ APT IPs in native Windows Firewall |

---

## 🤝 Contributing

Pull requests are welcome. For major changes, please open an issue first.

Since this project is in beta, **security reports are especially valuable** — please disclose responsibly via issue or direct contact.

Licensed under **AGPLv3** — *"For Humans, not for SaaS Corporations."*

---

## 🏢 Built by VisionGaia Technology

[![VGT](https://img.shields.io/badge/VGT-VisionGaia_Technology-red?style=for-the-badge)](https://visiongaiatechnology.de)

VisionGaia Technology builds enterprise-grade security infrastructure — engineered to the DIAMANT VGT SUPREME standard.

> *"A plugin that embeds itself into the WordPress core before anyone else can register a single hook — that's not a plugin. That's a gatekeeper."*

---

*Version 2.0.4 Beta — VGT Security Lockdown // Omega Protocol // Argon2id + HMAC-SHA256 + MU-Interceptor // AGPLv3*
