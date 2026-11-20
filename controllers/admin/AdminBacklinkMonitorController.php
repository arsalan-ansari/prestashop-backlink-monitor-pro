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

class AdminBacklinkMonitorController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'backlinkmonitor';
        $this->className = 'BacklinkMonitorLink';
        $this->identifier = 'id_backlink';
        $this->lang = false;
        $this->bootstrap = true;

        parent::__construct();

        // Ensure database schema is up-to-date and sync target statuses
        $this->ensureSchema();

        $this->actions = ['checknow', 'edit', 'delete'];

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->trans('Delete selected', [], 'Modules.Backlinkmonitor.Admin'),
                'confirm' => $this->trans('Delete selected backlinks?', [], 'Modules.Backlinkmonitor.Admin'),
                'icon' => 'icon-trash',
            ],
            'checkSelected' => [
                'text' => $this->trans('Check selected', [], 'Modules.Backlinkmonitor.Admin'),
                'icon' => 'icon-refresh',
            ],
            'enableSelection' => [
                'text' => $this->trans('Enable selection', [], 'Modules.Backlinkmonitor.Admin'),
                'icon' => 'icon-power-off text-success',
            ],
            'disableSelection' => [
                'text' => $this->trans('Disable selection', [], 'Modules.Backlinkmonitor.Admin'),
                'icon' => 'icon-power-off text-danger',
            ],
        ];

        $statusList = [
            BacklinkMonitorLink::STATUS_NEVER_CHECKED => $this->trans('Never Checked', [], 'Modules.Backlinkmonitor.Admin'),
            BacklinkMonitorLink::STATUS_WORKING => $this->trans('Working', [], 'Modules.Backlinkmonitor.Admin'),
            BacklinkMonitorLink::STATUS_NOFOLLOW => $this->trans('NoFollow', [], 'Modules.Backlinkmonitor.Admin'),
            BacklinkMonitorLink::STATUS_BROKEN => $this->trans('Broken', [], 'Modules.Backlinkmonitor.Admin'),
        ];

        $targetStatusList = [
            BacklinkMonitorLink::STATUS_NEVER_CHECKED => $this->trans('Never Checked', [], 'Modules.Backlinkmonitor.Admin'),
            BacklinkMonitorLink::STATUS_WORKING => $this->trans('Working', [], 'Modules.Backlinkmonitor.Admin'),
            BacklinkMonitorLink::STATUS_BROKEN => $this->trans('Broken', [], 'Modules.Backlinkmonitor.Admin'),
        ];

        $this->fields_list = [
            'id_backlink' => [
                'title' => $this->trans('ID', [], 'Admin.Global'),
                'align' => 'center',
                'class' => 'fixed-width-xs',
            ],
            'url_source' => [
                'title' => $this->trans('Source URL', [], 'Modules.Backlinkmonitor.Admin'),
                'callback' => 'printSourceUrl',
            ],
            'url_target' => [
                'title' => $this->trans('Target URL', [], 'Modules.Backlinkmonitor.Admin'),
                'callback' => 'printTargetUrl',
            ],
            'target_status' => [
                'title' => $this->trans('Target Status', [], 'Modules.Backlinkmonitor.Admin'),
                'align' => 'center',
                'type' => 'select',
                'list' => $targetStatusList,
                'filter_key' => 'a!target_status',
                'callback' => 'printTargetStatusBadge',
                'class' => 'fixed-width-sm',
            ],
            'target_http_code' => [
                'title' => $this->trans('Target HTTP', [], 'Modules.Backlinkmonitor.Admin'),
                'align' => 'center',
                'callback' => 'printTargetHttpCode',
                'class' => 'fixed-width-xs',
            ],
            'anchor_text' => [
                'title' => $this->trans('Expected Anchor', [], 'Modules.Backlinkmonitor.Admin'),
                'callback' => 'printExpectedAnchor',
            ],
            'found_anchor_text' => [
                'title' => $this->trans('Found Anchor', [], 'Modules.Backlinkmonitor.Admin'),
                'callback' => 'printFoundAnchor',
            ],
            'anchor_mismatch' => [
                'title' => $this->trans('Anchor Match', [], 'Modules.Backlinkmonitor.Admin'),
                'align' => 'center',
                'type' => 'select',
                'list' => [
                    0 => $this->trans('Match', [], 'Modules.Backlinkmonitor.Admin'),
                    1 => $this->trans('Mismatch', [], 'Modules.Backlinkmonitor.Admin'),
                ],
                'filter_key' => 'a!anchor_mismatch',
                'callback' => 'printAnchorMismatch',
                'class' => 'fixed-width-sm',
            ],
            'status' => [
                'title' => $this->trans('Status', [], 'Modules.Backlinkmonitor.Admin'),
                'align' => 'center',
                'type' => 'select',
                'list' => $statusList,
                'filter_key' => 'a!status',
                'callback' => 'printStatusBadge',
                'class' => 'fixed-width-sm',
            ],
            'rel_attribute' => [
                'title' => $this->trans('Rel / Type', [], 'Modules.Backlinkmonitor.Admin'),
                'align' => 'center',
                'callback' => 'printRelAttribute',
                'class' => 'fixed-width-sm',
            ],
            'http_code' => [
                'title' => $this->trans('Source HTTP', [], 'Modules.Backlinkmonitor.Admin'),
                'align' => 'center',
                'callback' => 'printSourceHttpCode',
                'class' => 'fixed-width-xs',
            ],
            'check_message' => [
                'title' => $this->trans('Diagnostic / Message', [], 'Modules.Backlinkmonitor.Admin'),
                'callback' => 'printCheckMessage',
            ],
            'notes' => [
                'title' => $this->trans('Comments', [], 'Modules.Backlinkmonitor.Admin'),
                'callback' => 'printNotes',
                'filter_key' => 'a!notes',
                'class' => 'bm-col-notes',
            ],
            'last_checked' => [
                'title' => $this->trans('Last Checked', [], 'Modules.Backlinkmonitor.Admin'),
                'type' => 'datetime',
                'align' => 'text-right',
            ],
            'active' => [
                'title' => $this->trans('Active', [], 'Admin.Global'),
                'align' => 'center',
                'active' => 'status',
                'type' => 'bool',
                'orderby' => false,
                'class' => 'fixed-width-xs',
            ],
        ];

        $this->_select = 'a.*';
        $this->_orderBy = 'id_backlink';
        $this->_orderWay = 'DESC';
    }

    /**
     * Ensure database table has all modern columns and synchronize target statuses.
     */
    private function ensureSchema()
    {
        BacklinkMonitorLink::ensureSchema();
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addCSS($this->module->getPathUri() . 'views/css/admin.css?v=' . $this->module->version);
        $this->addJS($this->module->getPathUri() . 'views/js/admin.js?v=' . $this->module->version);

        if (class_exists('Media')) {
            Media::addJsDef([
                'bm_ajax_url' => self::$currentIndex . '&token=' . $this->token,
            ]);
        }
    }

    public function printSourceUrl($value, $row)
    {
        $short = (Tools::strlen($value) > 45) ? Tools::substr($value, 0, 42) . '...' : $value;
        $html = '<span title="' . Tools::safeOutput($value) . '">' . Tools::safeOutput($short) . '</span>';
        $html .= ' <a href="' . Tools::safeOutput($value) . '" target="_blank" rel="noopener noreferrer" class="bm-external-link" title="Open Source URL"><i class="icon-external-link"></i></a>';
        if (!empty($row['redirect_url'])) {
            $html .= '<br><small class="text-muted"><i class="icon-long-arrow-right"></i> ' . Tools::safeOutput(Tools::substr($row['redirect_url'], 0, 38)) . '...</small>';
        }
        return $html;
    }

    public function printTargetUrl($value, $row)
    {
        $short = (Tools::strlen($value) > 45) ? Tools::substr($value, 0, 42) . '...' : $value;
        $html = '<span title="' . Tools::safeOutput($value) . '">' . Tools::safeOutput($short) . '</span>';
        $html .= ' <a href="' . Tools::safeOutput($value) . '" target="_blank" rel="noopener noreferrer" class="bm-external-link" title="Open Target URL"><i class="icon-external-link"></i></a>';
        if (!empty($row['target_redirect_url'])) {
            $html .= '<br><small class="text-muted"><i class="icon-long-arrow-right"></i> ' . Tools::safeOutput(Tools::substr($row['target_redirect_url'], 0, 38)) . '...</small>';
        }
        return $html;
    }

    public function printExpectedAnchor($value, $row)
    {
        if (empty($value)) {
            return '<span class="text-muted"><em>' . $this->trans('None specified', [], 'Modules.Backlinkmonitor.Admin') . '</em></span>';
        }
        return '<span class="bm-anchor-text" title="' . Tools::safeOutput($value) . '">' . Tools::safeOutput($value) . '</span>';
    }

    public function printFoundAnchor($value, $row)
    {
        if (empty($value)) {
            return '<span class="text-muted">-</span>';
        }
        if (strpos($value, '[Image:') === 0) {
            return '<span class="label label-info" title="' . Tools::safeOutput($value) . '"><i class="icon-picture"></i> ' . Tools::safeOutput($value) . '</span>';
        }
        return '<span class="bm-anchor-text" title="' . Tools::safeOutput($value) . '">' . Tools::safeOutput($value) . '</span>';
    }

    public function printStatusBadge($value, $row)
    {
        $map = [
            BacklinkMonitorLink::STATUS_NEVER_CHECKED => ['label' => 'Never Checked', 'class' => 'label-default'],
            BacklinkMonitorLink::STATUS_WORKING => ['label' => 'Working', 'class' => 'label-success'],
            BacklinkMonitorLink::STATUS_NOFOLLOW => ['label' => 'NoFollow', 'class' => 'label-warning'],
            BacklinkMonitorLink::STATUS_BROKEN => ['label' => 'Broken', 'class' => 'label-danger'],
        ];
        $info = isset($map[(int) $value]) ? $map[(int) $value] : $map[0];

        return '<span class="label ' . $info['class'] . '">' . $info['label'] . '</span>';
    }

    public function printTargetStatusBadge($value, $row)
    {
        $status = (int) $value;

        // Auto-infer status if target_status is 0 but target_http_code was recorded
        if ($status === BacklinkMonitorLink::STATUS_NEVER_CHECKED && !empty($row['target_http_code'])) {
            $code = (int) $row['target_http_code'];
            if ($code >= 200 && $code < 400) {
                $status = BacklinkMonitorLink::STATUS_WORKING;
            } elseif ($code >= 400 || $code === 0) {
                $status = BacklinkMonitorLink::STATUS_BROKEN;
            }
        }

        $map = [
            BacklinkMonitorLink::STATUS_NEVER_CHECKED => ['label' => 'Never Checked', 'class' => 'label-default'],
            BacklinkMonitorLink::STATUS_WORKING => ['label' => 'Working', 'class' => 'label-success'],
            BacklinkMonitorLink::STATUS_BROKEN => ['label' => 'Broken', 'class' => 'label-danger'],
        ];
        $info = isset($map[$status]) ? $map[$status] : $map[0];

        return '<span class="label ' . $info['class'] . '">' . $info['label'] . '</span>';
    }

    public function printTargetHttpCode($value, $row)
    {
        $code = (int) $value;
        if ($code <= 0) {
            return '<span class="text-muted">-</span>';
        }
        $class = ($code >= 200 && $code < 400) ? 'label-success' : 'label-danger';
        return '<span class="label ' . $class . '">' . $code . '</span>';
    }

    public function printSourceHttpCode($value, $row)
    {
        $code = (int) $value;
        if ($code <= 0) {
            return '<span class="text-muted">-</span>';
        }
        $class = ($code >= 200 && $code < 400) ? 'label-success' : 'label-danger';
        return '<span class="label ' . $class . '">' . $code . '</span>';
    }

    public function printRelAttribute($value, $row)
    {
        if ((int) $row['status'] === BacklinkMonitorLink::STATUS_NEVER_CHECKED) {
            return '<span class="text-muted">-</span>';
        }

        if ((int) $row['status'] === BacklinkMonitorLink::STATUS_BROKEN) {
            return '<span class="label label-default">N/A</span>';
        }

        $rel = !empty($value) ? $value : ($row['is_dofollow'] ? 'dofollow' : 'nofollow');

        if (strpos($rel, 'sponsored') !== false) {
            return '<span class="label label-info" title="' . Tools::safeOutput($rel) . '"><i class="icon-money"></i> Sponsored</span>';
        }
        if (strpos($rel, 'ugc') !== false) {
            return '<span class="label label-info" title="' . Tools::safeOutput($rel) . '"><i class="icon-comments"></i> UGC</span>';
        }
        if (strpos($rel, 'nofollow') !== false) {
            return '<span class="label label-warning" title="' . Tools::safeOutput($rel) . '">NoFollow</span>';
        }

        return '<span class="label label-success" title="' . Tools::safeOutput($rel) . '">Dofollow</span>';
    }

    public function printAnchorMismatch($value, $row)
    {
        if (empty($row['anchor_text'])) {
            return '<span class="text-muted" title="No expected anchor set">-</span>';
        }

        if ($row['anchor_mismatch'] === null) {
            return '<span class="text-muted">-</span>';
        }

        return $row['anchor_mismatch']
            ? '<span class="label label-danger"><i class="icon-warning-sign"></i> Mismatch</span>'
            : '<span class="label label-success"><i class="icon-check"></i> Match</span>';
    }

    public function printCheckMessage($value, $row)
    {
        if (empty($value)) {
            return '<span class="text-muted">-</span>';
        }

        $short = (Tools::strlen($value) > 38) ? Tools::substr($value, 0, 35) . '...' : $value;
        $class = 'text-muted';
        if ((int) $row['status'] === BacklinkMonitorLink::STATUS_BROKEN) {
            $class = 'text-danger font-weight-bold';
        } elseif ((int) $row['status'] === BacklinkMonitorLink::STATUS_WORKING) {
            $class = 'text-success';
        }

        return '<span class="' . $class . '" title="' . Tools::safeOutput($value) . '">' . Tools::safeOutput($short) . '</span>';
    }

    public function printNotes($value, $row)
    {
        $raw = trim(strip_tags((string) $value));
        if ($raw === '') {
            return '<span class="text-muted">-</span>';
        }

        $short = (Tools::strlen($raw) > 35) ? Tools::substr($raw, 0, 32) . '...' : $raw;
        return '<span class="bm-notes-text" title="' . Tools::safeOutput($raw) . '"><i class="icon-comment text-muted"></i> ' . Tools::safeOutput($short) . '</span>';
    }

    /**
     * Row action: "Check Now"
     */
    public function displayChecknowLink($token, $id)
    {
        $link = self::$currentIndex . '&id_backlink=' . (int) $id . '&checkSingle=1&token=' . ($token !== null ? $token : $this->token);
        return '<a href="' . $link . '" class="btn btn-default" title="' . $this->trans('Check Now', [], 'Modules.Backlinkmonitor.Admin') . '"><i class="icon-refresh"></i> ' . $this->trans('Check', [], 'Modules.Backlinkmonitor.Admin') . '</a>';
    }

    public function renderList()
    {
        // Add Action Toolbar buttons
        $this->toolbar_btn['new'] = [
            'href' => self::$currentIndex . '&add' . $this->table . '&token=' . $this->token,
            'desc' => $this->trans('Add new backlink', [], 'Modules.Backlinkmonitor.Admin'),
            'icon' => 'process-icon-new',
        ];

        $this->toolbar_btn['check_all'] = [
            'href' => '#',
            'desc' => $this->trans('Check All Backlinks', [], 'Modules.Backlinkmonitor.Admin'),
            'icon' => 'process-icon-refresh',
            'class' => 'bm-check-all-btn',
        ];

        $this->toolbar_btn['export_csv'] = [
            'href' => self::$currentIndex . '&exportCSV=1&token=' . $this->token,
            'desc' => $this->trans('Export CSV Report', [], 'Modules.Backlinkmonitor.Admin'),
            'icon' => 'process-icon-export',
        ];

        $this->toolbar_btn['export_report'] = [
            'href' => self::$currentIndex . '&exportAuditReport=1&token=' . $this->token,
            'desc' => $this->trans('Export Executive Report', [], 'Modules.Backlinkmonitor.Admin'),
            'icon' => 'process-icon-stats',
            'target' => '_blank',
        ];

        $this->toolbar_btn['settings'] = [
            'href' => $this->context->link->getAdminLink('AdminModules') . '&configure=' . $this->module->name,
            'desc' => $this->trans('Settings', [], 'Admin.Global'),
            'icon' => 'process-icon-cogs',
        ];

        return $this->renderKpiPanel() . parent::renderList();
    }

    private function renderKpiPanel()
    {
        $counters = BacklinkMonitorLink::getCounters();
        $this->context->smarty->assign([
            'counters' => $counters,
            'current_url' => self::$currentIndex . '&token=' . $this->token,
            'auto_check_batch' => (bool) Tools::getValue('autoCheckBatch', false),
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'backlinkmonitor/views/templates/admin/kpi.tpl');
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend' => [
                'title' => $this->trans('Backlink Configuration', [], 'Modules.Backlinkmonitor.Admin'),
                'icon' => 'icon-link',
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->trans('Source URL', [], 'Modules.Backlinkmonitor.Admin'),
                    'name' => 'url_source',
                    'required' => true,
                    'desc' => $this->trans('The full URL of the page hosting your backlink (e.g. https://partner-site.com/blog-post).', [], 'Modules.Backlinkmonitor.Admin'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Target URL', [], 'Modules.Backlinkmonitor.Admin'),
                    'name' => 'url_target',
                    'required' => true,
                    'desc' => $this->trans('The destination URL on your shop that the backlink should point to (e.g. https://myshop.com/best-products).', [], 'Modules.Backlinkmonitor.Admin'),
                ],
                [
                    'type' => 'text',
                    'label' => $this->trans('Expected Anchor Text', [], 'Modules.Backlinkmonitor.Admin'),
                    'name' => 'anchor_text',
                    'required' => false,
                    'desc' => $this->trans('Optional. The exact anchor text you agreed on. If actual text differs, a mismatch alert is triggered.', [], 'Modules.Backlinkmonitor.Admin'),
                ],
                [
                    'type' => 'textarea',
                    'label' => $this->trans('Comments / Notes', [], 'Modules.Backlinkmonitor.Admin'),
                    'name' => 'notes',
                    'required' => false,
                    'desc' => $this->trans('Add your custom comments, notes, cost, contact person, or article topic.', [], 'Modules.Backlinkmonitor.Admin'),
                ],
                [
                    'type' => 'switch',
                    'label' => $this->trans('Active Status', [], 'Admin.Global'),
                    'name' => 'active',
                    'desc' => $this->trans('Inactive backlinks are skipped during batch and automated cron checks.', [], 'Modules.Backlinkmonitor.Admin'),
                    'values' => [
                        ['id' => 'active_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
                        ['id' => 'active_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->trans('Save & Verify', [], 'Modules.Backlinkmonitor.Admin'),
            ],
        ];

        $obj = $this->loadObject(true);
        if (!$obj) {
            return;
        }

        if (!$obj->id) {
            $this->fields_value = [
                'url_target' => empty($obj->url_target) ? Tools::getShopDomainSsl(true) : $obj->url_target,
                'active' => 1,
                'target_http_code' => 0,
                'http_code' => 0,
                'target_status' => 0,
                'status' => 0,
            ];
        }

        return parent::renderForm();
    }

    public function processAdd()
    {
        if (Tools::isSubmit('url_source')) {
            $_POST['url_source'] = BacklinkMonitorChecker::cleanUrl(Tools::getValue('url_source'));
        }
        if (Tools::isSubmit('url_target')) {
            $_POST['url_target'] = BacklinkMonitorChecker::cleanUrl(Tools::getValue('url_target'));
        }
        if (Tools::isSubmit('anchor_text')) {
            $_POST['anchor_text'] = trim((string) Tools::getValue('anchor_text'));
        }

        if (!Tools::getValue('target_http_code') && Tools::getValue('target_http_code') !== '0') {
            $_POST['target_http_code'] = 0;
        }
        if (!Tools::getValue('http_code') && Tools::getValue('http_code') !== '0') {
            $_POST['http_code'] = 0;
        }
        if (!Tools::getValue('target_status') && Tools::getValue('target_status') !== '0') {
            $_POST['target_status'] = 0;
        }
        if (!Tools::getValue('status') && Tools::getValue('status') !== '0') {
            $_POST['status'] = 0;
        }

        return parent::processAdd();
    }

    public function processUpdate()
    {
        if (Tools::isSubmit('url_source')) {
            $_POST['url_source'] = BacklinkMonitorChecker::cleanUrl(Tools::getValue('url_source'));
        }
        if (Tools::isSubmit('url_target')) {
            $_POST['url_target'] = BacklinkMonitorChecker::cleanUrl(Tools::getValue('url_target'));
        }
        if (Tools::isSubmit('anchor_text')) {
            $_POST['anchor_text'] = trim((string) Tools::getValue('anchor_text'));
        }

        if (!Tools::getValue('target_http_code') && Tools::getValue('target_http_code') !== '0') {
            $_POST['target_http_code'] = 0;
        }
        if (!Tools::getValue('http_code') && Tools::getValue('http_code') !== '0') {
            $_POST['http_code'] = 0;
        }

        return parent::processUpdate();
    }

    public function initContent()
    {
        // Handle AJAX batch requests
        if (Tools::isSubmit('ajax') || Tools::getValue('ajax')) {
            $action = Tools::getValue('action');
            if ($action === 'getBacklinkIds') {
                $this->ajaxProcessGetBacklinkIds();
            } elseif ($action === 'checkSingleBacklink') {
                $this->ajaxProcessCheckSingleBacklink();
            }
        }

        // Single backlink check trigger
        if (Tools::getValue('checkSingle') && Tools::getValue('id_backlink')) {
            BacklinkMonitorChecker::pingDatabase();
            $res = BacklinkMonitorChecker::check((int) Tools::getValue('id_backlink'));
            BacklinkMonitorChecker::pingDatabase();

            if ($res && Validate::isLoadedObject($res)) {
                $statusLabel = BacklinkMonitorLink::getStatusLabel($res->status);
                $this->confirmations[] = sprintf($this->trans('Backlink #%d checked: %s.', [], 'Modules.Backlinkmonitor.Admin'), (int) $res->id, $statusLabel);
            } else {
                $this->errors[] = $this->trans('Failed to check backlink.', [], 'Modules.Backlinkmonitor.Admin');
            }
        }

        // Check all backlinks: redirect to clean auto-batch mode to avoid Symfony / Doctrine MySQL timeouts
        if (Tools::getValue('checkAllBacklinks')) {
            Tools::redirectAdmin(self::$currentIndex . '&autoCheckBatch=1&token=' . $this->token);
        }

        // Export CSV trigger
        if (Tools::getValue('exportCSV')) {
            $this->exportCSV();
            exit;
        }

        // Export Executive Audit Report trigger
        if (Tools::getValue('exportAuditReport')) {
            $this->exportExecutiveReport();
            exit;
        }

        parent::initContent();
    }

    /**
     * AJAX action: return list of backlink IDs to check in batch.
     */
    public function ajaxProcessGetBacklinkIds()
    {
        BacklinkMonitorChecker::pingDatabase();
        $onlyActive = (bool) Tools::getValue('onlyActive', false);
        $sql = 'SELECT id_backlink, url_source FROM `' . _DB_PREFIX_ . 'backlinkmonitor`';
        if ($onlyActive) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY id_backlink ASC';
        $rows = Db::getInstance()->executeS($sql);

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'links' => $rows ? $rows : [],
            'total' => $rows ? count($rows) : 0,
        ]);
        exit;
    }

    /**
     * AJAX action: check a single backlink without keeping long idle MySQL connections.
     */
    public function ajaxProcessCheckSingleBacklink()
    {
        BacklinkMonitorChecker::pingDatabase();
        $id_backlink = (int) Tools::getValue('id_backlink');

        if (!$id_backlink) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Missing backlink ID']);
            exit;
        }

        $link = BacklinkMonitorChecker::check($id_backlink);
        BacklinkMonitorChecker::pingDatabase();

        if (!$link || !Validate::isLoadedObject($link)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Failed to check backlink #' . $id_backlink]);
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'id' => $id_backlink,
            'status' => (int) $link->status,
            'status_label' => BacklinkMonitorLink::getStatusLabel($link->status),
            'is_dofollow' => $link->is_dofollow,
            'rel' => $link->rel_attribute,
            'anchor' => $link->found_anchor_text,
            'anchor_mismatch' => $link->anchor_mismatch,
            'http_code' => $link->http_code,
            'target_status' => (int) $link->target_status,
            'target_http_code' => $link->target_http_code,
            'message' => $link->check_message,
        ]);
        exit;
    }

    public function processBulkCheckSelected()
    {
        $ids = Tools::getValue($this->table . 'Box');
        $checked = 0;
        if (is_array($ids)) {
            foreach ($ids as $id) {
                BacklinkMonitorChecker::pingDatabase();
                BacklinkMonitorChecker::check((int) $id);
                BacklinkMonitorChecker::pingDatabase();
                $checked++;
            }
        }
        $this->confirmations[] = sprintf($this->trans('%d selected backlink(s) checked.', [], 'Modules.Backlinkmonitor.Admin'), $checked);
    }

    public function processBulkEnableSelection()
    {
        $ids = Tools::getValue($this->table . 'Box');
        if (is_array($ids) && !empty($ids)) {
            $cleanIds = array_map('intval', $ids);
            Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'backlinkmonitor` SET active = 1 WHERE id_backlink IN (' . implode(',', $cleanIds) . ')');
            $this->confirmations[] = $this->trans('Selected backlinks enabled.', [], 'Modules.Backlinkmonitor.Admin');
        }
    }

    public function processBulkDisableSelection()
    {
        $ids = Tools::getValue($this->table . 'Box');
        if (is_array($ids) && !empty($ids)) {
            $cleanIds = array_map('intval', $ids);
            Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . 'backlinkmonitor` SET active = 0 WHERE id_backlink IN (' . implode(',', $cleanIds) . ')');
            $this->confirmations[] = $this->trans('Selected backlinks disabled.', [], 'Modules.Backlinkmonitor.Admin');
        }
    }

    /**
     * Export complete dataset as Excel-ready UTF-8 CSV with BOM.
     */
    private function exportCSV()
    {
        BacklinkMonitorChecker::pingDatabase();
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'backlinkmonitor` ORDER BY id_backlink DESC'
        );

        $filename = 'backlinks_report_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Output UTF-8 BOM so Excel opens accents and special characters cleanly
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, [
            'ID',
            'Source URL',
            'Source Redirect URL',
            'Target URL',
            'Target Redirect URL',
            'Status',
            'Rel Type',
            'Is Dofollow',
            'Link Type',
            'Expected Anchor',
            'Found Anchor',
            'Anchor Match',
            'Source HTTP Code',
            'Target Status',
            'Target HTTP Code',
            'Diagnostic Message',
            'Active',
            'Last Checked',
            'Target Last Checked',
            'Date Added',
            'Notes',
        ]);

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $anchorMatchText = 'N/A';
                if (!empty($row['anchor_text'])) {
                    if ($row['anchor_mismatch'] !== null) {
                        $anchorMatchText = $row['anchor_mismatch'] ? 'Mismatch' : 'Match';
                    }
                }

                $dofollowText = 'N/A';
                if ($row['is_dofollow'] !== null) {
                    $dofollowText = $row['is_dofollow'] ? 'Yes' : 'No';
                }

                $targetStatusVal = (int) $row['target_status'];
                if ($targetStatusVal === BacklinkMonitorLink::STATUS_NEVER_CHECKED && !empty($row['target_http_code'])) {
                    $targetHttp = (int) $row['target_http_code'];
                    if ($targetHttp >= 200 && $targetHttp < 400) {
                        $targetStatusVal = BacklinkMonitorLink::STATUS_WORKING;
                    } elseif ($targetHttp >= 400 || $targetHttp === 0) {
                        $targetStatusVal = BacklinkMonitorLink::STATUS_BROKEN;
                    }
                }

                fputcsv($output, [
                    $row['id_backlink'],
                    $row['url_source'],
                    $row['redirect_url'] ?: '',
                    $row['url_target'],
                    $row['target_redirect_url'] ?: '',
                    BacklinkMonitorLink::getStatusLabel($row['status']),
                    $row['rel_attribute'] ?: '',
                    $dofollowText,
                    $row['link_type'] ?: '',
                    $row['anchor_text'] ?: '',
                    $row['found_anchor_text'] ?: '',
                    $anchorMatchText,
                    $row['http_code'] ?: '',
                    BacklinkMonitorLink::getTargetStatusLabel($targetStatusVal),
                    $row['target_http_code'] ?: '',
                    $row['check_message'] ?: '',
                    $row['active'] ? 'Active' : 'Inactive',
                    $row['last_checked'] ?: 'Never',
                    $row['target_last_checked'] ?: 'Never',
                    $row['date_add'],
                    strip_tags($row['notes']),
                ]);
            }
        }

        fclose($output);
    }

    /**
     * Export Executive HTML / Printable Audit Report.
     */
    private function exportExecutiveReport()
    {
        BacklinkMonitorChecker::pingDatabase();
        $rows = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'backlinkmonitor` ORDER BY id_backlink DESC'
        );

        $counters = BacklinkMonitorLink::getCounters();
        $shopName = Configuration::get('PS_SHOP_NAME');
        $shopUrl = Tools::getShopDomainSsl(true);
        $total = $counters['total'];
        $working = $counters['working'];
        $broken = $counters['broken'];
        $nofollow = $counters['nofollow'];
        $never = $counters['never_checked'];
        $mismatches = $counters['mismatches'];
        $targetBroken = $counters['target_broken'];

        $healthScore = $total > 0 ? round(($working / $total) * 100) : 0;

        header('Content-Type: text/html; charset=UTF-8');
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backlink Audit Report - <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 24px;
            font-size: 14px;
            line-height: 1.5;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px 30px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .header h1 { margin: 0 0 6px 0; font-size: 24px; color: #0f172a; font-weight: 700; }
        .header p { margin: 0; color: #64748b; font-size: 13px; }
        .actions { display: flex; gap: 10px; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all .15s ease;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-default { background: #e2e8f0; color: #334155; }
        .btn-default:hover { background: #cbd5e1; }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .kpi-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
            text-align: left;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .kpi-title { font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 600; letter-spacing: .5px; }
        .kpi-num { font-size: 26px; font-weight: 700; margin-top: 6px; line-height: 1; }
        .kpi-working { color: #16a34a; }
        .kpi-broken { color: #dc2626; }
        .kpi-nofollow { color: #d97706; }
        .kpi-mismatch { color: #ea580c; }
        .kpi-score { color: #2563eb; }

        .health-bar-container {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .health-bar-label { display: flex; justify-content: space-between; font-weight: 600; margin-bottom: 8px; }
        .health-progress-bg { background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden; }
        .health-progress-fill { background: linear-gradient(90deg, #10b981, #059669); height: 100%; border-radius: 6px; }

        .table-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            background: #f1f5f9;
            padding: 12px 16px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #475569;
            font-weight: 600;
            border-bottom: 1px solid #e2e8f0;
        }
        td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
        tr:hover td { background: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .badge-info { background: #e0f2fe; color: #0369a1; }
        .badge-default { background: #f1f5f9; color: #64748b; }

        .url-box { max-width: 280px; word-break: break-all; font-family: monospace; font-size: 12px; }
        .anchor-box { font-weight: 500; }
        .text-muted { color: #94a3b8; }
        .msg-box { font-size: 12px; max-width: 250px; }

        @media print {
            body { background: #fff; padding: 0; font-size: 11px; }
            .header, .kpi-card, .table-card, .health-bar-container { box-shadow: none; border: 1px solid #ccc; }
            .actions { display: none !important; }
            .url-box { max-width: none; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Backlink Audit & Monitoring Report</h1>
            <p><strong>Shop:</strong> <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($shopUrl, ENT_QUOTES, 'UTF-8'); ?>) &bull; <strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
        <div class="actions">
            <button onclick="window.print()" class="btn btn-primary">&#128438; Print / Save as PDF</button>
            <a href="<?php echo self::$currentIndex . '&exportCSV=1&token=' . $this->token; ?>" class="btn btn-default">&#128196; Download CSV</a>
            <a href="<?php echo self::$currentIndex . '&token=' . $this->token; ?>" class="btn btn-default">&larr; Back to Monitor</a>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-title">Overall Health Score</div>
            <div class="kpi-num kpi-score"><?php echo $healthScore; ?>%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Total Monitored</div>
            <div class="kpi-num"><?php echo (int) $total; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Working (Dofollow)</div>
            <div class="kpi-num kpi-working"><?php echo (int) $working; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">NoFollow / Sponsored</div>
            <div class="kpi-num kpi-nofollow"><?php echo (int) $nofollow; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Broken / Missing</div>
            <div class="kpi-num kpi-broken"><?php echo (int) $broken; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Anchor Mismatches</div>
            <div class="kpi-num kpi-mismatch"><?php echo (int) $mismatches; ?></div>
        </div>
        <div class="kpi-card">
            <div class="kpi-title">Target URL Issues</div>
            <div class="kpi-num kpi-broken"><?php echo (int) $targetBroken; ?></div>
        </div>
    </div>

    <div class="health-bar-container">
        <div class="health-bar-label">
            <span>Backlink Profile Health</span>
            <span><?php echo $working; ?> / <?php echo $total; ?> active links working (<?php echo $healthScore; ?>%)</span>
        </div>
        <div class="health-progress-bg">
            <div class="health-progress-fill" style="width: <?php echo min(100, max(0, $healthScore)); ?>%;"></div>
        </div>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Source Page</th>
                    <th>Target Landing Page</th>
                    <th>Anchor Analysis</th>
                    <th>Rel / Type</th>
                    <th>HTTP Codes</th>
                    <th>Diagnostic Message</th>
                    <th>Comments</th>
                    <th>Last Checked</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)) : ?>
                    <tr><td colspan="10" style="text-align:center; padding: 30px;">No backlinks recorded yet.</td></tr>
                <?php else : ?>
                    <?php foreach ($rows as $row) :
                        $st = (int) $row['status'];
                        $badgeClass = 'badge-default';
                        $statusText = 'Never Checked';
                        if ($st === BacklinkMonitorLink::STATUS_WORKING) {
                            $badgeClass = 'badge-success';
                            $statusText = 'Working';
                        } elseif ($st === BacklinkMonitorLink::STATUS_NOFOLLOW) {
                            $badgeClass = 'badge-warning';
                            $statusText = 'NoFollow';
                        } elseif ($st === BacklinkMonitorLink::STATUS_BROKEN) {
                            $badgeClass = 'badge-danger';
                            $statusText = 'Broken';
                        }

                        $tgtSt = (int) $row['target_status'];
                        if ($tgtSt === BacklinkMonitorLink::STATUS_NEVER_CHECKED && !empty($row['target_http_code'])) {
                            $code = (int) $row['target_http_code'];
                            if ($code >= 200 && $code < 400) {
                                $tgtSt = BacklinkMonitorLink::STATUS_WORKING;
                            } elseif ($code >= 400 || $code === 0) {
                                $tgtSt = BacklinkMonitorLink::STATUS_BROKEN;
                            }
                        }
                    ?>
                    <tr>
                        <td><strong>#<?php echo (int) $row['id_backlink']; ?></strong></td>
                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $statusText; ?></span></td>
                        <td>
                            <div class="url-box">
                                <a href="<?php echo htmlspecialchars($row['url_source'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo htmlspecialchars($row['url_source'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                        </td>
                        <td>
                            <div class="url-box">
                                <a href="<?php echo htmlspecialchars($row['url_target'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo htmlspecialchars($row['url_target'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <?php if ($tgtSt === BacklinkMonitorLink::STATUS_BROKEN) : ?>
                                    <br><span class="badge badge-danger">Target Error</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="anchor-box">
                                <div><strong>Exp:</strong> <?php echo htmlspecialchars($row['anchor_text'] ?: 'Any', ENT_QUOTES, 'UTF-8'); ?></div>
                                <div><strong>Got:</strong> <?php echo htmlspecialchars($row['found_anchor_text'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php if (!empty($row['anchor_text']) && $row['anchor_mismatch'] !== null) : ?>
                                    <?php if ($row['anchor_mismatch']) : ?>
                                        <span class="badge badge-danger">Mismatch</span>
                                    <?php else : ?>
                                        <span class="badge badge-success">Match</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            if ($st === BacklinkMonitorLink::STATUS_NEVER_CHECKED) {
                                echo '<span class="text-muted">-</span>';
                            } elseif ($st === BacklinkMonitorLink::STATUS_BROKEN) {
                                echo '<span class="text-muted">N/A</span>';
                            } else {
                                $rel = $row['rel_attribute'] ?: ($row['is_dofollow'] ? 'dofollow' : 'nofollow');
                                if (strpos($rel, 'sponsored') !== false) {
                                    echo '<span class="badge badge-info">Sponsored</span>';
                                } elseif (strpos($rel, 'ugc') !== false) {
                                    echo '<span class="badge badge-info">UGC</span>';
                                } elseif (strpos($rel, 'nofollow') !== false) {
                                    echo '<span class="badge badge-warning">NoFollow</span>';
                                } else {
                                    echo '<span class="badge badge-success">Dofollow</span>';
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <div>Src: <strong><?php echo $row['http_code'] ? (int) $row['http_code'] : '-'; ?></strong></div>
                            <div>Tgt: <strong><?php echo $row['target_http_code'] ? (int) $row['target_http_code'] : '-'; ?></strong></div>
                        </td>
                        <td>
                            <div class="msg-box <?php echo $st === BacklinkMonitorLink::STATUS_BROKEN ? 'kpi-broken' : 'text-muted'; ?>">
                                <?php echo htmlspecialchars($row['check_message'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-size: 12px; max-width: 180px; color: #475569;">
                                <?php echo !empty($row['notes']) ? htmlspecialchars(strip_tags($row['notes']), ENT_QUOTES, 'UTF-8') : '<span class="text-muted">-</span>'; ?>
                            </div>
                        </td>
                        <td style="white-space:nowrap; font-size:12px;">
                            <?php echo $row['last_checked'] ? htmlspecialchars($row['last_checked'], ENT_QUOTES, 'UTF-8') : '<span class="text-muted">Never</span>'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
        <?php
    }
}
