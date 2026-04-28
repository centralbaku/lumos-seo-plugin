(function ($) {
    'use strict';

    var auditState = {
        domain: 'alliancemultimodal.com',
        auditName: 'Multimodal',
        pagesCrawled: 32,
        urlsFound: 143,
        errors: 1,
        warnings: 35,
        notices: 113,
        running: false,
        seconds: 16
    };

    function shellMarkup() {
        return '' +
            '<div class="lumos-audit-shell">' +
                '<aside class="lumos-audit-sidebar">' +
                    '<div class="lumos-audit-brand">Audit</div>' +
                    '<div class="lumos-audit-section">Website Audit</div>' +
                    '<nav class="lumos-audit-nav">' +
                        '<a href="#" data-page="overview" class="active">Overview</a>' +
                        '<a href="#" data-page="create">Create New Audit</a>' +
                        '<a href="#" data-page="live">Crawled Progress</a>' +
                        '<a href="#" data-page="pages">Crawled Pages</a>' +
                        '<a href="#" data-page="resources">Found Resources</a>' +
                        '<a href="#" data-page="issues">Issue Report</a>' +
                    '</nav>' +
                '</aside>' +
                '<section class="lumos-audit-main">' +
                    '<div class="lumos-audit-topbar">' +
                        '<div class="lumos-audit-title" id="lumos-page-title">Overview</div>' +
                        '<div class="lumos-audit-status" id="lumos-run-status">Ready</div>' +
                    '</div>' +
                    '<div class="lumos-audit-content">' +
                        pageOverview() +
                        pageCreate() +
                        pageLive() +
                        pageCrawled() +
                        pageResources() +
                        pageIssues() +
                    '</div>' +
                '</section>' +
            '</div>';
    }

    function pageOverview() {
        return '' +
        '<div class="lumos-page active" id="lumos-page-overview">' +
            '<div class="lumos-grid-4">' +
                statCard('Site Score', '80') +
                statCard('Crawled', String(auditState.pagesCrawled)) +
                statCard('Warnings', String(auditState.warnings)) +
                statCard('Errors', String(auditState.errors)) +
            '</div>' +
            '<div class="lumos-block"><strong>Top Issues</strong><ul><li>Description missing</li><li>X-Robots-Tag missing</li><li>Internal links to 3XX pages</li></ul></div>' +
        '</div>';
    }

    function pageCreate() {
        return '' +
        '<div class="lumos-page" id="lumos-page-create">' +
            '<div class="lumos-block">' +
                '<h3>Create New Audit</h3>' +
                '<p><label>Domain</label><br><input type="text" id="lumos-domain" value="' + esc(auditState.domain) + '" style="width:360px"></p>' +
                '<p><label>Audit name</label><br><input type="text" id="lumos-audit-name" value="' + esc(auditState.auditName) + '" style="width:360px"></p>' +
                '<div class="lumos-actions"><button class="button button-primary" id="lumos-run-audit">Run Audit</button></div>' +
            '</div>' +
        '</div>';
    }

    function pageLive() {
        return '' +
        '<div class="lumos-page" id="lumos-page-live">' +
            '<div class="lumos-grid-4">' +
                statCard('Pages Crawled', '<span id="live-pages">' + auditState.pagesCrawled + '</span>') +
                statCard('URLs', '<span id="live-urls">' + auditState.urlsFound + '</span>') +
                statCard('Warnings', '<span id="live-warnings">' + auditState.warnings + '</span>') +
                statCard('Errors', '<span id="live-errors">' + auditState.errors + '</span>') +
            '</div>' +
            '<div class="lumos-block"><strong>Elapsed:</strong> <span id="live-time">00:00:16</span></div>' +
        '</div>';
    }

    function pageCrawled() {
        return '' +
        '<div class="lumos-page" id="lumos-page-pages">' +
            '<div class="lumos-pills"><span class="lumos-pill active">All</span><span class="lumos-pill">Errors</span><span class="lumos-pill">Warnings</span><span class="lumos-pill">Notices</span></div>' +
            '<table class="lumos-table"><thead><tr><th>Page URL</th><th>Issue</th><th>Status</th><th>Action</th></tr></thead><tbody>' +
                row('/en/transport-logistics-services/', 'Description missing', 'Warning') +
                row('/en/about/', 'X-Robots-Tag missing', 'Notice') +
                row('/en/news/', 'Internal links to 3XX', 'Warning') +
            '</tbody></table>' +
        '</div>';
    }

    function pageResources() {
        return '' +
        '<div class="lumos-page" id="lumos-page-resources">' +
            '<div class="lumos-pills"><span class="lumos-pill active">All</span><span class="lumos-pill">Image</span><span class="lumos-pill">CSS</span><span class="lumos-pill">JavaScript</span></div>' +
            '<table class="lumos-table"><thead><tr><th>URL</th><th>Type</th><th>Status</th></tr></thead><tbody>' +
                '<tr><td>/file/2025/09/whatsapp_icon.svg</td><td>IMG</td><td>200</td></tr>' +
                '<tr><td>/file/2024/02/SEO-image2-1024x582.jpg</td><td>IMG</td><td>200</td></tr>' +
                '<tr><td>/maps/EWMap.jpg</td><td>IMG</td><td>200</td></tr>' +
            '</tbody></table>' +
        '</div>';
    }

    function pageIssues() {
        return '' +
        '<div class="lumos-page" id="lumos-page-issues">' +
            '<table class="lumos-table"><thead><tr><th>Category</th><th>Issue</th><th>Severity</th><th>Pages</th></tr></thead><tbody>' +
                '<tr><td>Crawling</td><td>4XX status code</td><td>Error</td><td>1</td></tr>' +
                '<tr><td>Meta Tags</td><td>Description missing</td><td>Warning</td><td>74</td></tr>' +
                '<tr><td>Redirects</td><td>3XX redirects in links</td><td>Warning</td><td>8</td></tr>' +
            '</tbody></table>' +
        '</div>';
    }

    function statCard(label, value) {
        return '<div class="lumos-stat"><div class="lumos-stat-label">' + label + '</div><div class="lumos-stat-value">' + value + '</div></div>';
    }
    function row(url, issue, status) {
        return '<tr><td>' + esc(url) + '</td><td>' + esc(issue) + '</td><td>' + esc(status) + '</td><td><a href="#">Open</a></td></tr>';
    }
    function esc(v) {
        return String(v || '').replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }
    function setPage(page) {
        $('.lumos-page').removeClass('active');
        $('#lumos-page-' + page).addClass('active');
        $('.lumos-audit-nav a').removeClass('active');
        $('.lumos-audit-nav a[data-page="' + page + '"]').addClass('active');
        $('#lumos-page-title').text($('.lumos-audit-nav a[data-page="' + page + '"]').text());
    }
    function formatTime(totalSeconds) {
        var hrs = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
        var mins = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
        var secs = String(totalSeconds % 60).padStart(2, '0');
        return hrs + ':' + mins + ':' + secs;
    }

    var timer = null;
    function startAudit() {
        auditState.running = true;
        $('#lumos-run-status').text('Audit running');
        setPage('live');
        if (timer) clearInterval(timer);
        timer = setInterval(function () {
            auditState.seconds += 1;
            auditState.pagesCrawled += 1;
            auditState.urlsFound += 2;
            if (auditState.pagesCrawled % 7 === 0) auditState.warnings += 1;
            if (auditState.pagesCrawled % 31 === 0) auditState.errors += 1;
            $('#live-time').text(formatTime(auditState.seconds));
            $('#live-pages').text(auditState.pagesCrawled);
            $('#live-urls').text(auditState.urlsFound);
            $('#live-warnings').text(auditState.warnings);
            $('#live-errors').text(auditState.errors);
        }, 1000);
    }

    $(function () {
        $('#lumos-audit-app').html(shellMarkup());
        $(document).on('click', '.lumos-audit-nav a', function (e) {
            e.preventDefault();
            setPage($(this).data('page'));
        });
        $(document).on('click', '#lumos-run-audit', function () {
            auditState.domain = $('#lumos-domain').val() || auditState.domain;
            auditState.auditName = $('#lumos-audit-name').val() || auditState.auditName;
            startAudit();
        });
    });
})(jQuery);
