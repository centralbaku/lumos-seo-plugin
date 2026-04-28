(function ($) {
    'use strict';

    var fallbackData = {
        traffic: [],
        keywords: [],
        tracking: [],
        serp: [],
        sitemap: [],
        checklist: []
    };

    var state = $.extend(true, {}, fallbackData, window.lumosDashboard ? lumosDashboard.data : {});
    var sitePages = window.lumosDashboard && Array.isArray(lumosDashboard.sitePages) ? lumosDashboard.sitePages : [];
    var overview = window.lumosDashboard && lumosDashboard.overview ? lumosDashboard.overview : {
        totalPages: 0, avgScore: 0, goodCount: 0, needsCount: 0, distribution: [0, 0, 0]
    };

    function safeArray(v) { return Array.isArray(v) ? v : []; }
    state.keywords = safeArray(state.keywords);
    state.tracking = safeArray(state.tracking);
    state.serp = safeArray(state.serp);
    state.traffic = safeArray(state.traffic);
    state.sitemap = safeArray(state.sitemap);
    state.checklist = safeArray(state.checklist);

    if (!state.sitemap.length && sitePages.length) {
        state.sitemap = sitePages.map(function (p) {
            return { done: false, url: p.url || '', priority: p.priority || '0.8', changefreq: p.changefreq || 'weekly', notes: p.title || '' };
        });
    }

    function saveToHiddenField() {
        $('#lumos-dashboard-data-field').val(JSON.stringify(state));
    }

    function toCsvTags(tags) {
        if (!Array.isArray(tags)) return '';
        return tags.join(', ');
    }

    function fromCsvTags(value) {
        return (value || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean);
    }

    function bindTabs() {
        $('.lumos-dash-tab').on('click', function () {
            var tab = $(this).data('tab');
            $('.lumos-dash-tab').removeClass('active');
            $('.lumos-dash-panel').removeClass('active');
            $(this).addClass('active');
            $('#lumos-tab-' + tab).addClass('active');
        });
    }

    function renderOverview() {
        var cards = [
            { label: 'Pages Checked', value: overview.totalPages || 0 },
            { label: 'Average SEO Score', value: overview.avgScore || 0 },
            { label: 'Good Pages (70+)', value: overview.goodCount || 0 },
            { label: 'Needs Improvement', value: overview.needsCount || 0 }
        ];

        var html = cards.map(function (c) {
            return '<div class="lumos-card"><div class="label">' + esc(c.label) + '</div><div class="value">' + esc(String(c.value)) + '</div></div>';
        }).join('');
        $('#lumos-overview-cards').html(html);

        if (window.Chart) {
            var trafficLabels = state.traffic.map(function (x) { return x.label; });
            var trafficValues = state.traffic.map(function (x) { return Number(x.value) || 0; });
            makeChart('lumos-traffic-chart', 'line', {
                labels: trafficLabels,
                datasets: [{ label: 'Organic Traffic', data: trafficValues, fill: true, borderColor: '#2271b1', backgroundColor: 'rgba(34,113,177,0.1)', tension: 0.35 }]
            }, { y: { beginAtZero: true } });

            makeChart('lumos-rating-chart', 'bar', {
                labels: ['Good', 'Okay', 'Poor'],
                datasets: [{ label: 'Pages', data: overview.distribution || [0, 0, 0], backgroundColor: ['#46b450', '#ffb900', '#dc3232'] }]
            }, { y: { beginAtZero: true, ticks: { precision: 0 } } });
        }
    }

    var chartRefs = {};
    function makeChart(id, type, data, scales) {
        if (chartRefs[id]) chartRefs[id].destroy();
        var canvas = document.getElementById(id);
        if (!canvas) return;
        chartRefs[id] = new Chart(canvas.getContext('2d'), {
            type: type,
            data: data,
            options: { responsive: true, maintainAspectRatio: false, scales: scales || {} }
        });
    }

    function renderKeywords() {
        var q = ($('#lumos-keyword-search').val() || '').toLowerCase();
        var rows = state.keywords.filter(function (k) {
            return !q || JSON.stringify(k).toLowerCase().indexOf(q) !== -1;
        }).map(function (k, i) {
            return '<tr data-i="' + i + '">' +
                tdText('keyword', k.keyword) +
                tdText('category', k.category) +
                tdNum('volume', k.volume) +
                tdNum('difficulty', k.difficulty) +
                tdText('intent', k.intent) +
                tdText('tags', toCsvTags(k.tags || [])) +
                tdText('url', k.url) +
                tdText('priority', k.priority) +
                '<td><button type="button" class="lumos-small-btn lumos-del-keyword">Delete</button></td>' +
                '</tr>';
        }).join('');
        $('#lumos-keywords-body').html(rows);
    }

    function renderTracking() {
        var q = ($('#lumos-tracking-search').val() || '').toLowerCase();
        var tagQ = ($('#lumos-tag-filter').val() || '').toLowerCase();
        var rows = state.tracking.filter(function (r) {
            var tags = toCsvTags(r.tags || []).toLowerCase();
            var textOk = !q || JSON.stringify(r).toLowerCase().indexOf(q) !== -1;
            var tagOk = !tagQ || tags.indexOf(tagQ) !== -1;
            return textOk && tagOk;
        }).map(function (r, i) {
            var ctr = Number(r.impressions) > 0 ? ((Number(r.clicks) / Number(r.impressions)) * 100).toFixed(2) + '%' : '0%';
            return '<tr data-i="' + i + '">' +
                tdText('keyword', r.keyword) +
                tdNum('currentRank', r.currentRank) +
                tdNum('targetRank', r.targetRank) +
                tdNum('impressions', r.impressions) +
                tdNum('clicks', r.clicks) +
                '<td>' + esc(ctr) + '</td>' +
                tdText('status', r.status) +
                tdText('tags', toCsvTags(r.tags || [])) +
                '<td><button type="button" class="lumos-small-btn lumos-del-tracking">Delete</button></td>' +
                '</tr>';
        }).join('');
        $('#lumos-tracking-body').html(rows);
    }

    function renderSerp() {
        var rows = state.serp.map(function (r, i) {
            return '<tr data-i="' + i + '">' +
                tdText('keyword', r.keyword) +
                tdText('competitor', r.competitor) +
                tdNum('ourRank', r.ourRank) +
                tdNum('titleQuality', r.titleQuality) +
                tdNum('descriptionQuality', r.descriptionQuality) +
                tdText('opportunity', r.opportunity) +
                '<td><button type="button" class="lumos-small-btn lumos-del-serp">Delete</button></td>' +
                '</tr>';
        }).join('');
        $('#lumos-serp-body').html(rows);
    }

    function renderSitemap() {
        var total = state.sitemap.length;
        var done = state.sitemap.filter(function (x) { return !!x.done; }).length;
        var progress = total ? Math.round((done / total) * 100) : 0;
        $('#lumos-sitemap-stats').html(
            '<div class="lumos-card"><div class="label">URLs Tracked</div><div class="value">' + total + '</div></div>' +
            '<div class="lumos-card"><div class="label">Optimized</div><div class="value">' + done + '</div></div>' +
            '<div class="lumos-card"><div class="label">Progress</div><div class="value">' + progress + '%</div></div>'
        );

        var rows = state.sitemap.map(function (s, i) {
            return '<tr data-i="' + i + '">' +
                '<td><input type="checkbox" data-key="done" ' + (s.done ? 'checked' : '') + '></td>' +
                tdText('url', s.url, 'url') +
                tdText('priority', s.priority) +
                tdText('changefreq', s.changefreq) +
                tdText('notes', s.notes) +
                '<td><button type="button" class="lumos-small-btn lumos-del-sitemap">Delete</button></td>' +
                '</tr>';
        }).join('');
        $('#lumos-sitemap-body').html(rows);
    }

    function renderChecklist() {
        var rows = state.checklist.map(function (c, i) {
            return '<tr data-i="' + i + '">' +
                '<td><input type="checkbox" data-key="done" ' + (c.done ? 'checked' : '') + '></td>' +
                tdText('task', c.task) +
                tdText('category', c.category) +
                tdText('suggestion', c.suggestion) +
                '<td><button type="button" class="lumos-small-btn lumos-del-checklist">Delete</button></td>' +
                '</tr>';
        }).join('');
        $('#lumos-checklist-body').html(rows);
    }

    function tdText(key, value, type) {
        var inputType = type === 'url' ? 'url' : 'text';
        return '<td><input type="' + inputType + '" data-key="' + key + '" value="' + escAttr(value || '') + '"></td>';
    }
    function tdNum(key, value) {
        return '<td><input type="number" data-key="' + key + '" value="' + escAttr(String(value || 0)) + '"></td>';
    }

    function bindMutations() {
        $('#lumos-keyword-search, #lumos-tracking-search, #lumos-tag-filter').on('input', function () {
            renderKeywords();
            renderTracking();
        });

        $('#lumos-add-keyword').on('click', function () {
            state.keywords.push({ keyword: '', category: 'Primary', volume: 0, difficulty: 0, intent: 'Commercial', tags: [], url: '', priority: 'Medium' });
            renderKeywords();
        });
        $('#lumos-add-tracking').on('click', function () {
            state.tracking.push({ keyword: '', currentRank: 10, targetRank: 3, impressions: 0, clicks: 0, status: 'Planned', tags: [] });
            renderTracking();
        });
        $('#lumos-add-serp').on('click', function () {
            state.serp.push({ keyword: '', competitor: '', ourRank: 10, titleQuality: 5, descriptionQuality: 5, opportunity: 'Medium' });
            renderSerp();
        });
        $('#lumos-add-sitemap-item').on('click', function () {
            state.sitemap.push({ done: false, url: '', priority: '0.8', changefreq: 'weekly', notes: '' });
            renderSitemap();
        });
        $('#lumos-add-check-item').on('click', function () {
            state.checklist.push({ done: false, task: '', category: 'General', suggestion: '' });
            renderChecklist();
        });

        $('#lumos-reset-dashboard').on('click', function () {
            if (!window.confirm('Reset dashboard to current sample defaults?')) return;
            window.location.reload();
        });

        $(document)
            .on('click', '.lumos-del-keyword', function () { removeRow(this, state.keywords, renderKeywords); })
            .on('click', '.lumos-del-tracking', function () { removeRow(this, state.tracking, renderTracking); })
            .on('click', '.lumos-del-serp', function () { removeRow(this, state.serp, renderSerp); })
            .on('click', '.lumos-del-sitemap', function () { removeRow(this, state.sitemap, renderSitemap); })
            .on('click', '.lumos-del-checklist', function () { removeRow(this, state.checklist, renderChecklist); })
            .on('input change', '#lumos-keywords-body input', function () { updateRow(this, state.keywords, renderKeywords, true); })
            .on('input change', '#lumos-tracking-body input', function () { updateRow(this, state.tracking, renderTracking, true); })
            .on('input change', '#lumos-serp-body input', function () { updateRow(this, state.serp, renderSerp, false); })
            .on('input change', '#lumos-sitemap-body input', function () { updateRow(this, state.sitemap, renderSitemap, false); })
            .on('input change', '#lumos-checklist-body input', function () { updateRow(this, state.checklist, renderChecklist, false); });

        $('#lumos-dashboard-form').on('submit', function () {
            saveToHiddenField();
        });
    }

    function removeRow(button, arr, renderFn) {
        var i = Number($(button).closest('tr').data('i'));
        if (Number.isNaN(i)) return;
        arr.splice(i, 1);
        renderFn();
    }

    function updateRow(input, arr, renderFn, hasTags) {
        var $input = $(input);
        var $tr = $input.closest('tr');
        var i = Number($tr.data('i'));
        if (Number.isNaN(i) || !arr[i]) return;
        var key = $input.data('key');
        var val;
        if ($input.attr('type') === 'checkbox') {
            val = $input.is(':checked');
        } else if ($input.attr('type') === 'number') {
            val = Number($input.val()) || 0;
        } else {
            val = $input.val();
        }

        if (key === 'tags' && hasTags) {
            arr[i][key] = fromCsvTags(val);
        } else {
            arr[i][key] = val;
        }
        if (renderFn === renderSitemap) renderSitemap();
    }

    function esc(v) {
        return String(v || '').replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }
    function escAttr(v) { return esc(v).replace(/'/g, '&#039;'); }

    $(function () {
        bindTabs();
        bindMutations();
        renderOverview();
        renderKeywords();
        renderTracking();
        renderSerp();
        renderSitemap();
        renderChecklist();
        saveToHiddenField();
    });
})(jQuery);
