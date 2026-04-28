/* Lumos SEO — Elementor Editor Panel */
(function ($) {
    'use strict';

    var DEBOUNCE_MS = 900;
    var state = {
        focusKw:   lumosSEO.meta.focus_keyword          || '',
        metaTitle: lumosSEO.meta.meta_title              || '',
        metaDesc:  lumosSEO.meta.meta_description        || '',
        // OG
        ogTitle:   lumosSEO.meta.og_title                || '',
        ogDesc:    lumosSEO.meta.og_description          || '',
        ogImage:   lumosSEO.meta.og_image                || '',
        ogUrl:     lumosSEO.meta.og_url                  || '',
        ogType:    lumosSEO.meta.og_type                 || '',
        ogSite:    lumosSEO.meta.og_site_name            || '',
        ogLocale:  lumosSEO.meta.og_locale               || '',
        // Twitter
        twCard:    lumosSEO.meta.twitter_card            || '',
        twTitle:   lumosSEO.meta.twitter_title           || '',
        twDesc:    lumosSEO.meta.twitter_description     || '',
        twImage:   lumosSEO.meta.twitter_image           || '',
        // Advanced
        noindex:   lumosSEO.meta.noindex                 || '',
        canonical: lumosSEO.meta.canonical               || '',
        analysis:  null,
        loading:   false,
        open:      false,
        activeTab: 'seo',
    };

    // ── Priority config ──────────────────────────────────────────────────
    var PRIORITY_LABELS = {
        high:   { label: 'HIGH',   color: '#e74c3c', bg: '#fdecea' },
        medium: { label: 'MEDIUM', color: '#e67e22', bg: '#fef3e7' },
        low:    { label: 'LOW',    color: '#27ae60', bg: '#eafaf1' },
    };
    var STATUS_COLORS = { good: '#46b450', ok: '#ffb900', bad: '#dc3232' };

    // ── Utility ──────────────────────────────────────────────────────────
    function debounce(fn, ms) {
        var t;
        return function () {
            var args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(null, args); }, ms);
        };
    }

    function scoreEmoji(s) {
        if (s === null || s === undefined) return '😐';
        if (s >= 70) return '😊';
        if (s >= 40) return '😐';
        return '😟';
    }

    function scoreColor(s) {
        if (s >= 70) return '#46b450';
        if (s >= 40) return '#ffb900';
        return '#dc3232';
    }

    // Get rendered HTML from the Elementor preview iframe
    function getElementorContent() {
        var iframe = document.getElementById('elementor-preview-iframe');
        if (!iframe || !iframe.contentDocument) return '';
        var body = iframe.contentDocument.body;
        return body ? body.innerHTML : '';
    }

    // Save a single meta field via AJAX (no-op on error — non-critical)
    function saveMeta(key, value) {
        $.post(lumosSEO.ajaxurl, {
            action:   'lumos_seo_save_meta',
            nonce:    lumosSEO.nonce,
            post_id:  lumosSEO.post_id,
            meta_key: key,
            meta_val: value,
        });
    }

    // ── AJAX analysis ────────────────────────────────────────────────────
    function runAnalysis() {
        if (state.loading) return;
        state.loading = true;
        renderPanel();

        $.post(lumosSEO.ajaxurl, {
            action:           'lumos_seo_analyze',
            nonce:            lumosSEO.nonce,
            post_id:          lumosSEO.post_id,
            focus_keyword:    state.focusKw,
            meta_title:       state.metaTitle,
            meta_description: state.metaDesc,
            content:          getElementorContent(),
        })
        .done(function (res) {
            state.loading  = false;
            state.analysis = res.success ? res.data : null;
            renderPanel();
        })
        .fail(function () {
            state.loading = false;
            renderPanel();
        });
    }

    var debouncedAnalyze = debounce(runAnalysis, DEBOUNCE_MS);

    // ── DOM Builders ─────────────────────────────────────────────────────
    function el(tag, attrs, children) {
        var e = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'style' && typeof attrs[k] === 'object') {
                    Object.assign(e.style, attrs[k]);
                } else if (k === 'html') {
                    e.innerHTML = attrs[k];
                } else if (k === 'on') {
                    Object.keys(attrs.on).forEach(function (ev) { e.addEventListener(ev, attrs.on[ev]); });
                } else {
                    e[k] = attrs[k];
                }
            });
        }
        if (children) {
            (Array.isArray(children) ? children : [children]).forEach(function (c) {
                if (c == null) return;
                e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
            });
        }
        return e;
    }

    function dot(status) {
        return el('span', {
            style: {
                display: 'inline-block', width: '10px', height: '10px',
                borderRadius: '50%', background: STATUS_COLORS[status] || '#ccc',
                flexShrink: '0', marginTop: '4px',
            },
        });
    }

    function priorityBadge(priority) {
        var cfg = PRIORITY_LABELS[priority] || PRIORITY_LABELS.low;
        return el('span', {
            className: 'lumos-priority-badge',
            style: {
                fontSize: '9px', fontWeight: '700', padding: '1px 5px',
                borderRadius: '3px', color: cfg.color, background: cfg.bg,
                marginLeft: '6px', letterSpacing: '0.04em', flexShrink: '0',
            },
        }, cfg.label);
    }

    function checkRow(check) {
        var row = el('div', { className: 'lumos-check-row', style: { display: 'flex', gap: '8px', padding: '7px 0', borderBottom: '1px solid #f0f0f0', alignItems: 'flex-start' } }, [
            dot(check.status),
            el('span', { style: { flex: '1', fontSize: '12px', lineHeight: '1.5', color: '#333' }, html: check.message }),
            priorityBadge(check.priority),
        ]);
        return row;
    }

    function checkGroup(title, checks, defaultOpen) {
        if (!checks.length) return null;
        var isOpen = defaultOpen;
        var content = el('div', { className: 'lumos-group-content', style: { display: isOpen ? 'block' : 'none', padding: '0 0 4px' } },
            checks.map(function (c) { return checkRow(c); })
        );
        var toggle = el('button', {
            className: 'lumos-group-toggle',
            style: {
                width: '100%', textAlign: 'left', background: 'none', border: 'none',
                padding: '8px 0', cursor: 'pointer', fontSize: '12px', fontWeight: '600',
                color: '#444', display: 'flex', alignItems: 'center', gap: '6px',
            },
        }, [
            el('span', { className: 'lumos-toggle-arrow', style: { transition: 'transform .2s', display: 'inline-block', transform: isOpen ? 'rotate(90deg)' : 'rotate(0deg)' } }, '▶'),
            title + ' (' + checks.length + ')',
        ]);
        toggle.addEventListener('click', function () {
            isOpen = !isOpen;
            content.style.display = isOpen ? 'block' : 'none';
            toggle.querySelector('.lumos-toggle-arrow').style.transform = isOpen ? 'rotate(90deg)' : 'rotate(0deg)';
        });
        var wrap = el('div', { className: 'lumos-check-group' }, [toggle, content]);
        return wrap;
    }

    function groupChecks(checks) {
        var c = checks || [];
        return {
            problems:     c.filter(function (x) { return x.status === 'bad'; }),
            improvements: c.filter(function (x) { return x.status === 'ok'; }),
            good:         c.filter(function (x) { return x.status === 'good'; }),
        };
    }

    // Sort by priority: HIGH first
    var PORDER = { high: 0, medium: 1, low: 2 };
    function sortByPriority(checks) {
        return checks.slice().sort(function (a, b) {
            return (PORDER[a.priority] || 0) - (PORDER[b.priority] || 0);
        });
    }

    // ── Snippet preview ──────────────────────────────────────────────────
    function buildSnippet() {
        var title   = state.metaTitle || document.title || '(no title)';
        var desc    = state.metaDesc  || 'No meta description set.';
        var preview = el('div', { className: 'lumos-snippet-preview' }, [
            el('div', { className: 'lumos-snippet-title', html: escHtml(title) }),
            el('div', { className: 'lumos-snippet-url' }, lumosSEO.siteUrl + '/'),
            el('div', { className: 'lumos-snippet-desc', html: escHtml(desc) }),
        ]);
        return preview;
    }

    function escHtml(s) {
        return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Labeled field ────────────────────────────────────────────────────
    function labeledInput(label, value, placeholder, onChange, maxChars) {
        var input = el('input', {
            className: 'lumos-field',
            style: { width: '100%', boxSizing: 'border-box', padding: '6px 8px', border: '1px solid #ddd', borderRadius: '4px', fontSize: '12px' },
            value: value,
            placeholder: placeholder || '',
        });
        var counter = el('span', { className: 'lumos-counter', style: { fontSize: '10px', color: '#999', float: 'right' } }, value.length + (maxChars ? '/' + maxChars : ''));
        var bar = maxChars ? buildBar(value.length, maxChars) : null;

        input.addEventListener('input', function () {
            var v = input.value;
            counter.textContent = v.length + (maxChars ? '/' + maxChars : '');
            if (bar) updateBar(bar, v.length, maxChars);
            onChange(v);
        });

        var wrap = el('div', { style: { marginBottom: '10px' } }, [
            el('div', { style: { display: 'flex', justifyContent: 'space-between', marginBottom: '3px' } }, [
                el('label', { style: { fontSize: '11px', fontWeight: '600', color: '#555' } }, label),
                counter,
            ]),
            input,
            bar,
        ]);
        return wrap;
    }

    function labeledTextarea(label, value, placeholder, onChange, maxChars) {
        var ta = el('textarea', {
            className: 'lumos-field',
            style: { width: '100%', boxSizing: 'border-box', padding: '6px 8px', border: '1px solid #ddd', borderRadius: '4px', fontSize: '12px', resize: 'vertical', minHeight: '60px' },
            placeholder: placeholder || '',
        });
        ta.value = value;
        var counter = el('span', { style: { fontSize: '10px', color: '#999', float: 'right' } }, value.length + (maxChars ? '/' + maxChars : ''));
        var bar = maxChars ? buildBar(value.length, maxChars) : null;

        ta.addEventListener('input', function () {
            var v = ta.value;
            counter.textContent = v.length + (maxChars ? '/' + maxChars : '');
            if (bar) updateBar(bar, v.length, maxChars);
            onChange(v);
        });

        var wrap = el('div', { style: { marginBottom: '10px' } }, [
            el('div', { style: { display: 'flex', justifyContent: 'space-between', marginBottom: '3px' } }, [
                el('label', { style: { fontSize: '11px', fontWeight: '600', color: '#555' } }, label),
                counter,
            ]),
            ta,
            bar,
        ]);
        return wrap;
    }

    function buildBar(len, max) {
        var fill = el('div', { style: { height: '100%', borderRadius: '2px', width: barWidth(len, max) + '%', background: barColor(len, max), transition: 'width .2s, background .2s' } });
        var wrap = el('div', { style: { height: '4px', background: '#e0e0e0', borderRadius: '2px', marginTop: '4px', overflow: 'hidden' } }, [fill]);
        wrap._fill = fill;
        return wrap;
    }

    function updateBar(bar, len, max) {
        if (!bar || !bar._fill) return;
        bar._fill.style.width   = barWidth(len, max) + '%';
        bar._fill.style.background = barColor(len, max);
    }

    function barWidth(len, max) {
        if (len === 0)   return 0;
        if (len > max)   return 100;
        return Math.round(len / max * 100);
    }

    function barColor(len, max) {
        var min = Math.round(max * 0.5);
        if (len === 0)        return '#dc3232';
        if (len < min)        return '#ffb900';
        if (len <= max)       return '#46b450';
        return '#ffb900';
    }

    // ── Tab switcher ─────────────────────────────────────────────────────
    function buildTabs() {
        var tabs = ['seo', 'readability', 'snippet', 'social', 'advanced'];
        var labels = { seo: 'SEO', readability: 'Readability', snippet: 'Preview', social: 'Social', advanced: 'Advanced' };
        var tabBar = el('div', { className: 'lumos-tab-bar' });
        tabs.forEach(function (t) {
            var btn = el('button', {
                className: 'lumos-tab' + (state.activeTab === t ? ' active' : ''),
                on: { click: function () {
                    state.activeTab = t;
                    renderPanelBody();
                }},
            }, labels[t]);
            tabBar.appendChild(btn);
        });
        return tabBar;
    }

    // ── Panel body ────────────────────────────────────────────────────────
    function buildPanelBody() {
        var body = el('div', { className: 'lumos-panel-body' });

        if (state.activeTab === 'seo') {
            body.appendChild(buildSEOTab());
        } else if (state.activeTab === 'readability') {
            body.appendChild(buildReadabilityTab());
        } else if (state.activeTab === 'snippet') {
            body.appendChild(buildSnippetTab());
        } else if (state.activeTab === 'social') {
            body.appendChild(buildSocialTab());
        } else if (state.activeTab === 'advanced') {
            body.appendChild(buildAdvancedTab());
        }

        return body;
    }

    function buildAnalysisSection(checks, score) {
        var wrap = el('div');

        if (state.loading) {
            wrap.appendChild(el('div', { style: { textAlign: 'center', padding: '20px', color: '#999' } }, 'Analyzing…'));
            return wrap;
        }

        if (!checks) {
            wrap.appendChild(el('p', { style: { color: '#999', fontSize: '12px', fontStyle: 'italic' } },
                'Set a focus keyphrase and click Analyze.'));
            return wrap;
        }

        // Score bar
        var scoreEl = el('div', { style: { display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '14px', padding: '10px', background: '#f8f8f8', borderRadius: '6px' } }, [
            el('span', { style: { fontSize: '22px' } }, scoreEmoji(score)),
            el('div', null, [
                el('div', { style: { fontWeight: '700', fontSize: '18px', color: scoreColor(score) } }, score + '/100'),
                el('div', { style: { fontSize: '11px', color: '#777' } }, score >= 70 ? 'Great' : score >= 40 ? 'Needs improvement' : 'Poor'),
            ]),
        ]);
        wrap.appendChild(scoreEl);

        var groups = groupChecks(checks);
        [
            { key: 'problems',     label: 'Problems',     open: true },
            { key: 'improvements', label: 'Improvements', open: true },
            { key: 'good',         label: 'Good results', open: false },
        ].forEach(function (g) {
            var sorted = sortByPriority(groups[g.key]);
            var node = checkGroup(g.label, sorted, g.open);
            if (node) wrap.appendChild(node);
        });

        return wrap;
    }

    function buildSEOTab() {
        var wrap = el('div');

        // AI Import banner
        var importBanner = el('div', { style: {
            display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            background: 'linear-gradient(135deg,#667eea,#764ba2)',
            borderRadius: '6px', padding: '8px 12px', marginBottom: '12px',
        }}, [
            el('span', { style: { color: '#fff', fontSize: '11px', fontWeight: '600' }}, '🤖 AI-generated SEO'),
            el('button', {
                style: {
                    background: 'rgba(255,255,255,.18)', color: '#fff', border: '1px solid rgba(255,255,255,.5)',
                    borderRadius: '4px', padding: '3px 10px', cursor: 'pointer', fontSize: '11px', fontWeight: '600',
                },
                on: { click: openImportModal },
            }, 'Import JSON'),
        ]);
        wrap.appendChild(importBanner);

        // Focus keyphrase
        var kwWrap = labeledInput('Focus Keyphrase', state.focusKw, 'e.g. best running shoes',
            function (v) {
                state.focusKw = v;
                saveMeta('_lumo_focus_keyword', v);
                debouncedAnalyze();
            }
        );
        wrap.appendChild(kwWrap);

        var analyzeBtn = el('button', {
            className: 'lumos-btn-primary',
            style: { width: '100%', marginBottom: '14px' },
            on: { click: runAnalysis },
        }, state.loading ? 'Analyzing…' : 'Analyze now');
        wrap.appendChild(analyzeBtn);

        var a = state.analysis;
        wrap.appendChild(buildAnalysisSection(a ? a.seo : null, a ? a.seo_score : null));
        return wrap;
    }

    function buildReadabilityTab() {
        var a = state.analysis;
        return buildAnalysisSection(a ? a.read : null, a ? a.read_score : null);
    }

    function buildSnippetTab() {
        var wrap = el('div');
        wrap.appendChild(buildSnippet());
        wrap.appendChild(labeledInput('SEO Title', state.metaTitle, 'Post title', function (v) {
            state.metaTitle = v;
            saveMeta('_lumo_meta_title', v);
            debouncedAnalyze();
        }, 60));
        wrap.appendChild(labeledTextarea('Meta Description', state.metaDesc, 'Write a compelling meta description…', function (v) {
            state.metaDesc = v;
            saveMeta('_lumo_meta_description', v);
            debouncedAnalyze();
        }, 158));
        return wrap;
    }

    function sectionHeading(text) {
        return el('div', { style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.06em', color: '#888', margin: '14px 0 8px', paddingBottom: '4px', borderBottom: '1px solid #eee' } }, text);
    }

    function buildSocialTab() {
        var wrap = el('div');

        // ── Open Graph ───────────────────────────────────────────────────
        wrap.appendChild(sectionHeading('Open Graph — Facebook · LinkedIn · WhatsApp'));
        wrap.appendChild(el('p', { style: { fontSize: '11px', color: '#999', marginBottom: '10px' } },
            'Controls how your page appears when shared on social platforms.'));

        wrap.appendChild(labeledInput('og:title', state.ogTitle, 'Defaults to SEO title', function (v) {
            state.ogTitle = v; saveMeta('_lumo_og_title', v);
        }));
        wrap.appendChild(labeledTextarea('og:description', state.ogDesc, 'Defaults to meta description', function (v) {
            state.ogDesc = v; saveMeta('_lumo_og_description', v);
        }));

        // og:image with live preview
        var imgPreview = state.ogImage ? el('img', { src: state.ogImage, style: { width: '100%', borderRadius: '4px', border: '1px solid #ddd', marginTop: '6px', display: 'block' } }) : null;
        var imgWrap = el('div');
        if (imgPreview) imgWrap.appendChild(imgPreview);
        wrap.appendChild(labeledInput('og:image (URL)', state.ogImage, 'https://yoursite.com/image.jpg — 1200×630px', function (v) {
            state.ogImage = v; saveMeta('_lumo_og_image', v);
            imgWrap.innerHTML = '';
            if (v) {
                var img = el('img', { src: v, style: { width: '100%', borderRadius: '4px', border: '1px solid #ddd', marginTop: '4px', display: 'block' } });
                imgWrap.appendChild(img);
            }
        }));
        wrap.appendChild(imgWrap);

        wrap.appendChild(labeledInput('og:url', state.ogUrl, 'Defaults to page URL', function (v) {
            state.ogUrl = v; saveMeta('_lumo_og_url', v);
        }));

        // og:type + og:locale side by side
        var row = el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }});
        row.appendChild(labeledInput('og:type', state.ogType, 'article', function (v) {
            state.ogType = v; saveMeta('_lumo_og_type', v);
        }));
        row.appendChild(labeledInput('og:locale', state.ogLocale, 'en_US', function (v) {
            state.ogLocale = v; saveMeta('_lumo_og_locale', v);
        }));
        wrap.appendChild(row);

        wrap.appendChild(labeledInput('og:site_name', state.ogSite, lumosSEO.siteName || 'Your Brand', function (v) {
            state.ogSite = v; saveMeta('_lumo_og_site_name', v);
        }));

        // ── Twitter / X ──────────────────────────────────────────────────
        wrap.appendChild(sectionHeading('Twitter / X'));
        wrap.appendChild(el('p', { style: { fontSize: '11px', color: '#999', marginBottom: '10px' } },
            'Falls back to Open Graph values when left blank.'));

        // twitter:card select
        var cardWrap = el('div', { style: { marginBottom: '10px' }});
        cardWrap.appendChild(el('label', { style: { fontSize: '11px', fontWeight: '600', color: '#555', display: 'block', marginBottom: '3px' }}, 'twitter:card'));
        var cardSel = el('select', { style: { width: '100%', padding: '6px 8px', border: '1px solid #ddd', borderRadius: '4px', fontSize: '12px', boxSizing: 'border-box' }});
        [
            ['summary_large_image', 'summary_large_image (large image)'],
            ['summary',             'summary (small image)'],
            ['app',                 'app'],
            ['player',              'player'],
        ].forEach(function (opt) {
            var o = el('option', { value: opt[0] }, opt[1]);
            if ((state.twCard || 'summary_large_image') === opt[0]) o.selected = true;
            cardSel.appendChild(o);
        });
        cardSel.addEventListener('change', function () {
            state.twCard = cardSel.value; saveMeta('_lumo_twitter_card', state.twCard);
        });
        cardWrap.appendChild(cardSel);
        wrap.appendChild(cardWrap);

        wrap.appendChild(labeledInput('twitter:title', state.twTitle, 'Falls back to og:title', function (v) {
            state.twTitle = v; saveMeta('_lumo_twitter_title', v);
        }));
        wrap.appendChild(labeledTextarea('twitter:description', state.twDesc, 'Falls back to og:description', function (v) {
            state.twDesc = v; saveMeta('_lumo_twitter_description', v);
        }));

        var twImgPreview = state.twImage ? el('img', { src: state.twImage, style: { width: '100%', borderRadius: '4px', border: '1px solid #ddd', marginTop: '6px', display: 'block' } }) : null;
        var twImgWrap = el('div');
        if (twImgPreview) twImgWrap.appendChild(twImgPreview);
        wrap.appendChild(labeledInput('twitter:image (URL)', state.twImage, 'Falls back to og:image', function (v) {
            state.twImage = v; saveMeta('_lumo_twitter_image', v);
            twImgWrap.innerHTML = '';
            if (v) twImgWrap.appendChild(el('img', { src: v, style: { width: '100%', borderRadius: '4px', border: '1px solid #ddd', marginTop: '4px', display: 'block' } }));
        }));
        wrap.appendChild(twImgWrap);

        return wrap;
    }

    function buildAdvancedTab() {
        var wrap = el('div');

        // Canonical
        wrap.appendChild(labeledInput('Canonical URL', state.canonical, 'Defaults to page URL', function (v) {
            state.canonical = v; saveMeta('_lumo_canonical', v);
        }));

        // noindex
        var cb = el('input', { type: 'checkbox' });
        cb.checked = state.noindex === '1';
        cb.addEventListener('change', function () {
            state.noindex = cb.checked ? '1' : '';
            saveMeta('_lumo_noindex', state.noindex);
        });
        wrap.appendChild(el('label', { style: { display: 'flex', alignItems: 'center', gap: '8px', fontSize: '13px', cursor: 'pointer', margin: '12px 0 6px' } }, [cb, 'No index, No follow']));
        wrap.appendChild(el('p', { style: { fontSize: '11px', color: '#999' } }, 'Prevents this page from appearing in search results.'));
        return wrap;
    }

    // ── Full panel render ─────────────────────────────────────────────────
    var panelEl = null;

    function renderPanel() {
        if (!panelEl) return;
        var body = panelEl.querySelector('.lumos-panel-body');
        if (body) body.parentNode.removeChild(body);
        var tabBar = panelEl.querySelector('.lumos-tab-bar');
        if (tabBar) tabBar.parentNode.removeChild(tabBar);

        panelEl.querySelector('.lumos-panel-inner').appendChild(buildTabs());
        panelEl.querySelector('.lumos-panel-inner').appendChild(buildPanelBody());
    }

    function renderPanelBody() {
        if (!panelEl) return;
        var body = panelEl.querySelector('.lumos-panel-body');
        if (body) body.parentNode.removeChild(body);
        panelEl.querySelector('.lumos-panel-inner').appendChild(buildPanelBody());

        // Re-mark active tab
        panelEl.querySelectorAll('.lumos-tab').forEach(function (btn) {
            btn.classList.toggle('active', btn.textContent.toLowerCase().indexOf(state.activeTab.slice(0,3)) !== -1 || btn.dataset.tab === state.activeTab);
        });
    }

    // ── Panel creation ────────────────────────────────────────────────────
    function createPanel() {
        panelEl = el('div', { id: 'lumos-seo-panel', className: 'lumos-seo-panel' + (state.open ? ' open' : '') });

        // Header
        var header = el('div', { className: 'lumos-panel-header' }, [
            el('div', { className: 'lumos-panel-logo' }, [
                el('span', { style: { fontSize: '16px' } }, '🔍'),
                el('span', { style: { fontWeight: '700', fontSize: '14px' } }, 'Lumos SEO'),
            ]),
            el('button', {
                className: 'lumos-panel-close',
                on: { click: function () { state.open = false; panelEl.classList.remove('open'); } },
            }, '✕'),
        ]);

        var inner = el('div', { className: 'lumos-panel-inner' });
        inner.appendChild(buildTabs());
        inner.appendChild(buildPanelBody());

        panelEl.appendChild(header);
        panelEl.appendChild(inner);
        document.body.appendChild(panelEl);
    }

    // ── AI JSON Import ────────────────────────────────────────────────────
    var IMPORT_FIELDS = {
        focus_keyword:       { stateKey: 'focusKw',   metaKey: '_lumo_focus_keyword' },
        meta_title:          { stateKey: 'metaTitle', metaKey: '_lumo_meta_title' },
        meta_description:    { stateKey: 'metaDesc',  metaKey: '_lumo_meta_description' },
        og_title:            { stateKey: 'ogTitle',   metaKey: '_lumo_og_title' },
        og_description:      { stateKey: 'ogDesc',    metaKey: '_lumo_og_description' },
        og_image:            { stateKey: 'ogImage',   metaKey: '_lumo_og_image' },
        og_url:              { stateKey: 'ogUrl',     metaKey: '_lumo_og_url' },
        og_type:             { stateKey: 'ogType',    metaKey: '_lumo_og_type' },
        og_site_name:        { stateKey: 'ogSite',    metaKey: '_lumo_og_site_name' },
        og_locale:           { stateKey: 'ogLocale',  metaKey: '_lumo_og_locale' },
        twitter_card:        { stateKey: 'twCard',    metaKey: '_lumo_twitter_card' },
        twitter_title:       { stateKey: 'twTitle',   metaKey: '_lumo_twitter_title' },
        twitter_description: { stateKey: 'twDesc',    metaKey: '_lumo_twitter_description' },
        twitter_image:       { stateKey: 'twImage',   metaKey: '_lumo_twitter_image' },
        canonical:           { stateKey: 'canonical', metaKey: '_lumo_canonical' },
        noindex:             { stateKey: 'noindex',   metaKey: '_lumo_noindex' },
    };

    function parseImportJson(raw) {
        var data;
        try { data = JSON.parse(raw.trim()); } catch (e) { return { error: 'Invalid JSON: ' + e.message }; }
        if (typeof data !== 'object' || Array.isArray(data)) return { error: 'JSON must be an object.' };

        var applied = [], warnings = [], result = {}, advisory = {};

        Object.keys(IMPORT_FIELDS).forEach(function (jsonKey) {
            if (!(jsonKey in data)) return;
            var val = data[jsonKey];
            if (jsonKey === 'noindex') val = val ? '1' : '';
            if (typeof val !== 'string') { warnings.push(jsonKey + ' skipped (not a string)'); return; }
            if (jsonKey === 'meta_title' && (val.length < 30 || val.length > 60))
                warnings.push('meta_title is ' + val.length + ' chars (ideal 30–60)');
            if (jsonKey === 'meta_description' && (val.length < 120 || val.length > 158))
                warnings.push('meta_description is ' + val.length + ' chars (ideal 120–158)');
            result[jsonKey] = val;
            applied.push(jsonKey);
        });

        ['related_keywords','suggested_headings','content_notes'].forEach(function (k) {
            if (k in data) advisory[k] = data[k];
        });

        if (!applied.length) return { error: 'No recognised SEO fields found in the JSON.' };
        return { result: result, applied: applied, warnings: warnings, advisory: advisory };
    }

    function applyImport(parsed) {
        Object.keys(parsed.result).forEach(function (jsonKey) {
            var cfg = IMPORT_FIELDS[jsonKey];
            if (!cfg) return;
            state[cfg.stateKey] = parsed.result[jsonKey];
            saveMeta(cfg.metaKey, parsed.result[jsonKey]);
        });
        renderPanel();
        setTimeout(runAnalysis, 300);
    }

    function buildImportModal() {
        var overlay = el('div', { id: 'lumos-import-overlay', style: {
            position: 'fixed', inset: '0', background: 'rgba(0,0,0,.55)',
            zIndex: '999999', display: 'flex', alignItems: 'center', justifyContent: 'center',
        }});

        var modal = el('div', { style: {
            background: '#fff', borderRadius: '8px', padding: '24px',
            width: '480px', maxWidth: '95vw', maxHeight: '90vh', overflowY: 'auto',
            boxShadow: '0 20px 60px rgba(0,0,0,.3)', fontFamily: '-apple-system,sans-serif',
        }});

        var title = el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '14px' }}, [
            el('h3', { style: { margin: 0, fontSize: '16px', fontWeight: '700' }}, '🤖 Import SEO from AI'),
            el('button', {
                style: { background: 'none', border: 'none', cursor: 'pointer', fontSize: '20px', color: '#999', lineHeight: '1' },
                on: { click: function () { document.body.removeChild(overlay); }},
            }, '✕'),
        ]);

        var desc = el('p', { style: { margin: '0 0 12px', fontSize: '13px', color: '#555', lineHeight: '1.5' }},
            'Paste the JSON generated by your AI assistant. Recognised fields are applied automatically to focus keyword, meta title, description, OG tags, and more.'
        );

        var exampleHint = el('div', { style: {
            background: '#f0f4ff', borderRadius: '4px', padding: '8px 12px', marginBottom: '10px',
            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        }}, [
            el('span', { style: { fontSize: '12px', color: '#555' }}, '📋 Need a template? Copy an example.'),
            el('button', {
                style: { background: 'none', border: 'none', color: '#2271b1', cursor: 'pointer', fontSize: '12px', fontWeight: '600', padding: '0' },
                on: { click: function () {
                    if (navigator.clipboard && lumosSEO.exampleJson) {
                        navigator.clipboard.writeText(lumosSEO.exampleJson).then(function () {
                            exampleHint.querySelector('button').textContent = '✓ Copied!';
                        });
                    }
                }},
            }, 'Copy example JSON'),
        ]);

        var textarea = el('textarea', { style: {
            width: '100%', height: '180px', fontFamily: 'monospace', fontSize: '12px',
            border: '1px solid #ddd', borderRadius: '4px', padding: '10px',
            boxSizing: 'border-box', resize: 'vertical', background: '#f9fafb',
        }, placeholder: '{\n  "focus_keyword": "...",\n  "meta_title": "...",\n  "meta_description": "..."\n}' });

        var feedback = el('div', { id: 'lumos-import-feedback', style: { marginTop: '10px' }});
        var advisory = el('div', { id: 'lumos-import-advisory', style: { marginTop: '8px' }});

        var btnRow = el('div', { style: { display: 'flex', gap: '8px', justifyContent: 'flex-end', marginTop: '16px' }});
        var cancelBtn = el('button', {
            style: { background: 'none', border: '1px solid #ddd', borderRadius: '4px', padding: '8px 16px', cursor: 'pointer', fontSize: '13px' },
            on: { click: function () { document.body.removeChild(overlay); }},
        }, 'Cancel');

        var state_parsed = null;
        var importBtn = el('button', {
            className: 'lumos-import-btn',
            style: { background: '#2271b1', color: '#fff', border: 'none', borderRadius: '4px', padding: '8px 16px', cursor: 'pointer', fontSize: '13px', fontWeight: '600' },
            on: { click: function () {
                if (state_parsed && state_parsed.result) {
                    // Second click = apply
                    applyImport(state_parsed);
                    document.body.removeChild(overlay);
                    return;
                }
                // First click = validate
                var raw = textarea.value;
                state_parsed = parseImportJson(raw);
                feedback.innerHTML = '';
                advisory.innerHTML = '';

                if (state_parsed.error) {
                    feedback.innerHTML = '<div style="background:#fff5f5;border:1px solid #f5c6cb;border-radius:4px;padding:10px 14px;font-size:12px;color:#721c24">' + escHtml(state_parsed.error) + '</div>';
                    state_parsed = null;
                    return;
                }

                // Success feedback
                var fbHtml = '<div style="background:#eafaf1;border:1px solid #a9dfbf;border-radius:4px;padding:10px 14px;font-size:12px">';
                fbHtml += '<strong style="color:#1e8449">✓ Ready to apply</strong><ul style="margin:6px 0 0;padding-left:16px">';
                state_parsed.applied.forEach(function (k) { fbHtml += '<li style="color:#1e8449">' + escHtml(k) + '</li>'; });
                state_parsed.warnings.forEach(function (w) { fbHtml += '<li style="color:#d35400">⚠ ' + escHtml(w) + '</li>'; });
                fbHtml += '</ul></div>';
                feedback.innerHTML = fbHtml;

                // Advisory fields
                var adv = state_parsed.advisory;
                if (adv.suggested_headings && adv.suggested_headings.length) {
                    var hHtml = '<div style="background:#f0f4ff;border:1px solid #c5cae9;border-radius:4px;padding:10px 14px;font-size:12px">';
                    hHtml += '<strong>💡 Suggested headings:</strong><ul style="margin:4px 0 0;padding-left:16px">';
                    adv.suggested_headings.forEach(function (h) { hHtml += '<li>' + escHtml(h) + '</li>'; });
                    hHtml += '</ul></div>';
                    advisory.innerHTML += hHtml;
                }
                if (adv.content_notes) {
                    advisory.innerHTML += '<div style="background:#fffde7;border:1px solid #fff176;border-radius:4px;padding:10px 14px;font-size:12px;margin-top:8px"><strong>📝 Content notes: </strong>' + escHtml(adv.content_notes) + '</div>';
                }
                if (adv.related_keywords && adv.related_keywords.length) {
                    advisory.innerHTML += '<div style="background:#f3e5f5;border:1px solid #ce93d8;border-radius:4px;padding:8px 14px;font-size:12px;margin-top:8px"><strong>🔑 Related keywords: </strong>' + escHtml(adv.related_keywords.join(', ')) + '</div>';
                }

                importBtn.textContent = 'Apply Import';
                importBtn.style.background = '#46b450';
            }},
        }, 'Validate & Preview');

        // Reset state if user edits textarea after validating
        textarea.addEventListener('input', function () {
            state_parsed = null;
            importBtn.textContent = 'Validate & Preview';
            importBtn.style.background = '#2271b1';
            feedback.innerHTML = '';
            advisory.innerHTML = '';
        });

        btnRow.appendChild(cancelBtn);
        btnRow.appendChild(importBtn);
        modal.appendChild(title);
        modal.appendChild(desc);
        modal.appendChild(exampleHint);
        modal.appendChild(textarea);
        modal.appendChild(feedback);
        modal.appendChild(advisory);
        modal.appendChild(btnRow);
        overlay.appendChild(modal);

        // Close on overlay click
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) document.body.removeChild(overlay);
        });

        return overlay;
    }

    function openImportModal() {
        var existing = document.getElementById('lumos-import-overlay');
        if (existing) { document.body.removeChild(existing); return; }
        document.body.appendChild(buildImportModal());
    }

    // ── FAB (floating action button) ──────────────────────────────────────
    function createFAB() {
        var fab = el('button', { id: 'lumos-seo-fab', className: 'lumos-seo-fab' }, [
            el('span', { style: { fontSize: '16px', lineHeight: '1' } }, '🔍'),
            el('span', { style: { fontSize: '11px', fontWeight: '700' } }, 'SEO'),
        ]);
        fab.addEventListener('click', function () {
            state.open = !state.open;
            panelEl.classList.toggle('open', state.open);
            if (state.open && !state.analysis && state.focusKw) {
                runAnalysis();
            }
        });
        document.body.appendChild(fab);
    }

    // ── Listen for Elementor content changes ──────────────────────────────
    function listenToElementor() {
        if (!window.elementor) return;
        elementor.channels.editor.on('change', debounce(function () {
            if (state.open && state.focusKw) debouncedAnalyze();
        }, 1500));
    }

    // ── Init ──────────────────────────────────────────────────────────────
    $(window).on('elementor:init', function () {
        createFAB();
        createPanel();
        listenToElementor();
    });

    // Fallback if elementor:init already fired
    if (window.elementor) {
        $(document).ready(function () {
            createFAB();
            createPanel();
            listenToElementor();
        });
    }

    // ── AJAX meta save handler (register in PHP) ──────────────────────────
    // The server-side handler is in class-meta-box.php under wp_ajax_lumos_seo_save_meta

})(jQuery);
