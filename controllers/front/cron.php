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

class BacklinkMonitorCronModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $token = Tools::getValue('token');
        $storedToken = (string) Configuration::get('BACKLINKMONITOR_CRON_TOKEN');

        if (empty($token) || empty($storedToken) || !hash_equals($storedToken, (string) $token)) {
            header('HTTP/1.1 403 Forbidden');
            exit('Error 403: Invalid or missing cron security token.');
        }

        $startTime = microtime(true);

        $limit = (int) Tools::getValue('limit', 0);
        $offset = (int) Tools::getValue('offset', 0);

        // Check active backlinks
        $summary = BacklinkMonitorChecker::checkAll(true, $limit, $offset);

        $duration = round(microtime(true) - $startTime, 2);

        $output = sprintf(
            '[OK] %d active backlink(s) checked in %s seconds (%d working, %d nofollow/sponsored, %d broken/errors) at %s.',
            $summary['checked'],
            $duration,
            $summary['working'],
            $summary['nofollow'],
            $summary['broken'],
            date('Y-m-d H:i:s')
        );

        header('Content-Type: text/plain; charset=UTF-8');
        echo $output;
        exit;
    }
}