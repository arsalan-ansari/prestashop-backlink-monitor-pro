/**
 * Backlink Monitor - PrestaShop Admin JS
 * Batch AJAX Backlink Checker
 */
window.startBatchCheck = null;

$(document).ready(function () {
    var checkQueue = [];
    var totalLinks = 0;
    var completedLinks = 0;
    var counts = { working: 0, nofollow: 0, broken: 0 };
    var cancelRequested = false;
    var isChecking = false;

    function getAjaxUrl() {
        if (typeof bm_ajax_url !== 'undefined' && bm_ajax_url) {
            return bm_ajax_url;
        }
        var $card = $('.bm-dashboard-card').first();
        if ($card.length && $card.attr('data-ajax-url')) {
            return $card.attr('data-ajax-url');
        }
        var $btn = $('.bm-check-all-btn').first();
        if ($btn.length && $btn.attr('href')) {
            return $btn.attr('href').replace('&checkAllBacklinks=1', '');
        }
        return window.location.href;
    }

    function openModal() {
        $('#bm-progress-modal').css('display', 'flex').hide().fadeIn(200);
        $('#bm-progress-bar').css('width', '0%');
        $('#bm-progress-percent').text('0%');
        $('#bm-progress-text').text('Loading backlinks list...');
        $('#bm-progress-detail').text('');
        $('#bm-cnt-working').text('0');
        $('#bm-cnt-nofollow').text('0');
        $('#bm-cnt-broken').text('0');
        $('#bm-modal-heading').text('Checking Backlinks...');
        $('#bm-modal-cancel').show().text('Stop / Cancel');
        $('#bm-modal-done').hide();
        cancelRequested = false;
        isChecking = true;
    }

    function startBatchCheck(onlyActive) {
        if (isChecking) {
            return;
        }
        openModal();
        var ajaxUrl = getAjaxUrl();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'getBacklinkIds',
                onlyActive: onlyActive ? 1 : 0
            },
            dataType: 'json',
            success: function (res) {
                if (!res.success || !res.links || res.links.length === 0) {
                    $('#bm-progress-text').text('No backlinks found to check.');
                    $('#bm-modal-cancel').hide();
                    $('#bm-modal-done').show().text('Close');
                    isChecking = false;
                    return;
                }

                checkQueue = res.links;
                totalLinks = checkQueue.length;
                completedLinks = 0;
                counts = { working: 0, nofollow: 0, broken: 0 };

                processNextQueueItem();
            },
            error: function () {
                $('#bm-progress-text').text('Failed to initialize check. Please try again.');
                $('#bm-modal-cancel').hide();
                $('#bm-modal-done').show().text('Close');
                isChecking = false;
            }
        });
    }

    window.startBatchCheck = startBatchCheck;

    function processNextQueueItem() {
        if (cancelRequested) {
            $('#bm-modal-heading').html('<i class="icon-warning-sign"></i> Check Stopped');
            $('#bm-progress-text').text('Check stopped by user (' + completedLinks + ' of ' + totalLinks + ' checked).');
            $('#bm-modal-cancel').hide();
            $('#bm-modal-done').show().text('Done & Refresh Page');
            isChecking = false;
            return;
        }

        if (checkQueue.length === 0) {
            // All completed!
            $('#bm-progress-bar').css('width', '100%');
            $('#bm-progress-percent').text('100%');
            $('#bm-modal-heading').html('<i class="icon-ok-circle text-success"></i> Check Completed!');
            $('#bm-progress-text').text('All ' + totalLinks + ' backlink(s) checked successfully!');
            $('#bm-progress-detail').text('Refreshing page in 2 seconds...');
            $('#bm-modal-cancel').hide();
            $('#bm-modal-done').show();
            isChecking = false;

            setTimeout(function () {
                window.location.reload();
            }, 2000);
            return;
        }

        var item = checkQueue.shift();
        var currentNum = completedLinks + 1;
        var pct = Math.round((completedLinks / totalLinks) * 100);

        $('#bm-progress-bar').css('width', pct + '%');
        $('#bm-progress-percent').text(pct + '%');
        $('#bm-progress-text').text('Checking link ' + currentNum + ' of ' + totalLinks + '...');
        $('#bm-progress-detail').text(item.url_source);

        var ajaxUrl = getAjaxUrl();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                ajax: 1,
                action: 'checkSingleBacklink',
                id_backlink: item.id_backlink
            },
            dataType: 'json',
            success: function (data) {
                completedLinks++;
                if (data.success) {
                    if (data.status === 1) {
                        counts.working++;
                        $('#bm-cnt-working').text(counts.working);
                    } else if (data.status === 2) {
                        counts.nofollow++;
                        $('#bm-cnt-nofollow').text(counts.nofollow);
                    } else {
                        counts.broken++;
                        $('#bm-cnt-broken').text(counts.broken);
                    }
                } else {
                    counts.broken++;
                    $('#bm-cnt-broken').text(counts.broken);
                }

                var newPct = Math.round((completedLinks / totalLinks) * 100);
                $('#bm-progress-bar').css('width', newPct + '%');
                $('#bm-progress-percent').text(newPct + '%');

                processNextQueueItem();
            },
            error: function () {
                completedLinks++;
                counts.broken++;
                $('#bm-cnt-broken').text(counts.broken);

                var newPct = Math.round((completedLinks / totalLinks) * 100);
                $('#bm-progress-bar').css('width', newPct + '%');
                $('#bm-progress-percent').text(newPct + '%');

                processNextQueueItem();
            }
        });
    }

    // Intercept Check All buttons and run smooth AJAX batch
    $(document).on('click', 'a[href*="checkAllBacklinks=1"], .bm-check-all-btn', function (e) {
        e.preventDefault();
        startBatchCheck(false);
    });

    // Single check link loading spinner
    $(document).on('click', 'a[href*="checkSingle=1"]', function () {
        var $btn = $(this);
        $btn.addClass('disabled').css('pointer-events', 'none');
        $btn.find('i').removeClass('icon-refresh').addClass('icon-spin icon-refresh');
    });

    // Cancel button in modal
    $('#bm-modal-cancel').on('click', function () {
        cancelRequested = true;
        $(this).text('Stopping...');
    });
});
