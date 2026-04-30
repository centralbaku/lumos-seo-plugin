/* Lumos SEO — Classic / Meta Box JS */
(function ($) {
    'use strict';

    // ── JSON field map: JSON key → input selector ────────────────────────
    var FIELD_MAP = {
        focus_keyword:       '#lm_focus_keyword',
        meta_title:          '#lm_meta_title',
        meta_description:    '#lm_meta_description',
        og_title:            '#lm_og_title',
        og_description:      '#lm_og_description',
        og_image:            '#lm_og_image',
        og_url:              '#lm_og_url',
        og_type:             '#lm_og_type',
        og_site_name:        '#lm_og_site_name',
        og_locale:           '#lm_og_locale',
        twitter_card:        '#lm_twitter_card',
        twitter_title:       '#lm_twitter_title',
        twitter_description: '#lm_twitter_description',
        twitter_image:       '#lm_twitter_image',
        canonical:           '#lm_canonical',
        service_schema:      '#lm_service_schema_json',
    };

    var LENGTH_RULES = {
        meta_title:       { min: 30, max: 60 },
        meta_description: { min: 120, max: 158 },
    };

    // ── Media library picker ──────────────────────────────────────────────
    var mediaFrames = {};

    $(document).on('click', '.lumos-btn-media', function () {
        var $btn     = $(this);
        var targetSel  = $btn.data('target');
        var previewSel = $btn.data('preview');
        var frameKey   = targetSel;

        if ( mediaFrames[ frameKey ] ) {
            mediaFrames[ frameKey ].open();
            return;
        }

        var frame = wp.media({
            title:    'Select or Upload Image',
            button:   { text: 'Use this image' },
            multiple: false,
            library:  { type: 'image' },
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var url = attachment.url;
            $(targetSel).val(url).trigger('input');
            $(previewSel).attr('src', url).show();
            $btn.siblings('.lumos-btn-media-remove').show();
        });

        mediaFrames[ frameKey ] = frame;
        frame.open();
    });

    $(document).on('click', '.lumos-btn-media-remove', function () {
        var $btn       = $(this);
        var targetSel  = $btn.data('target');
        var previewSel = $btn.data('preview');
        $(targetSel).val('').trigger('input');
        $(previewSel).attr('src', '').hide();
        $btn.hide();
    });

    // ── Tab switching ─────────────────────────────────────────────────────
    $(document).on('click', '.lumos-mb-tab', function () {
        var tab = $(this).data('tab');
        $('.lumos-mb-tab').removeClass('active');
        $('.lumos-mb-panel').removeClass('active');
        $(this).addClass('active');
        $('#lumos-tab-' + tab).addClass('active');
    });

    // ── Snippet preview ───────────────────────────────────────────────────
    function updateSnippet() {
        var title = $('#lm_meta_title').val() || $('input#title').val() || '(no title)';
        var desc  = $('#lm_meta_description').val() || 'No meta description set.';
        $('#lumos-preview-title').text(title);
        $('#lumos-preview-desc').text(desc);
    }
    $('#lm_meta_title, #lm_meta_description').on('input', updateSnippet);
    $(document).on('input', '#title', function () {
        if (!$('#lm_meta_title').val()) updateSnippet();
    });

    // ── Character counters & progress bars ────────────────────────────────
    function updateCounter(inputId, counterId, barId, min, max) {
        var val = $(inputId).val() || '';
        var len = val.length;
        var $cnt = $(counterId), $bar = $(barId);
        $cnt.text(len + ' / ' + max);
        var pct, color;
        if      (len === 0)    { pct = 0;   color = '#dc3232'; }
        else if (len < min)    { pct = Math.round(len / min * 50); color = '#ffb900'; }
        else if (len <= max)   { pct = Math.round(50 + (len - min) / (max - min) * 50); color = '#46b450'; }
        else                   { pct = 100; color = '#ffb900'; }
        $cnt.css('color', color);
        $bar.css({ width: pct + '%', background: color });
    }
    function refreshCounters() {
        updateCounter('#lm_meta_title',       '#lm_title_counter', '#lm_title_bar', 30, 60);
        updateCounter('#lm_meta_description', '#lm_desc_counter',  '#lm_desc_bar',  120, 158);
    }
    $('#lm_meta_title, #lm_meta_description').on('input', refreshCounters);
    refreshCounters();

    // ── OG card live preview ──────────────────────────────────────────────
    function updateOGCard() {
        var title = $('#lm_og_title').val()       || $('#lm_meta_title').val()       || '(no title)';
        var desc  = $('#lm_og_description').val() || $('#lm_meta_description').val() || '';
        var img   = $('#lm_og_image').val();
        $('#lumos-og-card-title').text(title);
        $('#lumos-og-card-desc').text(desc);
        if (img) {
            $('#lumos-og-card-img').css('background-image', 'url(' + img + ')');
            $('#lm_og_image_preview').attr('src', img).show();
        }
    }
    $('#lm_og_title, #lm_og_description, #lm_og_image, #lm_meta_title, #lm_meta_description').on('input', updateOGCard);

    // ── Twitter card live preview ─────────────────────────────────────────
    function updateTWCard() {
        var title = $('#lm_twitter_title').val()       || $('#lm_og_title').val()       || $('#lm_meta_title').val()       || '(no title)';
        var desc  = $('#lm_twitter_description').val() || $('#lm_og_description').val() || $('#lm_meta_description').val() || '';
        var img   = $('#lm_twitter_image').val()       || $('#lm_og_image').val();
        $('#lumos-tw-card-title').text(title);
        $('#lumos-tw-card-desc').text(desc);
        if (img) {
            $('#lumos-tw-card-img').css('background-image', 'url(' + img + ')');
            $('#lm_twitter_image_preview').attr('src', img).show();
        }
    }
    $('#lm_twitter_title, #lm_twitter_description, #lm_twitter_image').on('input', updateTWCard);

    function runAnalysis($button, loadingSelector) {
        $button.prop('disabled', true);
        $(loadingSelector).show();
        var content = '';
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            content = wp.data.select('core/editor').getEditedPostContent() || '';
        } else if ($('#content').length) {
            content = tinymce && tinymce.get('content')
                ? tinymce.get('content').getContent()
                : $('#content').val();
        }

        $.post(lumosSEO.ajaxurl, {
            action:           'lumos_seo_analyze',
            nonce:            lumosSEO.nonce,
            post_id:          lumosSEO.post_id,
            focus_keyword:    $('#lm_focus_keyword').val(),
            meta_title:       $('#lm_meta_title').val(),
            meta_description: $('#lm_meta_description').val(),
            content:          content,
            service_schema_enabled: $('#lm_service_schema_enabled').is(':checked') ? '1' : '',
            service_schema_json:    $('#lm_service_schema_json').val(),
        })
        .done(function (res) {
            if (res.success) renderResults(res.data);
        })
        .always(function () {
            $button.prop('disabled', false);
            $(loadingSelector).hide();
        });
    }

    // ── SEO Analysis ──────────────────────────────────────────────────────
    $('#lumos-analyze-btn').on('click', function () {
        runAnalysis($(this), '#lumos-analyzing');
    });

    // Schema tab quick analysis update
    $('#lumos-update-schema-analysis').on('click', function () {
        runAnalysis($(this), '#lumos-schema-analyzing');
    });

    var lastAnalysisData = null;

    // ── Copy report for GPT ───────────────────────────────────────────────
    $('#lumos-copy-report').on('click', function () {
        if (!lastAnalysisData) return;
        var text = buildReportText(lastAnalysisData);
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                $('#lumos-copy-report').text('✓ Copied!');
                setTimeout(function () { $('#lumos-copy-report').text('📋 Copy report for GPT'); }, 2500);
            });
        }
    });

    // Checks that JSON import can actually fix (meta fields only)
    var META_FIXABLE_IDS = {
        kw_in_title: true, meta_title_width: true,
        kw_meta_desc: true, meta_desc_length: true,
        service_schema_toggle: true, service_schema_valid: true, service_schema_type: true, service_schema_required_fields: true,
    };

    function buildReportText(data) {
        var title  = $('#lm_meta_title').val() || $('input#title').val() || '(untitled)';
        var kw     = $('#lm_focus_keyword').val() || '(none set)';
        var score  = data.score;
        var label  = score >= 70 ? 'Great' : score >= 40 ? 'Needs work' : 'Poor';
        var lines  = [];

        lines.push('SEO ANALYSIS REPORT');
        lines.push('Page title   : ' + title);
        lines.push('Focus keyword: ' + kw);
        lines.push('Score        : ' + score + '/100 — ' + label);
        lines.push('');
        lines.push('NOTE: Items marked [META] can be fixed in the JSON you generate.');
        lines.push('Items marked [CONTENT] require editing the page body directly — do NOT');
        lines.push('try to fix them in JSON; instead describe what changes are needed in');
        lines.push('the content_notes field.');
        lines.push('');

        var allChecks = (data.seo || []).concat(data.read || []);
        var problems     = allChecks.filter(function (c) { return c.status === 'bad'; });
        var improvements = allChecks.filter(function (c) { return c.status === 'ok'; });

        function formatCheck(c) {
            var tag = META_FIXABLE_IDS[c.id] ? '[META]' : '[CONTENT]';
            return '  ' + tag + ' [' + (c.priority || 'low').toUpperCase() + '] ' + c.message;
        }

        if (problems.length) {
            lines.push('PROBLEMS (' + problems.length + '):');
            problems.forEach(function (c) { lines.push(formatCheck(c)); });
            lines.push('');
        }
        if (improvements.length) {
            lines.push('IMPROVEMENTS (' + improvements.length + '):');
            improvements.forEach(function (c) { lines.push(formatCheck(c)); });
            lines.push('');
        }

        lines.push('══════════════════════════════════════════════════');
        lines.push('TASK: Generate a corrected JSON to fix all [META] items above.');
        lines.push('For [CONTENT] items, summarise what the writer should change inside');
        lines.push('"content_notes" (plain English, bullet points).');
        lines.push('');
        lines.push('Return ONLY valid JSON. Include only the fields you have improvements for:');
        lines.push('{');
        lines.push('  "focus_keyword": "...",');
        lines.push('  "meta_title": "must contain the focus keyword, 30-60 chars",');
        lines.push('  "meta_description": "must contain the focus keyword, 120-158 chars",');
        lines.push('  "og_title": "...", "og_description": "...", "og_image": "...",');
        lines.push('  "og_url": "...", "og_type": "article|website",');
        lines.push('  "og_site_name": "...", "og_locale": "en_US",');
        lines.push('  "twitter_card": "summary_large_image",');
        lines.push('  "twitter_title": "...", "twitter_description": "...",');
        lines.push('  "canonical": "...",');
        lines.push('  "noindex": false,');
        lines.push('  "use_service_schema": true,');
        lines.push('  "service_schema": {');
        lines.push('    "@context": "https://schema.org",');
        lines.push('    "@type": "Service",');
        lines.push('    "name": "...",');
        lines.push('    "description": "...",');
        lines.push('    "serviceType": "...",');
        lines.push('    "provider": { "@type": "Organization", "name": "..." }');
        lines.push('  },');
        lines.push('  "related_keywords": ["...", "..."],');
        lines.push('  "suggested_headings": ["H2 idea 1", "H2 idea 2"],');
        lines.push('  "content_notes": "bullet list of content edits the writer must make"');
        lines.push('}');

        return lines.join('\n');
    }

    function renderResults(data) {
        lastAnalysisData = data;
        var score = data.score;
        $('#lumos-results').show();

        // Donut arc
        var arc = document.getElementById('lumos-arc');
        if (arc) {
            var color = score >= 70 ? '#46b450' : score >= 40 ? '#ffb900' : '#dc3232';
            arc.setAttribute('stroke-dasharray', score + ',100');
            arc.setAttribute('stroke', color);
        }
        $('#lumos-score-num').text(score);
        $('#lumos-score-label')
            .text(score >= 70 ? '😊 Great' : score >= 40 ? '😐 Needs work' : '😟 Poor')
            .css('color', score >= 70 ? '#46b450' : score >= 40 ? '#ffb900' : '#dc3232');

        $('#lumos-checks-seo').html(buildChecklist(data.seo,  'SEO Analysis'));
        $('#lumos-checks-read').html(buildChecklist(data.read, 'Readability'));
        renderServiceSchemaAnalysis(data);

        // Show how many issues JSON can fix vs need content edits
        var allChecks = (data.seo || []).concat(data.read || []);
        var nonGood   = allChecks.filter(function (c) { return c.status !== 'good'; });
        var metaFixes = nonGood.filter(function (c) { return META_FIXABLE_IDS[c.id]; });
        var contentFixes = nonGood.length - metaFixes.length;
        var tip = '';
        if (metaFixes.length) {
            tip += '<span class="lumos-fix-tag lumos-fix-meta">✦ ' + metaFixes.length + ' fixable via JSON import</span>';
        }
        if (contentFixes) {
            tip += '<span class="lumos-fix-tag lumos-fix-content">✎ ' + contentFixes + ' require content edits</span>';
        }
        $('#lumos-fix-summary').html(tip).show();
        $('#lumos-copy-report').show().text('📋 Copy report for GPT');
    }

    var PRIORITY_CFG = {
        high:   { label: 'HIGH',   color: '#c0392b', bg: '#fdecea' },
        medium: { label: 'MED',    color: '#d35400', bg: '#fef3e7' },
        low:    { label: 'LOW',    color: '#27ae60', bg: '#eafaf1' },
    };
    var STATUS_DOT = { good: '#46b450', ok: '#ffb900', bad: '#dc3232' };
    var PORDER = { high: 0, medium: 1, low: 2 };

    function buildChecklist(checks, groupLabel) {
        if (!checks || !checks.length) return '';
        var sorted = checks.slice().sort(function (a, b) {
            return (PORDER[a.priority] || 0) - (PORDER[b.priority] || 0);
        });
        var problems     = sorted.filter(function (c) { return c.status === 'bad'; });
        var improvements = sorted.filter(function (c) { return c.status === 'ok'; });
        var good         = sorted.filter(function (c) { return c.status === 'good'; });

        var html = '<div class="lumos-check-section-label">' + groupLabel + '</div>';
        [[problems,'Problems'],[improvements,'Improvements'],[good,'Good results']].forEach(function (g) {
            if (!g[0].length) return;
            html += '<details class="lumos-check-group" ' + (g[1] !== 'Good results' ? 'open' : '') + '>';
            html += '<summary class="lumos-check-group-title">' + g[1] + ' <span>(' + g[0].length + ')</span></summary>';
            html += '<div class="lumos-check-list">';
            g[0].forEach(function (c) {
                var pc = PRIORITY_CFG[c.priority] || PRIORITY_CFG.low;
                html += '<div class="lumos-check-row">';
                html += '<span class="lumos-dot" style="background:' + (STATUS_DOT[c.status]||'#ccc') + '"></span>';
                html += '<span class="lumos-check-msg">' + c.message + '</span>';
                html += '<span class="lumos-prio-badge" style="color:' + pc.color + ';background:' + pc.bg + '">' + pc.label + '</span>';
                html += '</div>';
            });
            html += '</div></details>';
        });
        return html;
    }

    function renderServiceSchemaAnalysis(data) {
        if (!data || !data.seo) {
            $('#lumos-service-schema-analysis').html(
                '<div class="lumos-schema-analysis-title">Service Schema Analysis</div>' +
                '<div class="lumos-hint">Click "Update Analysis" to validate your Service schema.</div>'
            );
            return;
        }
        var checks = (data.seo || []).filter(function (c) {
            return (c.id || '').indexOf('service_schema') === 0;
        });
        if (!checks.length) {
            $('#lumos-service-schema-analysis').html('');
            return;
        }
        var html = '<div class="lumos-schema-analysis-title">Service Schema Analysis</div>';
        html += '<div class="lumos-check-list">';
        checks.forEach(function (c) {
            var pc = PRIORITY_CFG[c.priority] || PRIORITY_CFG.low;
            html += '<div class="lumos-check-row">';
            html += '<span class="lumos-dot" style="background:' + (STATUS_DOT[c.status]||'#ccc') + '"></span>';
            html += '<span class="lumos-check-msg">' + c.message + '</span>';
            html += '<span class="lumos-prio-badge" style="color:' + pc.color + ';background:' + pc.bg + '">' + pc.label + '</span>';
            html += '</div>';
        });
        html += '</div>';
        $('#lumos-service-schema-analysis').html(html);
    }

    // ── Import JSON ───────────────────────────────────────────────────────
    var parsedImport = null;

    // Move modal to <body> so position:fixed isn't broken by WP meta box transforms
    $(function () {
        var $modal = $('#lumos-import-modal');
        if ( $modal.length && !$modal.parent().is('body') ) {
            $modal.appendTo('body');
        }
    });

    function openModal() {
        $('#lumos-import-modal').show();
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        $('#lumos-import-modal').hide();
        document.body.style.overflow = '';
    }

    $('#lumos-open-import').on('click', function () {
        openModal();
        parsedImport = null;
        $('#lumos-validate-import').text('Validate & Preview');
        $('#lumos-import-feedback, #lumos-import-advisory').html('');
        $('#lumos-import-json').val('');
    });

    $('#lumos-close-import, #lumos-close-import-2').on('click', closeModal);

    // Close on overlay click
    $('#lumos-import-modal').on('click', function (e) {
        if ($(e.target).is('#lumos-import-modal')) closeModal();
    });

    // Copy example JSON
    $('#lumos-copy-example').on('click', function () {
        if (navigator.clipboard && lumosSEO.exampleJson) {
            navigator.clipboard.writeText(lumosSEO.exampleJson).then(function () {
                $('#lumos-copy-example').text('✓ Copied!');
                setTimeout(function () { $('#lumos-copy-example').text('Copy example JSON'); }, 2000);
            });
        }
    });

    $('#lumos-copy-service-schema-prompt').on('click', function () {
        var schema = ($('#lm_service_schema_json').val() || '').trim();
        var prompt = [
            'Improve this Service schema JSON-LD for SEO and rich results.',
            'Return ONLY valid JSON object (no markdown, no <script> tags).',
            'Keep @type as "Service" and preserve factual details.',
            '',
            schema || '{"@context":"https://schema.org","@type":"Service","name":"..."}'
        ].join('\n');

        if (navigator.clipboard) {
            navigator.clipboard.writeText(prompt).then(function () {
                $('#lumos-copy-service-schema-prompt').text('✓ Prompt copied');
                setTimeout(function () {
                    $('#lumos-copy-service-schema-prompt').text('Copy GPT prompt');
                }, 2000);
            });
        }
    });

    // Validate → Apply (two-step)
    $('#lumos-validate-import').on('click', function () {
        var raw = $('#lumos-import-json').val().trim();
        if (!raw) return;

        if (parsedImport) {
            // Second click: Apply
            applyImport(parsedImport);
            closeModal();
            return;
        }

        // First click: Validate
        var result = validateJson(raw);
        if (result.error) {
            $('#lumos-import-feedback').html(
                '<div class="lumos-feedback-error">✕ ' + escHtml(result.error) + '</div>'
            );
            return;
        }

        parsedImport = result;

        // Show success feedback
        var fb = '<div class="lumos-feedback-ok"><strong>✓ Ready to apply</strong><ul>';
        result.applied.forEach(function (k) { fb += '<li style="color:#1e8449">' + escHtml(k) + '</li>'; });
        result.warnings.forEach(function (w) { fb += '<li style="color:#d35400">⚠ ' + escHtml(w) + '</li>'; });
        fb += '</ul></div>';
        $('#lumos-import-feedback').html(fb);

        // Advisory sections
        var adv = '';
        if (result.advisory.suggested_headings && result.advisory.suggested_headings.length) {
            adv += '<div class="lumos-advisory lumos-advisory--headings"><strong>💡 Suggested headings:</strong><ul>';
            result.advisory.suggested_headings.forEach(function (h) { adv += '<li>' + escHtml(h) + '</li>'; });
            adv += '</ul></div>';
        }
        if (result.advisory.content_notes) {
            adv += '<div class="lumos-advisory lumos-advisory--notes"><strong>📝 Content notes:</strong> ' + escHtml(result.advisory.content_notes) + '</div>';
        }
        if (result.advisory.related_keywords && result.advisory.related_keywords.length) {
            adv += '<div class="lumos-advisory lumos-advisory--kw"><strong>🔑 Related keywords:</strong> ' + escHtml(result.advisory.related_keywords.join(', ')) + '</div>';
        }
        $('#lumos-import-advisory').html(adv);

        $(this).text('✓ Apply Import').css('background', '#46b450');
    });

    // ── File picker ───────────────────────────────────────────────────────
    var $dropZone = $('#lumos-file-drop');
    var $fileInput = $('#lumos-json-file');

    // Clicking anywhere on the drop zone triggers the file input
    $dropZone.on('click', function (e) {
        if (!$(e.target).is($fileInput)) $fileInput.trigger('click');
    });

    // Drag-over highlight
    $dropZone.on('dragover dragenter', function (e) {
        e.preventDefault();
        $dropZone.addClass('lumos-file-drop--over');
    });
    $dropZone.on('dragleave drop', function () {
        $dropZone.removeClass('lumos-file-drop--over');
    });

    // File dropped
    $dropZone.on('drop', function (e) {
        e.preventDefault();
        var file = e.originalEvent.dataTransfer.files[0];
        if (file) readJsonFile(file);
    });

    // File selected via dialog
    $fileInput.on('change', function () {
        if (this.files[0]) readJsonFile(this.files[0]);
    });

    function readJsonFile(file) {
        if (!file.name.match(/\.json$/i) && file.type !== 'application/json') {
            $('#lumos-import-feedback').html(
                '<div class="lumos-feedback-error">✕ Please select a .json file.</div>'
            );
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
            $('#lumos-import-json').val(e.target.result).trigger('input');
            $('#lumos-file-name').text('✓ ' + file.name);
        };
        reader.readAsText(file);
    }

    // Reset state when textarea changes
    $('#lumos-import-json').on('input', function () {
        parsedImport = null;
        $('#lumos-validate-import').text('Validate & Preview').css('background', '');
        $('#lumos-import-feedback, #lumos-import-advisory').html('');
    });

    function validateJson(raw) {
        var data;
        try { data = JSON.parse(raw); } catch (e) { return { error: 'Invalid JSON: ' + e.message }; }
        if (typeof data !== 'object' || Array.isArray(data)) return { error: 'JSON must be an object (not an array).' };

        var applied = [], warnings = [], result = {};
        var advisory = {};

        Object.keys(FIELD_MAP).forEach(function (jsonKey) {
            if (!(jsonKey in data)) return;
            var val = data[jsonKey];
            if (jsonKey === 'service_schema' && typeof val === 'object' && val && !Array.isArray(val)) {
                val = JSON.stringify(val, null, 2);
            }
            if (typeof val !== 'string') { warnings.push(jsonKey + ' skipped (not a string)'); return; }
            var rule = LENGTH_RULES[jsonKey];
            if (rule && (val.length < rule.min || val.length > rule.max)) {
                warnings.push(jsonKey + ' is ' + val.length + ' chars (ideal ' + rule.min + '–' + rule.max + ')');
            }
            result[jsonKey] = val;
            applied.push(jsonKey);
        });

        // noindex boolean
        if ('noindex' in data) {
            result._noindex = data.noindex ? '1' : '';
            applied.push('noindex');
        }
        if ('use_service_schema' in data) {
            result._service_schema_enabled = data.use_service_schema ? '1' : '';
            applied.push('use_service_schema');
        }

        ['related_keywords','suggested_headings','content_notes'].forEach(function (k) {
            if (k in data) advisory[k] = data[k];
        });

        if (!applied.length) return { error: 'No recognised SEO fields found in the JSON.' };
        return { result: result, applied: applied, warnings: warnings, advisory: advisory };
    }

    function applyImport(parsed) {
        // Fill every field
        Object.keys(parsed.result).forEach(function (jsonKey) {
            var sel = FIELD_MAP[jsonKey];
            if (!sel) return;
            var $el = $(sel);
            if ($el.is('textarea') || $el.is('input[type="text"]') || $el.is('input[type="url"]')) {
                $el.val(parsed.result[jsonKey]).trigger('input');
            } else if ($el.is('select')) {
                $el.val(parsed.result[jsonKey]).trigger('change');
            }
        });

        // Handle noindex checkbox
        if ('_noindex' in parsed.result) {
            $('input[name="noindex"]').prop('checked', parsed.result._noindex === '1');
        }
        if ('_service_schema_enabled' in parsed.result) {
            $('#lm_service_schema_enabled').prop('checked', parsed.result._service_schema_enabled === '1');
        }

        // Switch to the SEO tab to show filled fields
        $('.lumos-mb-tab[data-tab="seo"]').trigger('click');

        // Flash highlight on filled inputs
        Object.keys(parsed.result).forEach(function (k) {
            var sel = FIELD_MAP[k];
            if (sel) {
                $(sel).addClass('lumos-flash');
                setTimeout(function () { $(sel).removeClass('lumos-flash'); }, 1500);
            }
        });

        // Refresh all live previews
        updateSnippet();
        refreshCounters();
        updateOGCard();
        updateTWCard();
    }

    function escHtml(s) {
        return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Init ──────────────────────────────────────────────────────────────
    updateSnippet();
    updateOGCard();
    updateTWCard();
    renderServiceSchemaAnalysis(null);

})(jQuery);
