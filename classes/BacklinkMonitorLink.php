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

class BacklinkMonitorLink extends ObjectModel
{
    const STATUS_NEVER_CHECKED = 0;
    const STATUS_WORKING = 1;
    const STATUS_NOFOLLOW = 2;
    const STATUS_BROKEN = 3;

    public $id_backlink;
    public $url_source;
    public $redirect_url;
    public $url_target;
    public $target_redirect_url;
    public $anchor_text;
    public $found_anchor_text;
    public $anchor_mismatch;
    public $status = self::STATUS_NEVER_CHECKED;
    public $http_code = 0;
    public $is_dofollow;
    public $rel_attribute;
    public $link_type;
    public $check_message;
    public $target_status = self::STATUS_NEVER_CHECKED;
    public $target_http_code = 0;
    public $target_last_checked = null;
    public $notes;
    public $active = 1;
    public $last_checked;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'backlinkmonitor',
        'primary' => 'id_backlink',
        'fields' => [
            'url_source' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'required' => true, 'size' => 500],
            'redirect_url' => ['type' => self::TYPE_STRING, 'size' => 500, 'allow_null' => true],
            'url_target' => ['type' => self::TYPE_STRING, 'validate' => 'isUrl', 'required' => true, 'size' => 500],
            'target_redirect_url' => ['type' => self::TYPE_STRING, 'size' => 500, 'allow_null' => true],
            'anchor_text' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255, 'allow_null' => true],
            'found_anchor_text' => ['type' => self::TYPE_STRING, 'validate' => 'isCleanHtml', 'size' => 255, 'allow_null' => true],
            'anchor_mismatch' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'allow_null' => true],
            'status' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'http_code' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'allow_null' => true],
            'is_dofollow' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool', 'allow_null' => true],
            'rel_attribute' => ['type' => self::TYPE_STRING, 'size' => 128, 'allow_null' => true],
            'link_type' => ['type' => self::TYPE_STRING, 'size' => 64, 'allow_null' => true],
            'check_message' => ['type' => self::TYPE_STRING, 'size' => 255, 'allow_null' => true],
            'target_status' => ['type' => self::TYPE_INT, 'validate' => 'isInt'],
            'target_http_code' => ['type' => self::TYPE_INT, 'validate' => 'isInt', 'allow_null' => true],
            'target_last_checked' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'allow_null' => true],
            'notes' => ['type' => self::TYPE_HTML, 'validate' => 'isCleanHtml'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'last_checked' => ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat', 'allow_null' => true],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
        ],
    ];

    public function __construct($id = null, $id_lang = null, $id_shop = null)
    {
        parent::__construct($id, $id_lang, $id_shop);

        if (!$this->id) {
            $this->target_status = self::STATUS_NEVER_CHECKED;
            $this->status = self::STATUS_NEVER_CHECKED;
            $this->target_http_code = 0;
            $this->http_code = 0;
            $this->active = 1;
        }
    }

    /**
     * Auto-check a backlink right after save if enabled.
     */
    public function add($autodate = true, $null_values = false)
    {
        if (!empty($this->url_source)) {
            $this->url_source = BacklinkMonitorChecker::cleanUrl($this->url_source);
        }
        if (!empty($this->url_target)) {
            $this->url_target = BacklinkMonitorChecker::cleanUrl($this->url_target);
        }
        if (!empty($this->anchor_text)) {
            $this->anchor_text = trim((string) $this->anchor_text);
        }

        if ($this->target_status === null || $this->target_status === '') {
            $this->target_status = self::STATUS_NEVER_CHECKED;
        }
        if ($this->status === null || $this->status === '') {
            $this->status = self::STATUS_NEVER_CHECKED;
        }
        if ($this->target_http_code === null || $this->target_http_code === '') {
            $this->target_http_code = 0;
        }
        if ($this->http_code === null || $this->http_code === '') {
            $this->http_code = 0;
        }
        if ($this->active === null || $this->active === '') {
            $this->active = 1;
        }

        $this->target_status = (int) $this->target_status;
        $this->status = (int) $this->status;
        $this->target_http_code = (int) $this->target_http_code;
        $this->http_code = (int) $this->http_code;
        $this->active = (int) $this->active;

        $result = parent::add($autodate, $null_values);
        if ($result && (int) Configuration::get('BACKLINKMONITOR_AUTOCHECK') === 1) {
            BacklinkMonitorChecker::check((int) $this->id);
        }

        return $result;
    }

    public function update($null_values = false)
    {
        if (!empty($this->url_source)) {
            $this->url_source = BacklinkMonitorChecker::cleanUrl($this->url_source);
        }
        if (!empty($this->url_target)) {
            $this->url_target = BacklinkMonitorChecker::cleanUrl($this->url_target);
        }
        if (!empty($this->anchor_text)) {
            $this->anchor_text = trim((string) $this->anchor_text);
        }

        if ($this->target_status === null || $this->target_status === '') {
            $this->target_status = self::STATUS_NEVER_CHECKED;
        }
        if ($this->status === null || $this->status === '') {
            $this->status = self::STATUS_NEVER_CHECKED;
        }
        if ($this->target_http_code === null || $this->target_http_code === '') {
            $this->target_http_code = 0;
        }
        if ($this->http_code === null || $this->http_code === '') {
            $this->http_code = 0;
        }
        if ($this->active === null || $this->active === '') {
            $this->active = 1;
        }

        $this->target_status = (int) $this->target_status;
        $this->status = (int) $this->status;
        $this->target_http_code = (int) $this->target_http_code;
        $this->http_code = (int) $this->http_code;
        $this->active = (int) $this->active;

        $result = parent::update($null_values);
        if ($result && (int) Configuration::get('BACKLINKMONITOR_AUTOCHECK') === 1) {
            BacklinkMonitorChecker::check((int) $this->id);
        }

        return $result;
    }

    /**
     * Ensure database schema is complete, columns are modified to safe types/defaults,
     * and heal legacy statuses.
     */
    public static function ensureSchema()
    {
        $table = _DB_PREFIX_ . 'backlinkmonitor';
        $tableExists = Db::getInstance()->executeS("SHOW TABLES LIKE '" . $table . "'");
        if (empty($tableExists)) {
            return;
        }

        $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . $table . '`');
        $existing = [];
        if (!empty($columns)) {
            foreach ($columns as $col) {
                $existing[$col['Field']] = true;
            }
        }

        $queries = [];
        if (!isset($existing['redirect_url'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `redirect_url` VARCHAR(500) DEFAULT NULL AFTER `url_source`';
        }
        if (!isset($existing['target_redirect_url'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `target_redirect_url` VARCHAR(500) DEFAULT NULL AFTER `url_target`';
        }
        if (!isset($existing['rel_attribute'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `rel_attribute` VARCHAR(128) DEFAULT NULL AFTER `is_dofollow`';
        }
        if (!isset($existing['link_type'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `link_type` VARCHAR(64) DEFAULT NULL AFTER `rel_attribute`';
        }
        if (!isset($existing['check_message'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `check_message` VARCHAR(255) DEFAULT NULL AFTER `link_type`';
        }
        if (!isset($existing['target_status'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `target_status` TINYINT(1) NOT NULL DEFAULT 0 AFTER `check_message`';
        }
        if (!isset($existing['target_http_code'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `target_http_code` SMALLINT DEFAULT NULL AFTER `target_status`';
        }
        if (!isset($existing['target_last_checked'])) {
            $queries[] = 'ALTER TABLE `' . $table . '` ADD `target_last_checked` DATETIME DEFAULT NULL AFTER `target_http_code`';
        }

        foreach ($queries as $sql) {
            try {
                Db::getInstance()->execute($sql);
            } catch (Exception $e) {
                // Silently continue
            }
        }

        // Alter columns to ensure nullable or safe defaults so MySQL strict mode never causes constraint violations
        $alterModifications = [
            'ALTER TABLE `' . $table . '` MODIFY `target_http_code` SMALLINT NULL DEFAULT NULL',
            'ALTER TABLE `' . $table . '` MODIFY `http_code` SMALLINT NULL DEFAULT NULL',
            'ALTER TABLE `' . $table . '` MODIFY `target_status` TINYINT(1) NOT NULL DEFAULT 0',
            'ALTER TABLE `' . $table . '` MODIFY `status` TINYINT(1) NOT NULL DEFAULT 0',
            'ALTER TABLE `' . $table . '` MODIFY `target_last_checked` DATETIME NULL DEFAULT NULL',
            'ALTER TABLE `' . $table . '` MODIFY `last_checked` DATETIME NULL DEFAULT NULL',
            'ALTER TABLE `' . $table . '` MODIFY `active` TINYINT(1) NOT NULL DEFAULT 1',
        ];
        foreach ($alterModifications as $alterSql) {
            try {
                Db::getInstance()->execute($alterSql);
            } catch (Exception $e) {
                // Silently ignore if already matching
            }
        }

        // Auto-heal existing rows: synchronize target_status based on target_http_code
        try {
            Db::getInstance()->execute('UPDATE `' . $table . '` SET `target_status` = 1 WHERE `target_status` = 0 AND `target_http_code` >= 200 AND `target_http_code` < 400');
            Db::getInstance()->execute('UPDATE `' . $table . '` SET `target_status` = 3 WHERE `target_status` = 0 AND `target_http_code` >= 400');
        } catch (Exception $e) {
            // Silently continue
        }

        // Auto-heal existing rows: sanitize URLs in case they contain newlines, carriage returns, or spaces
        try {
            Db::getInstance()->execute("UPDATE `" . $table . "` SET `url_source` = TRIM(REPLACE(REPLACE(`url_source`, '\r', ''), '\n', '')), `url_target` = TRIM(REPLACE(REPLACE(`url_target`, '\r', ''), '\n', ''))");
        } catch (Exception $e) {
            // Silently continue
        }
    }

    public static function getStatusLabel($status)
    {
        switch ((int) $status) {
            case self::STATUS_WORKING:
                return 'Working (Dofollow)';
            case self::STATUS_NOFOLLOW:
                return 'NoFollow / Sponsored';
            case self::STATUS_BROKEN:
                return 'Broken / Missing';
            default:
                return 'Never Checked';
        }
    }

    public static function getTargetStatusLabel($status)
    {
        switch ((int) $status) {
            case self::STATUS_WORKING:
                return 'Working (200 OK)';
            case self::STATUS_BROKEN:
                return 'Broken / Error';
            default:
                return 'Never Checked';
        }
    }

    public static function getCounters()
    {
        $result = [
            'total' => 0,
            'working' => 0,
            'broken' => 0,
            'nofollow' => 0,
            'never_checked' => 0,
            'mismatches' => 0,
            'target_broken' => 0,
        ];

        $table = _DB_PREFIX_ . 'backlinkmonitor';

        // Check if table exists
        $tableExists = Db::getInstance()->executeS("SHOW TABLES LIKE '" . $table . "'");
        if (empty($tableExists)) {
            return $result;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT status, COUNT(*) as cnt FROM `' . $table . '` GROUP BY status'
        );

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $result['total'] += (int) $row['cnt'];
                switch ((int) $row['status']) {
                    case self::STATUS_WORKING:
                        $result['working'] = (int) $row['cnt'];
                        break;
                    case self::STATUS_NOFOLLOW:
                        $result['nofollow'] = (int) $row['cnt'];
                        break;
                    case self::STATUS_BROKEN:
                        $result['broken'] = (int) $row['cnt'];
                        break;
                    default:
                        $result['never_checked'] = (int) $row['cnt'];
                        break;
                }
            }
        }

        // Anchor mismatches
        $mismatchRow = Db::getInstance()->getRow(
            'SELECT COUNT(*) as cnt FROM `' . $table . '` WHERE anchor_mismatch = 1'
        );
        if ($mismatchRow) {
            $result['mismatches'] = (int) $mismatchRow['cnt'];
        }

        // Target broken (either explicitly marked 3 or target HTTP code indicates 4xx/5xx)
        $targetBrokenRow = Db::getInstance()->getRow(
            'SELECT COUNT(*) as cnt FROM `' . $table . '` WHERE target_status = ' . self::STATUS_BROKEN . ' OR (target_http_code IS NOT NULL AND target_http_code >= 400)'
        );
        if ($targetBrokenRow) {
            $result['target_broken'] = (int) $targetBrokenRow['cnt'];
        }

        return $result;
    }
}
