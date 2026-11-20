# PrestaShop Backlink Monitor Pro (v2.0.0)

> **Enterprise SEO Health Monitoring, Anchor Verification & Diagnostics Suite for PrestaShop 1.7, 8.x & 9.x**  
> Engineered by **Arsalan Ansari** (Senior PrestaShop & Full-Stack Architect)

[![PrestaShop](https://img.shields.io/badge/PrestaShop-1.7%20%7C%208.x%20%7C%209.x-251b5b?style=for-the-badge&logo=prestashop)](https://www.prestashop.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%20%7C%208.1%20%7C%208.2%20%7C%208.3%20%7C%208.4-777BB4?style=for-the-badge&logo=php)](https://www.php.net/)
[![Architecture](https://img.shields.io/badge/Architecture-Resilient%20Worker%20Engine-blue?style=for-the-badge)](#technical-architecture)
[![Security](https://img.shields.io/badge/Security-Timing--Safe%20Hash%20Verification-brightgreen?style=for-the-badge)](#security-standards)

---

## 📌 Overview

**Backlink Monitor Pro** is an automated, high-precision backlink auditing and target health engine built directly into the PrestaShop back office. 

Acquiring paid and organic backlinks is critical for e-commerce SEO, yet merchants and marketing agencies lose thousands in SEO value when external links get silently removed, redirected to 404s, modified to `rel="nofollow"`, or altered with mismatched anchor text. 

This module automates continuous link verification, flags target health regressions, and generates executive-level audit reports with zero external SaaS subscription fees.

---

## 🏗️ Technical Architecture & Key Highlights

This module was built to handle high-volume scraping and verification workloads without degrading server resources or causing database timeouts.

### 1. Resilient Database Reconnection Pattern
* **Problem in Long Batch Jobs:** When processing hundreds of URLs via cURL, prolonged network latency frequently exceeds MySQL's `wait_timeout`, causing fatal "MySQL server has gone away" errors in standard modules.
* **Architectural Solution:** Implemented `BacklinkMonitorChecker::pingDatabase()`, which monitors connection status and silently re-establishes active PDO/MySQLi connections before and after expensive network calls.

### 2. Comprehensive Link Inspection Engine
For every tracked backlink, the worker engine executes deep DOM parsing and HTTP protocol analysis:
* **Target Health Audit:** Verifies landing page HTTP status codes (`200 OK`, `301/302 Redirect`, `404 Not Found`, `500 Server Error`).
* **Anchor Text Discrepancy Detection:** Compares expected anchor text against the live DOM, flagging anchor mismatches and empty links.
* **Link Attribute Inspection:** Accurately isolates `rel="dofollow"`, `rel="nofollow"`, `rel="ugc"`, and `rel="sponsored"`.
* **Redirect Loop & Canonical Auditing:** Follows source redirects up to 5 hops while enforcing memory limits.

### 3. Cryptographically Secure Cron Automation
* External cron execution endpoint (`controllers/front/cron.php`) is protected by a 32-character cryptographically secure token (`Tools::passwdGen(32)`).
* Validated using constant-time string comparison (`hash_equals()`) to eliminate timing-attack vulnerabilities.

### 4. Executive KPI Dashboard & Export Engine
* Back-Office controller (`AdminBacklinkMonitorController`) renders interactive KPI summary widgets.
* One-click CSV and structured audit report generators for marketing directors and agency clients.

---

## 📂 Module Directory Structure

```text
backlinkmonitor/
├── classes/
│   ├── BacklinkMonitorChecker.php   # Deep cURL engine, DOM parser, and ping logic (960+ lines)
│   └── BacklinkMonitorLink.php      # ObjectModel database abstraction layer
├── controllers/
│   ├── admin/
│   │   └── AdminBacklinkMonitorController.php # Full back-office management & reporting
│   └── front/
│       └── cron.php                 # Protected automated background runner
├── views/
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   └── templates/admin/
│       └── kpi.tpl                  # Executive KPI summary metrics
├── config.xml
├── logo.png
└── backlinkmonitor.php              # Module lifecycle & schema installer
```

---

## 🚀 Installation & Compatibility

* **PrestaShop Compatibility:** `1.7.x`, `8.x`, and verified on `9.0.x`
* **PHP Compatibility:** `7.4`, `8.1`, `8.2`, `8.3`, and `8.4`

### Quick Install
1. Clone or download into your modules directory:
   ```bash
   cd /path-to-prestashop/modules/
   git clone https://github.com/arsalan-ansari/prestashop-backlink-monitor-pro.git backlinkmonitor
   ```
2. Navigate to your PrestaShop Back Office > **Modules** > **Module Manager**.
3. Search for **Backlink Monitor Pro** and click **Install**.
4. Set up your automated cron trigger in cPanel or server crontab using the secure URL displayed in the module configuration.

---

## 👨‍💻 Author & Professional Inquiries

**Arsalan Ansari**  
*Senior PrestaShop & Full-Stack PHP Architect*  
* Specialized in bespoke module engineering, PrestaShop 8/9 upgrades, and technical SEO architectures for global brands.
* **LinkedIn:** [linkedin.com/in/arsalan-ansari-prestashop](https://www.linkedin.com/in/arsalan-ansari-prestashop/)
* **GitHub:** [github.com/arsalan-ansari](https://github.com/arsalan-ansari)
