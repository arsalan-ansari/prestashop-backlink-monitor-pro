<div class="bm-dashboard-card" data-ajax-url="{$current_url}">
    <div class="bm-kpi-header">
        <div class="bm-kpi-header-title">
            <i class="icon-dashboard"></i> {l s='Backlink Profile Overview' mod='backlinkmonitor'}
        </div>
        <div class="bm-kpi-header-actions">
            <a href="{$current_url}&exportAuditReport=1" target="_blank" class="btn btn-primary btn-sm">
                <i class="icon-bar-chart"></i> {l s='Executive Audit Report' mod='backlinkmonitor'}
            </a>
            <a href="{$current_url}&exportCSV=1" class="btn btn-default btn-sm">
                <i class="icon-download"></i> {l s='Export CSV' mod='backlinkmonitor'}
            </a>
            <a href="#" class="btn btn-default btn-sm bm-check-all-btn">
                <i class="icon-refresh"></i> {l s='Check All Now' mod='backlinkmonitor'}
            </a>
        </div>
    </div>

    <div class="bm-kpi-row">
        <a href="{$current_url}" class="bm-kpi bm-kpi-total" title="{l s='View all backlinks' mod='backlinkmonitor'}">
            <div class="bm-kpi-icon"><i class="icon-link"></i></div>
            <div class="bm-kpi-info">
                <div class="bm-kpi-value">{$counters.total}</div>
                <div class="bm-kpi-label">{l s='Total Links' mod='backlinkmonitor'}</div>
            </div>
        </a>

        <a href="{$current_url}&backlinkmonitorFilter_a!status=1" class="bm-kpi bm-kpi-working" title="{l s='Filter working dofollow links' mod='backlinkmonitor'}">
            <div class="bm-kpi-icon"><i class="icon-check-circle"></i></div>
            <div class="bm-kpi-info">
                <div class="bm-kpi-value">{$counters.working}</div>
                <div class="bm-kpi-label">{l s='Working (Dofollow)' mod='backlinkmonitor'}</div>
            </div>
        </a>

        <a href="{$current_url}&backlinkmonitorFilter_a!status=2" class="bm-kpi bm-kpi-nofollow" title="{l s='Filter nofollow & sponsored links' mod='backlinkmonitor'}">
            <div class="bm-kpi-icon"><i class="icon-ban-circle"></i></div>
            <div class="bm-kpi-info">
                <div class="bm-kpi-value">{$counters.nofollow}</div>
                <div class="bm-kpi-label">{l s='NoFollow / Sponsored' mod='backlinkmonitor'}</div>
            </div>
        </a>

        <a href="{$current_url}&backlinkmonitorFilter_a!status=3" class="bm-kpi bm-kpi-broken" title="{l s='Filter broken or missing links' mod='backlinkmonitor'}">
            <div class="bm-kpi-icon"><i class="icon-remove-circle"></i></div>
            <div class="bm-kpi-info">
                <div class="bm-kpi-value">{$counters.broken}</div>
                <div class="bm-kpi-label">{l s='Broken / Missing' mod='backlinkmonitor'}</div>
            </div>
        </a>

        <a href="{$current_url}&backlinkmonitorFilter_a!anchor_mismatch=1" class="bm-kpi bm-kpi-mismatch" title="{l s='Filter anchor text mismatches' mod='backlinkmonitor'}">
            <div class="bm-kpi-icon"><i class="icon-warning-sign"></i></div>
            <div class="bm-kpi-info">
                <div class="bm-kpi-value">{$counters.mismatches}</div>
                <div class="bm-kpi-label">{l s='Anchor Mismatches' mod='backlinkmonitor'}</div>
            </div>
        </a>

        {if $counters.target_broken > 0}
        <a href="{$current_url}&backlinkmonitorFilter_a!target_status=3" class="bm-kpi bm-kpi-target-broken" title="{l s='Filter target URL errors' mod='backlinkmonitor'}">
            <div class="bm-kpi-icon"><i class="icon-exclamation-sign"></i></div>
            <div class="bm-kpi-info">
                <div class="bm-kpi-value">{$counters.target_broken}</div>
                <div class="bm-kpi-label">{l s='Target Page Errors' mod='backlinkmonitor'}</div>
            </div>
        </a>
        {/if}

        {if $counters.never_checked > 0}
        <a href="{$current_url}&backlinkmonitorFilter_a!status=0" class="bm-kpi bm-kpi-never" title="{l s='Filter never checked links' mod='backlinkmonitor'}">
            <div class="bm-kpi-icon"><i class="icon-question-sign"></i></div>
            <div class="bm-kpi-info">
                <div class="bm-kpi-value">{$counters.never_checked}</div>
                <div class="bm-kpi-label">{l s='Never Checked' mod='backlinkmonitor'}</div>
            </div>
        </a>
        {/if}
    </div>
</div>

<!-- AJAX Batch Progress Modal -->
<div id="bm-progress-modal" class="bm-modal-overlay" style="display:none;">
    <div class="bm-modal-dialog">
        <div class="bm-modal-content">
            <div class="bm-modal-header">
                <h4 class="bm-modal-title"><i class="icon-refresh icon-spin"></i> <span id="bm-modal-heading">{l s='Checking Backlinks...' mod='backlinkmonitor'}</span></h4>
            </div>
            <div class="bm-modal-body">
                <div class="bm-progress-track">
                    <div id="bm-progress-bar" class="bm-progress-fill" style="width: 0%;"></div>
                </div>
                <div class="bm-progress-info">
                    <span id="bm-progress-text">{l s='Initializing check...' mod='backlinkmonitor'}</span>
                    <span id="bm-progress-percent">0%</span>
                </div>
                <div class="bm-modal-counters">
                    <div class="bm-counter-pill bm-pill-working"><i class="icon-check"></i> {l s='Working:' mod='backlinkmonitor'} <strong id="bm-cnt-working">0</strong></div>
                    <div class="bm-counter-pill bm-pill-nofollow"><i class="icon-ban-circle"></i> {l s='NoFollow:' mod='backlinkmonitor'} <strong id="bm-cnt-nofollow">0</strong></div>
                    <div class="bm-counter-pill bm-pill-broken"><i class="icon-remove"></i> {l s='Broken:' mod='backlinkmonitor'} <strong id="bm-cnt-broken">0</strong></div>
                </div>
                <div id="bm-progress-detail" class="bm-progress-detail text-muted"></div>
            </div>
            <div class="bm-modal-footer">
                <button type="button" id="bm-modal-cancel" class="btn btn-default">{l s='Stop / Cancel' mod='backlinkmonitor'}</button>
                <button type="button" id="bm-modal-done" class="btn btn-primary" style="display:none;" onclick="window.location.reload();">{l s='Done & Refresh Page' mod='backlinkmonitor'}</button>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var bm_ajax_url = '{$current_url}';
    var bm_auto_start = {if !empty($auto_check_batch)}true{else}false{/if};

    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function ($) {
            if (bm_auto_start && typeof startBatchCheck === 'function') {
                startBatchCheck(false);
            }
        });
    }
</script>
