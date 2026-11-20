<?php
/**
 * Backlink Monitor - PrestaShop Module
 *
 * @author    Arsalan
 * @copyright Arsalan
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/classes/BacklinkMonitorLink.php';
require_once dirname(__FILE__) . '/classes/BacklinkMonitorChecker.php';

class backlinkmonitor extends Module
{
    public function __construct()
    {
        $this->name = 'backlinkmonitor';
        $this->tab = 'administration';
        $this->version = '2.0.0';
        $this->author = 'Arsalan';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Backlink Monitor Pro');
        $this->description = $this->l('Advanced backlink & target health monitoring, accurate rel/anchor verification, diagnostics, and executive export reports.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall? All backlink monitoring data will be deleted.');
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        $res = parent::install()
            && $this->installDB()
            && $this->installTab()
            && $this->installConfig();

        if ($res) {
            BacklinkMonitorLink::ensureSchema();
        }

        return $res;
    }

    public function uninstall()
    {
        return $this->uninstallDB()
            && $this->uninstallTab()
            && $this->uninstallConfig()
            && parent::uninstall();
    }

    private function installDB()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'backlinkmonitor` (
            `id_backlink` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `url_source` VARCHAR(500) NOT NULL,
            `redirect_url` VARCHAR(500) DEFAULT NULL,
            `url_target` VARCHAR(500) NOT NULL,
            `target_redirect_url` VARCHAR(500) DEFAULT NULL,
            `anchor_text` VARCHAR(255) DEFAULT NULL,
            `found_anchor_text` VARCHAR(255) DEFAULT NULL,
            `anchor_mismatch` TINYINT(1) DEFAULT NULL,
            `status` TINYINT(1) NOT NULL DEFAULT 0,
            `http_code` SMALLINT DEFAULT NULL,
            `is_dofollow` TINYINT(1) DEFAULT NULL,
            `rel_attribute` VARCHAR(128) DEFAULT NULL,
            `link_type` VARCHAR(64) DEFAULT NULL,
            `check_message` VARCHAR(255) DEFAULT NULL,
            `target_status` TINYINT(1) NOT NULL DEFAULT 0,
            `target_http_code` SMALLINT DEFAULT NULL,
            `target_last_checked` DATETIME DEFAULT NULL,
            `notes` TEXT,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_checked` DATETIME DEFAULT NULL,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_backlink`),
            KEY `idx_status` (`status`),
            KEY `idx_target_status` (`target_status`),
            KEY `idx_active` (`active`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return Db::getInstance()->execute($sql);
    }

    private function uninstallDB()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'backlinkmonitor`');
    }

    private function installTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminBacklinkMonitor');
        if ($id_tab) {
            return true;
        }

        $tab = new Tab();
        $tab->class_name = 'AdminBacklinkMonitor';
        $tab->id_parent = 0; // Root menu level
        $tab->module = $this->name;
        $tab->icon = 'link';
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Backlink Monitor';
        }

        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminBacklinkMonitor');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }

        return true;
    }

    private function installConfig()
    {
        if (!Configuration::get('BACKLINKMONITOR_CRON_TOKEN')) {
            Configuration::updateValue('BACKLINKMONITOR_CRON_TOKEN', Tools::passwdGen(32));
        }

        $timeout = Configuration::get('BACKLINKMONITOR_TIMEOUT');
        if ($timeout === false || (int) $timeout <= 0) {
            Configuration::updateValue('BACKLINKMONITOR_TIMEOUT', 15);
        }

        $userAgent = Configuration::get('BACKLINKMONITOR_USER_AGENT');
        if (empty($userAgent)) {
            Configuration::updateValue('BACKLINKMONITOR_USER_AGENT', BacklinkMonitorChecker::DEFAULT_USER_AGENT);
        }

        if (Configuration::get('BACKLINKMONITOR_FOLLOW_REDIRECTS') === false) {
            Configuration::updateValue('BACKLINKMONITOR_FOLLOW_REDIRECTS', 1);
        }

        if (Configuration::get('BACKLINKMONITOR_STRICT_ANCHOR') === false) {
            Configuration::updateValue('BACKLINKMONITOR_STRICT_ANCHOR', 0);
        }

        if (Configuration::get('BACKLINKMONITOR_AUTOCHECK') === false) {
            Configuration::updateValue('BACKLINKMONITOR_AUTOCHECK', 1);
        }

        return true;
    }

    private function uninstallConfig()
    {
        Configuration::deleteByName('BACKLINKMONITOR_CRON_TOKEN');
        Configuration::deleteByName('BACKLINKMONITOR_TIMEOUT');
        Configuration::deleteByName('BACKLINKMONITOR_USER_AGENT');
        Configuration::deleteByName('BACKLINKMONITOR_FOLLOW_REDIRECTS');
        Configuration::deleteByName('BACKLINKMONITOR_STRICT_ANCHOR');
        Configuration::deleteByName('BACKLINKMONITOR_AUTOCHECK');

        return true;
    }

    public function getContent()
    {
        $output = '';

        // Ensure database schema is modern & healed
        BacklinkMonitorLink::ensureSchema();

        // Auto-initialize defaults if not set or empty
        $this->installConfig();

        // Process Settings Save
        if (Tools::isSubmit('submitBacklinkMonitorConfig')) {
            $timeout = (int) Tools::getValue('BACKLINKMONITOR_TIMEOUT');
            if ($timeout < 3 || $timeout > 60) {
                $timeout = 15;
            }
            Configuration::updateValue('BACKLINKMONITOR_TIMEOUT', $timeout);

            $userAgent = trim((string) Tools::getValue('BACKLINKMONITOR_USER_AGENT'));
            if (empty($userAgent)) {
                $userAgent = BacklinkMonitorChecker::DEFAULT_USER_AGENT;
            }
            Configuration::updateValue('BACKLINKMONITOR_USER_AGENT', $userAgent);

            Configuration::updateValue('BACKLINKMONITOR_FOLLOW_REDIRECTS', (int) Tools::getValue('BACKLINKMONITOR_FOLLOW_REDIRECTS'));
            Configuration::updateValue('BACKLINKMONITOR_STRICT_ANCHOR', (int) Tools::getValue('BACKLINKMONITOR_STRICT_ANCHOR'));
            Configuration::updateValue('BACKLINKMONITOR_AUTOCHECK', (int) Tools::getValue('BACKLINKMONITOR_AUTOCHECK'));

            if (Tools::getValue('regenerate_cron_token')) {
                Configuration::updateValue('BACKLINKMONITOR_CRON_TOKEN', Tools::passwdGen(32));
            }

            $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
        }

        $cronToken = Configuration::get('BACKLINKMONITOR_CRON_TOKEN');
        if (empty($cronToken)) {
            $cronToken = Tools::passwdGen(32);
            Configuration::updateValue('BACKLINKMONITOR_CRON_TOKEN', $cronToken);
        }
        $cronUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__ . 'module/' . $this->name . '/cron?token=' . $cronToken;
        $adminLink = Context::getContext()->link->getAdminLink('AdminBacklinkMonitor');
        $exportCsvLink = $adminLink . '&exportCSV=1';
        $exportReportLink = $adminLink . '&exportAuditReport=1';

        $output .= '<div class="panel">
            <h3><i class="icon-dashboard"></i> ' . $this->l('Backlink Monitor Dashboard & Quick Actions') . '</h3>
            <p>' . $this->l('Monitor backlinks placed on external websites, verify dofollow/nofollow and sponsored status, detect anchor text mismatches, and safeguard your target URLs.') . '</p>
            <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="' . $adminLink . '" class="btn btn-primary btn-lg"><i class="icon-list"></i> ' . $this->l('Manage Backlinks') . '</a>
                <a href="' . $exportReportLink . '" target="_blank" class="btn btn-info btn-lg"><i class="icon-bar-chart"></i> ' . $this->l('Executive Audit Report (HTML/Print)') . '</a>
                <a href="' . $exportCsvLink . '" class="btn btn-default btn-lg"><i class="icon-download"></i> ' . $this->l('Download CSV (Excel Ready)') . '</a>
            </div>
        </div>';

        $output .= $this->renderConfigurationForm();

        $output .= '<div class="panel">
            <h3><i class="icon-time"></i> ' . $this->l('Automated Cron Job Setup') . '</h3>
            <p>' . $this->l('To keep your backlink statuses up to date without manual checks, configure your server (cPanel, Plesk, or Linux crontab) to run this task regularly:') . '</p>
            
            <div class="form-group" style="margin-top: 15px;">
                <label><strong>' . $this->l('Cron URL') . ':</strong></label>
                <input type="text" class="form-control" value="' . Tools::safeOutput($cronUrl) . '" readonly onclick="this.select();">
            </div>

            <div class="form-group" style="margin-top: 10px;">
                <label><strong>' . $this->l('cURL Command (e.g. Daily at 02:00 AM)') . ':</strong></label>
                <pre><code>0 2 * * * curl -s -L "' . $cronUrl . '" > /dev/null 2>&1</code></pre>
            </div>

            <div class="form-group" style="margin-top: 10px;">
                <label><strong>' . $this->l('Wget Command') . ':</strong></label>
                <pre><code>0 2 * * * wget -q -O - "' . $cronUrl . '" > /dev/null 2>&1</code></pre>
            </div>
        </div>';

        return $output;
    }

    private function renderConfigurationForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Monitor & Crawler Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Request Timeout (seconds)'),
                        'name' => 'BACKLINKMONITOR_TIMEOUT',
                        'class' => 'fixed-width-sm',
                        'desc' => $this->l('Recommended: 15 seconds. Timeout for cURL network requests (between 3 and 60 seconds).'),
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('User-Agent Header'),
                        'name' => 'BACKLINKMONITOR_USER_AGENT',
                        'rows' => 2,
                        'desc' => $this->l('Recommended: Standard modern desktop browser User-Agent. Prevents Cloudflare, Wordfence, and anti-bot systems from blocking backlink checks.'),
                        'required' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Follow HTTP Redirects'),
                        'name' => 'BACKLINKMONITOR_FOLLOW_REDIRECTS',
                        'is_bool' => true,
                        'desc' => $this->l('Recommended: Yes. Automatically follow 301/302 redirects to verify the final landing page.'),
                        'values' => [
                            ['id' => 'follow_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'follow_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Strict Anchor Text Matching'),
                        'name' => 'BACKLINKMONITOR_STRICT_ANCHOR',
                        'is_bool' => true,
                        'desc' => $this->l('Recommended: No. If disabled, anchor comparison is case-insensitive with whitespace normalization. If enabled, exact capitalization is required.'),
                        'values' => [
                            ['id' => 'strict_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'strict_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Auto-check on Save'),
                        'name' => 'BACKLINKMONITOR_AUTOCHECK',
                        'is_bool' => true,
                        'desc' => $this->l('Recommended: Yes. Automatically trigger a live check immediately when adding or editing a backlink.'),
                        'values' => [
                            ['id' => 'autocheck_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'autocheck_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'checkbox',
                        'label' => $this->l('Security Token'),
                        'name' => 'regenerate',
                        'values' => [
                            'query' => [
                                ['id' => 'cron_token', 'name' => $this->l('Regenerate Cron Secret Security Token upon saving')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save Settings'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBacklinkMonitorConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        // Read configuration values safely with fallbacks
        $timeoutVal = Configuration::get('BACKLINKMONITOR_TIMEOUT');
        if ($timeoutVal === false || $timeoutVal === '' || (int) $timeoutVal <= 0) {
            $timeoutVal = 15;
        }

        $userAgentVal = Configuration::get('BACKLINKMONITOR_USER_AGENT');
        if (empty($userAgentVal)) {
            $userAgentVal = BacklinkMonitorChecker::DEFAULT_USER_AGENT;
        }

        $redirectsVal = Configuration::get('BACKLINKMONITOR_FOLLOW_REDIRECTS');
        $redirectsVal = ($redirectsVal === false) ? 1 : (int) $redirectsVal;

        $strictVal = Configuration::get('BACKLINKMONITOR_STRICT_ANCHOR');
        $strictVal = ($strictVal === false) ? 0 : (int) $strictVal;

        $autocheckVal = Configuration::get('BACKLINKMONITOR_AUTOCHECK');
        $autocheckVal = ($autocheckVal === false) ? 1 : (int) $autocheckVal;

        $helper->fields_value['BACKLINKMONITOR_TIMEOUT'] = (int) $timeoutVal;
        $helper->fields_value['BACKLINKMONITOR_USER_AGENT'] = $userAgentVal;
        $helper->fields_value['BACKLINKMONITOR_FOLLOW_REDIRECTS'] = $redirectsVal;
        $helper->fields_value['BACKLINKMONITOR_STRICT_ANCHOR'] = $strictVal;
        $helper->fields_value['BACKLINKMONITOR_AUTOCHECK'] = $autocheckVal;
        $helper->fields_value['regenerate_cron_token'] = false;

        return $helper->generateForm([$fields_form]);
    }
}
