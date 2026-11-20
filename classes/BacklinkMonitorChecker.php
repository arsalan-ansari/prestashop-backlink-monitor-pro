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

class BacklinkMonitorChecker
{
    const DEFAULT_TIMEOUT = 15;
    const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    /**
     * Keep database connection alive and auto-reconnect if MySQL connection dropped during cURL waits.
     */
    public static function pingDatabase()
    {
        try {
            $db = Db::getInstance();
            if ($db) {
                $db->execute('SELECT 1');
            }
        } catch (Exception $e) {
            try {
                if (method_exists(Db::getInstance(), 'disconnect')) {
                    Db::getInstance()->disconnect();
                }
                if (method_exists(Db::getInstance(), 'connect')) {
                    Db::getInstance()->connect();
                }
            } catch (Exception $ex) {
                // Silently continue
            }
        }
    }

    /**
     * Check one backlink and persist:
     * 1. Target URL health & status
     * 2. Source URL response, HTML parsing, link verification, rel detection, anchor match
     *
     * @param int $id_backlink
     * @return BacklinkMonitorLink|false
     */
    public static function check($id_backlink)
    {
        self::pingDatabase();

        $id_backlink = (int) $id_backlink;
        $backlink = new BacklinkMonitorLink($id_backlink);

        if (!Validate::isLoadedObject($backlink)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'last_checked' => $now,
            'date_upd' => $now,
        ];

        $cleanTarget = self::cleanUrl($backlink->url_target);
        $cleanSource = self::cleanUrl($backlink->url_source);

        // Auto-heal database row if URL had hidden control chars / trailing newlines
        if ($cleanTarget !== $backlink->url_target) {
            $data['url_target'] = pSQL($cleanTarget);
            $backlink->url_target = $cleanTarget;
        }
        if ($cleanSource !== $backlink->url_source) {
            $data['url_source'] = pSQL($cleanSource);
            $backlink->url_source = $cleanSource;
        }

        /*
         * ---------------------------------------------------------
         * 1. CHECK TARGET URL (Merchant's landing page)
         * ---------------------------------------------------------
         */
        $targetResult = self::fetch($cleanTarget);
        $targetHttpCode = (int) $targetResult['http_code'];
        $data['target_http_code'] = $targetHttpCode;
        $data['target_last_checked'] = $now;

        if ($targetResult['effective_url'] !== '' && $targetResult['effective_url'] !== $cleanTarget) {
            $data['target_redirect_url'] = pSQL($targetResult['effective_url']);
        } else {
            $data['target_redirect_url'] = null;
        }

        if ($targetHttpCode >= 200 && $targetHttpCode < 400) {
            $data['target_status'] = BacklinkMonitorLink::STATUS_WORKING;
            $targetHealthy = true;
        } else {
            $data['target_status'] = BacklinkMonitorLink::STATUS_BROKEN;
            $targetHealthy = false;
        }

        /*
         * ---------------------------------------------------------
         * 2. CHECK SOURCE URL (Page hosting the backlink)
         * ---------------------------------------------------------
         */
        $sourceResult = self::fetch($cleanSource);
        $sourceHttpCode = (int) $sourceResult['http_code'];
        $data['http_code'] = $sourceHttpCode;

        if ($sourceResult['effective_url'] !== '' && $sourceResult['effective_url'] !== $cleanSource) {
            $data['redirect_url'] = pSQL($sourceResult['effective_url']);
        } else {
            $data['redirect_url'] = null;
        }

        if (!$sourceResult['success']) {
            // Network or cURL error (e.g. timeout, DNS failed, SSL failed)
            $data['status'] = BacklinkMonitorLink::STATUS_BROKEN;
            $data['is_dofollow'] = null;
            $data['rel_attribute'] = null;
            $data['link_type'] = 'not_found';
            $data['found_anchor_text'] = null;
            $data['anchor_mismatch'] = null;
            $data['check_message'] = pSQL('Connection failed: ' . ($sourceResult['error_msg'] ?: 'Network Error'));
        } elseif ($sourceHttpCode >= 400 || $sourceHttpCode === 0) {
            // Source page returned HTTP error (404, 403, 500, etc.)
            $data['status'] = BacklinkMonitorLink::STATUS_BROKEN;
            $data['is_dofollow'] = null;
            $data['rel_attribute'] = null;
            $data['link_type'] = 'not_found';
            $data['found_anchor_text'] = null;
            $data['anchor_mismatch'] = null;
            $desc = self::getHttpCodeMessage($sourceHttpCode);
            $data['check_message'] = pSQL('Source page returned HTTP ' . $sourceHttpCode . ($desc ? ' (' . $desc . ')' : ''));
        } else {
            // Page responded successfully (HTTP 2xx or 3xx) - Parse HTML
            $effectiveSourceUrl = !empty($sourceResult['effective_url']) ? $sourceResult['effective_url'] : $cleanSource;
            $linkInfo = self::findLink(
                $sourceResult['body'],
                $cleanTarget,
                $effectiveSourceUrl,
                $backlink->anchor_text,
                $sourceResult['headers']
            );

            if (!$linkInfo['found']) {
                $data['status'] = BacklinkMonitorLink::STATUS_BROKEN;
                $data['is_dofollow'] = null;
                $data['rel_attribute'] = null;
                $data['link_type'] = 'not_found';
                $data['found_anchor_text'] = null;
                $data['anchor_mismatch'] = null;
                $data['check_message'] = pSQL(!empty($linkInfo['message']) ? $linkInfo['message'] : 'Target link not found in page HTML');
            } else {
                $data['is_dofollow'] = $linkInfo['dofollow'] ? 1 : 0;
                $data['rel_attribute'] = pSQL($linkInfo['rel']);
                $data['link_type'] = pSQL($linkInfo['link_type']);
                $data['found_anchor_text'] = pSQL($linkInfo['anchor_text']);
                $data['anchor_mismatch'] = $linkInfo['anchor_mismatch'] !== null ? ($linkInfo['anchor_mismatch'] ? 1 : 0) : null;

                $data['status'] = $linkInfo['dofollow']
                    ? BacklinkMonitorLink::STATUS_WORKING
                    : BacklinkMonitorLink::STATUS_NOFOLLOW;

                $data['check_message'] = pSQL($linkInfo['message']);
            }
        }

        // Append warning if target URL is broken
        if (!$targetHealthy) {
            $targetDesc = 'Target URL issue: HTTP ' . $targetHttpCode;
            if (!empty($data['check_message'])) {
                $data['check_message'] .= ' | ' . $targetDesc;
            } else {
                $data['check_message'] = $targetDesc;
            }
        }

        // Keep database active before persisting
        self::pingDatabase();
        self::persist($id_backlink, $data);
        self::pingDatabase();

        return new BacklinkMonitorLink($id_backlink);
    }

    /**
     * Check multiple or all backlinks with DB keepalive.
     *
     * @param bool $onlyActive
     * @param int $limit
     * @param int $offset
     * @return array Summary of checks
     */
    public static function checkAll($onlyActive = true, $limit = 0, $offset = 0)
    {
        @set_time_limit(0);
        @ini_set('max_execution_time', 0);

        self::pingDatabase();

        $sql = 'SELECT id_backlink FROM `' . _DB_PREFIX_ . 'backlinkmonitor`';

        if ($onlyActive) {
            $sql .= ' WHERE active = 1';
        }

        $sql .= ' ORDER BY id_backlink ASC';

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $offset . ', ' . (int) $limit;
        }

        $rows = Db::getInstance()->executeS($sql);

        $summary = [
            'total' => 0,
            'checked' => 0,
            'working' => 0,
            'nofollow' => 0,
            'broken' => 0,
        ];

        if (!empty($rows)) {
            $summary['total'] = count($rows);
            foreach ($rows as $row) {
                self::pingDatabase();
                $res = self::check((int) $row['id_backlink']);
                self::pingDatabase();

                if ($res && Validate::isLoadedObject($res)) {
                    $summary['checked']++;
                    if ((int) $res->status === BacklinkMonitorLink::STATUS_WORKING) {
                        $summary['working']++;
                    } elseif ((int) $res->status === BacklinkMonitorLink::STATUS_NOFOLLOW) {
                        $summary['nofollow']++;
                    } else {
                        $summary['broken']++;
                    }
                }
            }
        }

        self::pingDatabase();

        return $summary;
    }

    /**
     * Persist results directly into DB without triggering ObjectModel recursion.
     *
     * @param int $id_backlink
     * @param array $data
     * @return bool
     */
    private static function persist($id_backlink, array $data)
    {
        $result = Db::getInstance()->update(
            'backlinkmonitor',
            $data,
            'id_backlink = ' . (int) $id_backlink,
            0,
            true
        );

        if (!$result) {
            PrestaShopLogger::addLog(
                'BacklinkMonitor: Failed to update backlink #' . (int) $id_backlink . '. DB error: ' . Db::getInstance()->getMsgError(),
                3
            );
        }

        return $result;
    }

    /**
     * Clean, sanitize, and normalize URL string to prevent libcurl CURLE_URL_MALFORMAT (3)
     * "URL rejected: Malformed input to a URL function" caused by hidden control chars,
     * newlines, zero-width spaces, or unencoded path characters.
     *
     * @param string $url
     * @return string
     */
    public static function cleanUrl($url)
    {
        if (!is_string($url)) {
            return '';
        }

        // 1. Remove UTF-8 Byte Order Mark (BOM) & zero-width / hidden characters
        $url = str_replace(
            ["\xEF\xBB\xBF", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xFE\xFF", "\xFF\xFE"],
            '',
            $url
        );

        // 2. Replace non-breaking spaces (\u00A0) with standard space
        $url = str_replace(["\xC2\xA0", "\xA0"], ' ', $url);

        // 3. Remove all non-printable ASCII control characters (\x00 to \x1F and \x7F) including \r, \n, \t
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url);

        // 4. Decode HTML entities like &amp; into &
        $url = str_replace('&amp;', '&', $url);

        // 5. Trim quotes and whitespace
        $url = trim($url, " \t\n\r\0\x0B\"'");

        if ($url === '') {
            return '';
        }

        // 6. Fix malformed single-slash schemes (e.g. http:/example.com)
        $url = preg_replace('~^http:/(?!/)~i', 'http://', $url);
        $url = preg_replace('~^https:/(?!/)~i', 'https://', $url);

        // 7. Ensure valid scheme (default to https:// if missing)
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        // 8. Clean spaces and parse URL
        $parts = @parse_url($url);
        if ($parts !== false && isset($parts['host'])) {
            $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) . '://' : 'https://';
            $user = isset($parts['user']) ? $parts['user'] : '';
            $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
            $auth = ($user || $pass) ? "$user$pass@" : '';
            $host = strtolower($parts['host']);
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            $path = isset($parts['path']) ? $parts['path'] : '';
            $query = isset($parts['query']) ? '?' . $parts['query'] : '';
            $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

            // IDN domain conversion if international domain
            if (function_exists('idn_to_ascii') && defined('INTL_IDNA_VARIANT_UTS46')) {
                $asciiHost = @idn_to_ascii($host, 0, INTL_IDNA_VARIANT_UTS46);
                if ($asciiHost) {
                    $host = $asciiHost;
                }
            }

            // Clean path: encode raw spaces to %20
            $path = str_replace(' ', '%20', $path);

            $url = $scheme . $auth . $host . $port . $path . $query . $fragment;
        }

        return $url;
    }

    /**
     * Fetch URL using cURL with realistic browser headers, redirect tracking, and header parsing.
     * Includes automatic protocol fallback (http <-> https) if initial connection fails.
     *
     * @param string $url
     * @return array
     */
    public static function fetch($url)
    {
        $url = self::cleanUrl($url);
        if (empty($url)) {
            return [
                'success' => false,
                'http_code' => 0,
                'effective_url' => '',
                'error_no' => 3,
                'error_msg' => 'Empty or invalid URL provided',
                'headers' => [],
                'body' => '',
            ];
        }

        $timeout = (int) Configuration::get('BACKLINKMONITOR_TIMEOUT');
        if ($timeout < 3 || $timeout > 60) {
            $timeout = self::DEFAULT_TIMEOUT;
        }

        $userAgent = (string) Configuration::get('BACKLINKMONITOR_USER_AGENT');
        if (empty($userAgent)) {
            $userAgent = self::DEFAULT_USER_AGENT;
        }

        $followVal = Configuration::get('BACKLINKMONITOR_FOLLOW_REDIRECTS');
        $followRedirects = ($followVal === false) ? true : (bool) $followVal;

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
        ];

        $res = self::executeCurl($url, $followRedirects, $timeout, $userAgent, $headers);

        // If request failed on http://, attempt automatic fallback to https://
        if (!$res['success'] && stripos($url, 'http://') === 0) {
            $httpsUrl = 'https://' . substr($url, 7);
            $retry = self::executeCurl($httpsUrl, $followRedirects, $timeout, $userAgent, $headers);
            if ($retry['success'] || ($retry['http_code'] > 0 && $res['http_code'] === 0)) {
                return $retry;
            }
        } elseif (!$res['success'] && stripos($url, 'https://') === 0 && in_array($res['error_no'], [35, 58, 60])) {
            // SSL handshake or certificate failure: attempt http fallback
            $httpUrl = 'http://' . substr($url, 8);
            $retry = self::executeCurl($httpUrl, $followRedirects, $timeout, $userAgent, $headers);
            if ($retry['success'] || ($retry['http_code'] > 0 && $res['http_code'] === 0)) {
                return $retry;
            }
        }

        return $res;
    }

    private static function executeCurl($url, $followRedirects, $timeout, $userAgent, array $headers)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => $followRedirects,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => '', // Enables gzip, deflate, and br decompression
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        $headerText = '';
        $body = '';

        if ($curlErrno === 0 && $response !== false) {
            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
        }

        // Parse response headers into array
        $parsedHeaders = [];
        if (!empty($headerText)) {
            $headerLines = explode("\r\n", $headerText);
            foreach ($headerLines as $line) {
                if (strpos($line, ':') !== false) {
                    list($key, $val) = explode(':', $line, 2);
                    $parsedHeaders[strtolower(trim($key))] = trim($val);
                }
            }
        }

        return [
            'success' => ($curlErrno === 0),
            'http_code' => $httpCode,
            'effective_url' => $effectiveUrl,
            'error_no' => $curlErrno,
            'error_msg' => $curlError,
            'headers' => $parsedHeaders,
            'body' => $body ?: '',
        ];
    }

    /**
     * Advanced link finder and parser.
     * Supports:
     * - Accurate URL normalization (trailing slash, www/non-www, query parameters)
     * - Relative and protocol-relative URL resolution
     * - Base href support
     * - Page-level robots meta tags & X-Robots-Tag HTTP headers
     * - Rel analysis: dofollow, nofollow, sponsored, ugc
     * - Anchor text extraction including image alt/title fallback
     * - Smart matching for multiple links on page (prioritizing expected anchor & dofollow)
     *
     * @param string $html
     * @param string $targetUrl
     * @param string $sourceUrl
     * @param string $expectedAnchor
     * @param array $responseHeaders
     * @return array
     */
    public static function findLink($html, $targetUrl, $sourceUrl = '', $expectedAnchor = '', array $responseHeaders = [])
    {
        $result = [
            'found' => false,
            'dofollow' => false,
            'rel' => '',
            'anchor_text' => '',
            'anchor_mismatch' => null,
            'link_type' => 'not_found',
            'matched_url' => '',
            'message' => 'Target link not found on source page',
        ];

        if (empty($html)) {
            $result['message'] = 'Source page returned empty HTML body';
            return $result;
        }

        $strictVal = Configuration::get('BACKLINKMONITOR_STRICT_ANCHOR');
        $strictAnchor = ($strictVal === false) ? false : (bool) $strictVal;

        // Normalize target URL
        $targetNorm = self::normalizeUrlForComparison($targetUrl);
        $targetNormNoQuery = self::normalizeUrlForComparison(preg_replace('/\?.*$/', '', $targetUrl));

        $targetHost = strtolower((string) parse_url('https://' . ltrim($targetNorm, '/'), PHP_URL_HOST));
        $targetHost = preg_replace('/^www\./', '', $targetHost);
        $targetPath = trim((string) parse_url('https://' . ltrim($targetNorm, '/'), PHP_URL_PATH), '/');
        $isTargetRootDomain = ($targetPath === '');

        // Check X-Robots-Tag HTTP header for nofollow
        $headerNofollow = false;
        if (!empty($responseHeaders['x-robots-tag'])) {
            $xRobots = strtolower($responseHeaders['x-robots-tag']);
            if (strpos($xRobots, 'nofollow') !== false || strpos($xRobots, 'none') !== false) {
                $headerNofollow = true;
            }
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        // Convert multibyte encoding to HTML numeric entities to safely preserve all UTF-8 characters
        if (function_exists('mb_encode_numericentity')) {
            $safeHtml = mb_encode_numericentity($html, [0x80, 0x10ffff, 0, 0x1fffff], 'UTF-8');
        } else {
            $safeHtml = '<?xml encoding="UTF-8">' . $html;
        }

        @$dom->loadHTML($safeHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        // Check for <base href="...">
        $baseUrl = $sourceUrl;
        $baseTags = $dom->getElementsByTagName('base');
        if ($baseTags->length > 0) {
            $baseHref = $baseTags->item(0)->getAttribute('href');
            if (!empty($baseHref)) {
                $baseUrl = $baseHref;
            }
        }

        // Check for page-level robots meta tags
        $pageRobotsNofollow = $headerNofollow;
        $metaTags = $dom->getElementsByTagName('meta');
        foreach ($metaTags as $meta) {
            $name = strtolower($meta->getAttribute('name'));
            if (in_array($name, ['robots', 'googlebot', 'bingbot', 'msnbot'])) {
                $content = strtolower($meta->getAttribute('content'));
                if (strpos($content, 'nofollow') !== false || strpos($content, 'none') !== false) {
                    $pageRobotsNofollow = true;
                    break;
                }
            }
        }

        $links = $dom->getElementsByTagName('a');
        $candidates = [];
        $sameDomainCandidates = [];

        foreach ($links as $link) {
            $href = trim($link->getAttribute('href'));
            if (empty($href) || preg_match('#^(javascript:|mailto:|tel:|\#|data:)#i', $href)) {
                continue;
            }

            $resolvedUrl = self::resolveUrl($href, $baseUrl);
            if (empty($resolvedUrl)) {
                continue;
            }

            $resolvedNorm = self::normalizeUrlForComparison($resolvedUrl);
            $resolvedNormNoQuery = self::normalizeUrlForComparison(preg_replace('/\?.*$/', '', $resolvedUrl));

            $resolvedHost = strtolower((string) parse_url('https://' . ltrim($resolvedNorm, '/'), PHP_URL_HOST));
            $resolvedHost = preg_replace('/^www\./', '', $resolvedHost);
            $resolvedPath = trim((string) parse_url('https://' . ltrim($resolvedNorm, '/'), PHP_URL_PATH), '/');
            $isSameHost = (!empty($targetHost) && $resolvedHost === $targetHost);

            $isMatch = false;
            $matchScore = 0;
            $matchType = 'exact';

            if ($resolvedNorm === $targetNorm) {
                // Exact normalized URL match (host, path, non-tracking query parameters)
                $isMatch = true;
                $matchScore = 100;
                $matchType = 'exact';
            } elseif ($resolvedNormNoQuery === $targetNormNoQuery) {
                // Base path matches, tracking or extra query params present
                $isMatch = true;
                $matchScore = 80;
                $matchType = 'base_path';
            } elseif ($isSameHost && $isTargetRootDomain) {
                // Target URL is merchant's root domain => any link to their domain matches!
                $isMatch = true;
                $matchScore = 75;
                $matchType = 'domain_root';
            }

            // Extract anchor text and link type
            $anchorText = trim(preg_replace('/\s+/', ' ', $link->textContent));
            $linkType = 'text';

            if ($anchorText === '') {
                // Inspect for <img> inside <a>
                $imgs = $link->getElementsByTagName('img');
                if ($imgs->length > 0) {
                    $alt = trim($imgs->item(0)->getAttribute('alt'));
                    $imgTitle = trim($imgs->item(0)->getAttribute('title'));
                    if (!empty($alt)) {
                        $anchorText = '[Image: ' . $alt . ']';
                        $linkType = 'image';
                    } elseif (!empty($imgTitle)) {
                        $anchorText = '[Image: ' . $imgTitle . ']';
                        $linkType = 'image';
                    } else {
                        $anchorText = '[Image: no alt text]';
                        $linkType = 'image';
                    }
                } else {
                    $linkTitle = trim($link->getAttribute('title'));
                    $aria = trim($link->getAttribute('aria-label'));
                    if (!empty($aria)) {
                        $anchorText = $aria;
                    } elseif (!empty($linkTitle)) {
                        $anchorText = $linkTitle;
                    }
                }
            }

            // If not matched by exact path or root domain, check if anchor matches on same domain!
            if (!$isMatch && $isSameHost) {
                if (!empty($expectedAnchor)) {
                    $expLower = trim(mb_strtolower($expectedAnchor, 'UTF-8'));
                    $foundLower = trim(mb_strtolower(preg_replace('/^\[Image:\s*(.*?)\s*\]$/i', '$1', $anchorText), 'UTF-8'));
                    if ($expLower === $foundLower) {
                        $isMatch = true;
                        $matchScore = 70;
                        $matchType = 'domain_anchor_match';
                    }
                }

                if (!$isMatch) {
                    $sameDomainCandidates[] = [
                        'link' => $link,
                        'resolvedUrl' => $resolvedUrl,
                        'resolvedPath' => $resolvedPath,
                        'anchorText' => $anchorText,
                        'linkType' => $linkType,
                    ];
                    continue;
                }
            }

            if (!$isMatch) {
                continue;
            }

            $candidates[] = self::buildCandidate(
                $link,
                $resolvedUrl,
                $resolvedPath,
                $anchorText,
                $linkType,
                $expectedAnchor,
                $strictAnchor,
                $pageRobotsNofollow,
                $matchScore,
                $matchType
            );
        }

        if (empty($candidates)) {
            if (!empty($sameDomainCandidates)) {
                // If only 1 link to this domain exists on the page, match it
                if (count($sameDomainCandidates) === 1) {
                    $sd = $sameDomainCandidates[0];
                    $candidates[] = self::buildCandidate(
                        $sd['link'],
                        $sd['resolvedUrl'],
                        $sd['resolvedPath'],
                        $sd['anchorText'],
                        $sd['linkType'],
                        $expectedAnchor,
                        $strictAnchor,
                        $pageRobotsNofollow,
                        65,
                        'single_domain_link'
                    );
                } else {
                    $samples = [];
                    foreach (array_slice($sameDomainCandidates, 0, 2) as $sdc) {
                        $samples[] = $sdc['resolvedUrl'] . (!empty($sdc['anchorText']) ? ' ("' . $sdc['anchorText'] . '")' : '');
                    }
                    $result['message'] = 'Target link not found. Found domain links: ' . implode(', ', $samples);
                    return $result;
                }
            } else {
                return $result;
            }
        }

        // Sort candidates by score descending so the best/editorial link is chosen
        usort($candidates, function ($a, $b) {
            return $b['score'] - $a['score'];
        });

        $best = $candidates[0];
        $count = count($candidates);

        if ($best['dofollow']) {
            $msg = 'Link verified (Dofollow)';
        } else {
            $msg = 'Link found (' . $best['rel'] . ')';
        }

        if (isset($best['type']) && $best['type'] === 'domain_root') {
            $msg .= ' [Page: /' . $best['path'] . ']';
        } elseif (isset($best['type']) && $best['type'] === 'single_domain_link') {
            $msg .= ' [Domain link: /' . $best['path'] . ']';
        } elseif (isset($best['type']) && $best['type'] === 'domain_anchor_match') {
            $msg .= ' [Matched on domain by anchor]';
        }

        if ($count > 1 && (!isset($best['type']) || $best['type'] === 'exact')) {
            $msg .= ' [' . $count . ' links found on page]';
        }

        if ($best['anchor_mismatch'] === true) {
            $msg .= ' - Anchor mismatch';
        }

        $best['message'] = $msg;

        return $best;
    }

    private static function buildCandidate($link, $resolvedUrl, $resolvedPath, $anchorText, $linkType, $expectedAnchor, $strictAnchor, $pageRobotsNofollow, $score, $matchType)
    {
        // Analyze rel attribute
        $relRaw = trim($link->getAttribute('rel'));
        $relParts = preg_split('/[\s,]+/', strtolower($relRaw), -1, PREG_SPLIT_NO_EMPTY);

        $isLinkNofollow = in_array('nofollow', $relParts);
        $isSponsored = in_array('sponsored', $relParts);
        $isUgc = in_array('ugc', $relParts);

        $isOverallNofollow = ($isLinkNofollow || $pageRobotsNofollow || $isSponsored || $isUgc);
        $dofollow = !$isOverallNofollow;

        // Build clean rel attribute summary
        $relSummary = [];
        if ($isLinkNofollow) {
            $relSummary[] = 'nofollow';
        } elseif ($pageRobotsNofollow) {
            $relSummary[] = 'nofollow (page meta)';
        }

        if ($isSponsored) {
            $relSummary[] = 'sponsored';
        }
        if ($isUgc) {
            $relSummary[] = 'ugc';
        }
        if (in_array('noopener', $relParts)) {
            $relSummary[] = 'noopener';
        }
        if (in_array('noreferrer', $relParts)) {
            $relSummary[] = 'noreferrer';
        }

        $relString = !empty($relSummary) ? implode(' ', $relSummary) : 'dofollow';

        // Compare expected anchor text
        $anchorMismatch = null;
        if (!empty($expectedAnchor)) {
            if ($strictAnchor) {
                $expectedClean = trim($expectedAnchor);
                $foundClean = trim(preg_replace('/^\[Image:\s*(.*?)\s*\]$/i', '$1', $anchorText));
                $anchorMismatch = ($expectedClean !== $foundClean);
            } else {
                $expectedClean = trim(mb_strtolower($expectedAnchor, 'UTF-8'));
                $foundClean = trim(mb_strtolower(preg_replace('/^\[Image:\s*(.*?)\s*\]$/i', '$1', $anchorText), 'UTF-8'));
                $anchorMismatch = ($expectedClean !== $foundClean);
            }

            if (!$anchorMismatch) {
                // Match bonus score
                $score += 50;
            }
        }

        if ($dofollow) {
            $score += 20;
        }

        return [
            'score' => $score,
            'type' => $matchType,
            'path' => $resolvedPath,
            'found' => true,
            'dofollow' => $dofollow,
            'rel' => $relString,
            'anchor_text' => $anchorText,
            'anchor_mismatch' => $anchorMismatch,
            'link_type' => $linkType,
            'matched_url' => $resolvedUrl,
        ];
    }

    /**
     * Normalize URL for clean comparison:
     * - Lowercase domain
     * - Strip leading www.
     * - Normalize path (strip redundant slashes, remove trailing slash for non-root)
     * - Filter out dynamic tracking query parameters (utm_*, fbclid, gclid, etc.)
     *
     * @param string $url
     * @return string
     */
    public static function normalizeUrlForComparison($url)
    {
        if (empty($url)) {
            return '';
        }

        $url = trim($url);
        $parts = parse_url($url);

        if (!$parts || empty($parts['host'])) {
            return $url;
        }

        $host = strtolower($parts['host']);
        $host = preg_replace('/^www\./', '', $host);

        // Normalize path
        $path = !empty($parts['path']) ? $parts['path'] : '/';
        $path = preg_replace('#/+#', '/', $path);
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        // Clean tracking parameters from query string
        $cleanQuery = '';
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            $ignoreParams = [
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'fbclid', 'gclid', 'msclkid', 'yclid', 'ref', 'source'
            ];
            foreach ($ignoreParams as $p) {
                unset($queryParams[$p]);
            }
            if (!empty($queryParams)) {
                ksort($queryParams);
                $cleanQuery = '?' . http_build_query($queryParams);
            }
        }

        return $host . $path . $cleanQuery;
    }

    /**
     * Resolve relative or protocol-relative URL to absolute URL.
     *
     * @param string $relativeHref
     * @param string $baseUrl
     * @return string
     */
    public static function resolveUrl($relativeHref, $baseUrl)
    {
        $relativeHref = trim($relativeHref);
        if (empty($relativeHref)) {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $relativeHref)) {
            if (substr($relativeHref, 0, 2) === '//') {
                $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME);
                return ($baseScheme ? $baseScheme : 'https') . ':' . $relativeHref;
            }
            return $relativeHref;
        }

        if (preg_match('#^(mailto:|tel:|javascript:|\#|data:)#i', $relativeHref)) {
            return '';
        }

        $baseParts = parse_url($baseUrl);
        if (!$baseParts || empty($baseParts['host'])) {
            return $relativeHref;
        }

        $scheme = !empty($baseParts['scheme']) ? $baseParts['scheme'] : 'https';
        $host = $baseParts['host'];
        $port = !empty($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $baseOrigin = $scheme . '://' . $host . $port;

        if ($relativeHref[0] === '/') {
            return $baseOrigin . $relativeHref;
        }

        $basePath = !empty($baseParts['path']) ? $baseParts['path'] : '/';
        $baseDir = preg_replace('#/[^/]*$#', '/', $basePath);
        if (empty($baseDir)) {
            $baseDir = '/';
        }

        return $baseOrigin . $baseDir . $relativeHref;
    }

    /**
     * Map HTTP status codes to standard human-readable descriptions.
     *
     * @param int $code
     * @return string
     */
    public static function getHttpCodeMessage($code)
    {
        $map = [
            200 => 'OK',
            301 => 'Moved Permanently',
            302 => 'Found / Moved Temporarily',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden (Access Denied / WAF)',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            408 => 'Request Timeout',
            410 => 'Gone',
            429 => 'Too Many Requests (Rate Limited)',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];

        return isset($map[$code]) ? $map[$code] : '';
    }
}