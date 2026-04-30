/* Lumos SEO — Gutenberg Sidebar */
(function ($) {
    'use strict';

    // Guard: only run inside the block editor
    if ( ! wp || ! wp.plugins || ! wp.element ) return;

    const { registerPlugin }                           = wp.plugins;
    const { createElement: el, useState, useEffect,
            useCallback, useRef, Fragment }            = wp.element;
    const { useSelect, useDispatch }                   = wp.data;
    const { __ }                                       = wp.i18n;

    // Support WP 6.6+ (moved to wp.editor) and older (wp.editPost)
    const editorPkg = ( wp.editor && wp.editor.PluginSidebar ) ? wp.editor : wp.editPost;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = editorPkg;

    const {
        Panel, PanelBody, PanelRow,
        TextControl, TextareaControl,
        Button, Spinner, Notice,
        __experimentalSpacer: Spacer,
    } = wp.components;

    const DEBOUNCE = 800;

    const PRIORITY_CFG = {
        high:   { label: 'HIGH',   color: '#c0392b', bg: '#fdecea' },
        medium: { label: 'MED',    color: '#d35400', bg: '#fef3e7' },
        low:    { label: 'LOW',    color: '#27ae60', bg: '#eafaf1' },
    };
    const PORDER = { high: 0, medium: 1, low: 2 };

    // ── helpers ──────────────────────────────────────────────────────────
    function debounce( fn, ms ) {
        let t;
        return ( ...args ) => { clearTimeout( t ); t = setTimeout( () => fn( ...args ), ms ); };
    }

    function scoreColor( s ) {
        if ( s >= 70 ) return '#46b450';
        if ( s >= 40 ) return '#ffb900';
        return '#dc3232';
    }

    function scoreEmoji( s ) {
        if ( s === null ) return '😐';
        if ( s >= 70 ) return '😊';
        if ( s >= 40 ) return '😐';
        return '😟';
    }

    function sortByPriority( checks ) {
        return [ ...checks ].sort( ( a, b ) => ( PORDER[ a.priority ] || 0 ) - ( PORDER[ b.priority ] || 0 ) );
    }

    // ── Dot indicator ────────────────────────────────────────────────────
    function Dot( { status } ) {
        const colors = { good: '#46b450', ok: '#ffb900', bad: '#dc3232' };
        return el( 'span', {
            className: 'lumos-dot lumos-dot--' + status,
            style: {
                display: 'inline-block',
                width: 10, height: 10,
                borderRadius: '50%',
                background: colors[ status ] || '#ccc',
                flexShrink: 0,
                marginTop: 5,
            },
        } );
    }

    // ── Priority badge ────────────────────────────────────────────────────
    function PriorityBadge( { priority } ) {
        const cfg = PRIORITY_CFG[ priority ] || PRIORITY_CFG.low;
        return el( 'span', {
            style: {
                fontSize: 9, fontWeight: 700, padding: '1px 5px',
                borderRadius: 3, color: cfg.color, background: cfg.bg,
                marginLeft: 6, letterSpacing: '0.04em', flexShrink: 0,
                alignSelf: 'flex-start', marginTop: 4,
            },
        }, cfg.label );
    }

    // ── Single check row ─────────────────────────────────────────────────
    function CheckRow( { check } ) {
        return el( 'div', { className: 'lumos-check-row', style: { display: 'flex', gap: 8, padding: '7px 0', borderBottom: '1px solid #f0f0f0', alignItems: 'flex-start', lineHeight: 1.45, fontSize: 13 } },
            el( Dot, { status: check.status } ),
            el( 'span', { style: { flex: 1 }, dangerouslySetInnerHTML: { __html: check.message } } ),
            el( PriorityBadge, { priority: check.priority } )
        );
    }

    // ── Grouped check list inside a PanelBody ────────────────────────────
    function CheckGroup( { title, checks, defaultOpen } ) {
        if ( ! checks.length ) return null;
        const sorted = sortByPriority( checks );
        return el( PanelBody, {
            title: title + ' (' + sorted.length + ')',
            initialOpen: defaultOpen,
            className: 'lumos-check-group',
        },
            sorted.map( c => el( CheckRow, { key: c.id, check: c } ) )
        );
    }

    // ── Score badge ──────────────────────────────────────────────────────
    function ScoreBadge( { score } ) {
        if ( score === null ) return null;
        return el( 'span', {
            style: {
                display: 'inline-block',
                background: scoreColor( score ),
                color: '#fff',
                fontWeight: 700,
                fontSize: 11,
                padding: '1px 7px',
                borderRadius: 10,
                marginLeft: 8,
                verticalAlign: 'middle',
            },
        }, score + '/100' );
    }

    // ── Snippet preview ──────────────────────────────────────────────────
    function SnippetPreview( { title, url, desc } ) {
        return el( 'div', {
            className: 'lumos-snippet',
            style: {
                border: '1px solid #ddd', borderRadius: 8, padding: '12px 14px',
                background: '#fff', fontFamily: 'Arial, sans-serif', marginBottom: 12,
            },
        },
            el( 'div', { style: { fontSize: 18, color: '#1a0dab', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, title || '(no title)' ),
            el( 'div', { style: { fontSize: 12, color: '#006621', marginTop: 2, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } }, url ),
            el( 'div', { style: { fontSize: 13, color: '#545454', marginTop: 4, lineHeight: 1.5 } }, desc || 'No meta description set.' )
        );
    }

    // ── Section title with emoji + score ─────────────────────────────────
    function SectionTitle( { label, score } ) {
        return el( 'span', { style: { display: 'flex', alignItems: 'center', gap: 6 } },
            el( 'span', { style: { fontSize: 15 } }, scoreEmoji( score ) ),
            label,
            el( ScoreBadge, { score } )
        );
    }

    // ── Character bar ────────────────────────────────────────────────────
    function CharBar( { value, min, max } ) {
        const len = ( value || '' ).length;
        let pct, color;
        if ( len === 0 )      { pct = 0;   color = '#dc3232'; }
        else if ( len < min ) { pct = Math.round( len / min * 50 ); color = '#ffb900'; }
        else if ( len <= max ){ pct = Math.round( 50 + ( len - min ) / ( max - min ) * 50 ); color = '#46b450'; }
        else                  { pct = 100; color = '#ffb900'; }

        return el( 'div', { style: { height: 4, background: '#e0e0e0', borderRadius: 2, overflow: 'hidden', marginTop: 4 } },
            el( 'div', { style: { height: '100%', width: pct + '%', background: color, borderRadius: 2, transition: 'width .25s' } } )
        );
    }

    // ── AI Import Modal ───────────────────────────────────────────────────
    const IMPORT_FIELDS = {
        focus_keyword:       '_lumos_focus_keyword',
        meta_title:          '_lumos_meta_title',
        meta_description:    '_lumos_meta_description',
        og_title:            '_lumos_og_title',
        og_description:      '_lumos_og_description',
        og_image:            '_lumos_og_image',
        og_url:              '_lumos_og_url',
        og_type:             '_lumos_og_type',
        og_site_name:        '_lumos_og_site_name',
        og_locale:           '_lumos_og_locale',
        twitter_card:        '_lumos_twitter_card',
        twitter_title:       '_lumos_twitter_title',
        twitter_description: '_lumos_twitter_description',
        twitter_image:       '_lumos_twitter_image',
        canonical:           '_lumos_canonical',
        noindex:             '_lumos_noindex',
        use_service_schema:  '_lumos_service_schema_enabled',
        service_schema:      '_lumos_service_schema_json',
    };

    function parseImportJson( raw ) {
        let data;
        try {
            data = JSON.parse( raw.trim() );
        } catch ( e ) {
            return { error: 'Invalid JSON: ' + e.message };
        }
        if ( typeof data !== 'object' || Array.isArray( data ) ) {
            return { error: 'JSON must be an object (not an array).' };
        }

        const applied  = [];
        const skipped  = [];
        const warnings = [];
        const result   = {};

        Object.entries( IMPORT_FIELDS ).forEach( ( [ jsonKey, metaKey ] ) => {
            if ( ! ( jsonKey in data ) ) { skipped.push( jsonKey ); return; }
            let val = data[ jsonKey ];

            // Coerce noindex boolean → string
            if ( jsonKey === 'noindex' ) val = val ? '1' : '';

            if ( typeof val !== 'string' ) {
                warnings.push( jsonKey + ' was skipped (expected string, got ' + typeof val + ')' );
                return;
            }

            // Soft length warnings
            if ( jsonKey === 'meta_title' && ( val.length < 30 || val.length > 60 ) ) {
                warnings.push( 'meta_title is ' + val.length + ' chars (ideal 30–60)' );
            }
            if ( jsonKey === 'meta_description' && ( val.length < 120 || val.length > 158 ) ) {
                warnings.push( 'meta_description is ' + val.length + ' chars (ideal 120–158)' );
            }

            result[ metaKey ] = val;
            applied.push( jsonKey );
        } );

        // Read-only advisory fields (not saved to meta, surfaced as info)
        const advisory = {};
        [ 'related_keywords', 'suggested_headings', 'content_notes' ].forEach( k => {
            if ( k in data ) advisory[ k ] = data[ k ];
        } );

        if ( ! applied.length ) return { error: 'No recognised SEO fields found in the JSON.' };
        return { result, applied, skipped, warnings, advisory };
    }

    function ImportModal( { onApply, onClose } ) {
        const [ raw,      setRaw      ] = useState( '' );
        const [ feedback, setFeedback ] = useState( null );
        const [ copied,   setCopied   ] = useState( false );

        const copyExample = () => {
            navigator.clipboard?.writeText( lumosSEO.exampleJson || '' )
                .then( () => { setCopied( true ); setTimeout( () => setCopied( false ), 2000 ); } );
        };

        const handleImport = () => {
            const parsed = parseImportJson( raw );
            if ( parsed.error ) {
                setFeedback( { type: 'error', parsed } );
                return;
            }
            setFeedback( { type: 'success', parsed } );
            onApply( parsed );
        };

        const Modal = wp.components.Modal;

        return el( Modal, {
            title: '🤖 Import SEO from AI',
            onRequestClose: onClose,
            style: { maxWidth: 520 },
        },
            el( 'p', { style: { margin: '0 0 10px', fontSize: 13, color: '#555' } },
                'Paste the JSON generated by your AI assistant. Recognised fields are applied automatically.'
            ),

            // Example JSON hint
            el( 'div', { style: { background: '#f0f4ff', borderRadius: 4, padding: '8px 12px', marginBottom: 10, display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                el( 'span', { style: { fontSize: 12, color: '#555' } }, '📋 Need an example? Copy the template below.' ),
                el( Button, { variant: 'link', style: { fontSize: 12 }, onClick: copyExample },
                    copied ? '✓ Copied!' : 'Copy example JSON'
                )
            ),

            // JSON textarea
            el( 'textarea', {
                style: {
                    width: '100%', height: 200, fontFamily: 'monospace', fontSize: 12,
                    border: '1px solid #ddd', borderRadius: 4, padding: 10,
                    boxSizing: 'border-box', resize: 'vertical',
                    background: feedback?.type === 'error' ? '#fff5f5' : '#f9fafb',
                },
                placeholder: '{\n  "focus_keyword": "...",\n  "meta_title": "...",\n  "meta_description": "..."\n}',
                value: raw,
                onChange: e => { setRaw( e.target.value ); setFeedback( null ); },
                spellCheck: false,
            } ),

            // Feedback
            feedback && el( 'div', { style: { marginTop: 10 } },
                feedback.type === 'error'
                    ? el( Notice, { status: 'error', isDismissible: false },
                        feedback.parsed.error
                    )
                    : el( 'div', { style: { background: '#eafaf1', border: '1px solid #a9dfbf', borderRadius: 4, padding: '10px 14px', fontSize: 12 } },
                        el( 'strong', { style: { color: '#1e8449' } }, '✓ Ready to apply' ),
                        el( 'ul', { style: { margin: '6px 0 0', paddingLeft: 16 } },
                            feedback.parsed.applied.map( k =>
                                el( 'li', { key: k, style: { color: '#1e8449' } }, k )
                            ),
                            feedback.parsed.warnings.map( ( w, i ) =>
                                el( 'li', { key: 'w' + i, style: { color: '#d35400' } }, '⚠ ' + w )
                            )
                        ),
                        feedback.parsed.advisory && Object.keys( feedback.parsed.advisory ).length > 0 &&
                            el( 'div', { style: { marginTop: 8, paddingTop: 8, borderTop: '1px solid #a9dfbf', color: '#555' } },
                                el( 'strong', null, 'Advisory (not saved): ' ),
                                el( 'span', null, Object.keys( feedback.parsed.advisory ).join( ', ' ) )
                            )
                    )
            ),

            // Advisory fields preview
            feedback?.parsed?.advisory?.suggested_headings &&
                el( 'div', { style: { marginTop: 10, background: '#f0f4ff', border: '1px solid #c5cae9', borderRadius: 4, padding: '10px 14px', fontSize: 12 } },
                    el( 'strong', null, '💡 Suggested headings:' ),
                    el( 'ul', { style: { margin: '4px 0 0', paddingLeft: 16 } },
                        feedback.parsed.advisory.suggested_headings.map( ( h, i ) =>
                            el( 'li', { key: i }, h )
                        )
                    )
                ),

            feedback?.parsed?.advisory?.content_notes &&
                el( 'div', { style: { marginTop: 10, background: '#fffde7', border: '1px solid #fff176', borderRadius: 4, padding: '10px 14px', fontSize: 12 } },
                    el( 'strong', null, '📝 Content notes: ' ),
                    feedback.parsed.advisory.content_notes
                ),

            // Action row
            el( 'div', { style: { display: 'flex', gap: 8, marginTop: 16, justifyContent: 'flex-end' } },
                el( Button, { variant: 'tertiary', onClick: onClose }, 'Cancel' ),
                el( Button, {
                    variant: 'primary',
                    onClick: handleImport,
                    disabled: ! raw.trim(),
                }, feedback?.type === 'success' ? 'Apply Import' : 'Validate & Preview' )
            )
        );
    }

    // ── Main sidebar component ────────────────────────────────────────────
    function LumoSidebar() {
        const postId      = useSelect( s => s( 'core/editor' ).getCurrentPostId() );
        const postTitle   = useSelect( s => s( 'core/editor' ).getEditedPostAttribute( 'title' ) || '' );
        const postContent = useSelect( s => s( 'core/editor' ).getEditedPostAttribute( 'content' ) || '' );
        const postLink    = useSelect( s => ( s( 'core/editor' ).getCurrentPost() || {} ).link || '' );
        const meta        = useSelect( s => s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} );
        const { editPost } = useDispatch( 'core/editor' );

        const [ analysis,    setAnalysis    ] = useState( null );
        const [ loading,     setLoading     ] = useState( false );
        const [ error,       setError       ] = useState( null );
        const [ showImport,  setShowImport  ] = useState( false );

        const focusKw   = meta._lumos_focus_keyword       || '';
        const metaTitle = meta._lumos_meta_title          || '';
        const metaDesc  = meta._lumos_meta_description    || '';
        // OG
        const ogTitle   = meta._lumos_og_title            || '';
        const ogDesc    = meta._lumos_og_description      || '';
        const ogImage   = meta._lumos_og_image            || '';
        const ogUrl     = meta._lumos_og_url              || '';
        const ogType    = meta._lumos_og_type             || '';
        const ogSite    = meta._lumos_og_site_name        || '';
        const ogLocale  = meta._lumos_og_locale           || '';
        // Twitter
        const twCard    = meta._lumos_twitter_card        || '';
        const twTitle   = meta._lumos_twitter_title       || '';
        const twDesc    = meta._lumos_twitter_description || '';
        const twImage   = meta._lumos_twitter_image       || '';
        // Advanced
        const noindex   = meta._lumos_noindex             || '';
        const serviceSchemaEnabled = meta._lumos_service_schema_enabled || '';
        const serviceSchemaJson    = meta._lumos_service_schema_json || '';

        const setMeta = useCallback( ( key, val ) => {
            editPost( { meta: { [ key ]: val } } );
        }, [ editPost ] );

        // ── AJAX analysis ─────────────────────────────────────────────────
        const analyze = useCallback( () => {
            if ( ! postId ) return;
            setLoading( true );
            setError( null );
            $.post( lumosSEO.ajaxurl, {
                action:           'lumos_seo_analyze',
                nonce:            lumosSEO.nonce,
                post_id:          postId,
                focus_keyword:    focusKw,
                meta_title:       metaTitle || postTitle,
                meta_description: metaDesc,
                content:          postContent,
                service_schema_enabled: serviceSchemaEnabled,
                service_schema_json:    serviceSchemaJson,
            } )
            .done( function ( res ) {
                if ( res.success ) setAnalysis( res.data );
                else setError( 'Analysis failed. Please try again.' );
            } )
            .fail( function () {
                setError( 'Connection error. Please try again.' );
            } )
            .always( function () {
                setLoading( false );
            } );
        }, [ postId, focusKw, metaTitle, metaDesc, postTitle, postContent, serviceSchemaEnabled, serviceSchemaJson ] );

        // Debounced auto-analysis when key fields change
        const debouncedRef = useRef( null );
        useEffect( () => {
            debouncedRef.current = debounce( analyze, DEBOUNCE );
        }, [ analyze ] );

        useEffect( () => {
            if ( focusKw && debouncedRef.current ) debouncedRef.current();
        }, [ focusKw, postContent, metaTitle, metaDesc, serviceSchemaEnabled, serviceSchemaJson ] );

        // ── Group checks ──────────────────────────────────────────────────
        const group = ( checks ) => ( {
            problems:     ( checks || [] ).filter( c => c.status === 'bad' ),
            improvements: ( checks || [] ).filter( c => c.status === 'ok' ),
            good:         ( checks || [] ).filter( c => c.status === 'good' ),
        } );

        const seoG  = group( analysis && analysis.seo );
        const readG = group( analysis && analysis.read );

        const previewTitle = metaTitle || postTitle;
        const previewUrl   = postLink || ( lumosSEO.siteUrl + '/' );

        // ── Apply imported data ───────────────────────────────────────────
        const handleImportApply = useCallback( ( { result } ) => {
            // Write each imported meta key into the editor state
            const merged = Object.assign( {}, meta );
            Object.entries( result ).forEach( ( [ k, v ] ) => { merged[ k ] = v; } );
            editPost( { meta: merged } );
            setShowImport( false );
            // Trigger fresh analysis after a short delay so state has settled
            setTimeout( () => analyze(), 300 );
        }, [ editPost, meta, analyze ] );

        // ── Render ────────────────────────────────────────────────────────
        return el( 'div', { className: 'lumos-sidebar' },

            // AI Import Modal (rendered outside sidebar panels so it overlays everything)
            showImport && el( ImportModal, {
                onApply: handleImportApply,
                onClose: () => setShowImport( false ),
            } ),

            // ── Import from AI banner ─────────────────────────────────────
            el( 'div', {
                style: {
                    margin: '0 0 0', padding: '10px 16px',
                    background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                },
            },
                el( 'span', { style: { color: '#fff', fontSize: 12, fontWeight: 600 } }, '🤖 AI-generated SEO' ),
                el( Button, {
                    variant: 'secondary',
                    style: { color: '#fff', borderColor: 'rgba(255,255,255,.6)', fontSize: 11, padding: '3px 10px', height: 'auto' },
                    onClick: () => setShowImport( true ),
                }, 'Import JSON' )
            ),

            // ── Focus keyphrase ───────────────────────────────────────────
            el( PanelBody, { title: 'Focus keyphrase', initialOpen: true, className: 'lumos-panel' },
                el( TextControl, {
                    label:       '',
                    value:       focusKw,
                    placeholder: 'e.g. best running shoes',
                    onChange:    v => setMeta( '_lumos_focus_keyword', v ),
                    __nextHasNoMarginBottom: true,
                } ),
                el( 'div', { style: { marginTop: 8 } },
                    el( Button, {
                        variant: 'secondary',
                        isBusy:  loading,
                        onClick: analyze,
                        style:   { width: '100%', justifyContent: 'center' },
                    }, loading ? el( Fragment, null, el( Spinner ), ' Analyzing…' ) : 'Analyze now' )
                ),
                error && el( Notice, { status: 'error', isDismissible: false, style: { marginTop: 8 } }, error )
            ),

            // ── SEO Analysis ──────────────────────────────────────────────
            el( PanelBody, {
                title:       el( SectionTitle, { label: 'SEO analysis', score: analysis ? analysis.seo_score : null } ),
                initialOpen: true,
                className:   'lumos-panel',
            },
                ! analysis && ! loading && el( 'p', { style: { color: '#999', fontSize: 13, fontStyle: 'italic', margin: 0 } },
                    'Set a focus keyphrase above and click Analyze to start.'
                ),
                loading && el( 'div', { style: { textAlign: 'center', padding: 16 } }, el( Spinner ) ),
                analysis && el( Fragment, null,
                    el( CheckGroup, { title: 'Problems',     checks: seoG.problems,     defaultOpen: true } ),
                    el( CheckGroup, { title: 'Improvements', checks: seoG.improvements, defaultOpen: true } ),
                    el( CheckGroup, { title: 'Good results', checks: seoG.good,         defaultOpen: false } )
                )
            ),

            // ── Readability ───────────────────────────────────────────────
            el( PanelBody, {
                title:       el( SectionTitle, { label: 'Readability analysis', score: analysis ? analysis.read_score : null } ),
                initialOpen: false,
                className:   'lumos-panel',
            },
                ! analysis && ! loading && el( 'p', { style: { color: '#999', fontSize: 13, fontStyle: 'italic', margin: 0 } },
                    'Run analysis to see readability feedback.'
                ),
                loading && el( 'div', { style: { textAlign: 'center', padding: 16 } }, el( Spinner ) ),
                analysis && el( Fragment, null,
                    el( CheckGroup, { title: 'Problems',     checks: readG.problems,     defaultOpen: true } ),
                    el( CheckGroup, { title: 'Improvements', checks: readG.improvements, defaultOpen: true } ),
                    el( CheckGroup, { title: 'Good results', checks: readG.good,         defaultOpen: false } )
                )
            ),

            // ── Snippet preview ───────────────────────────────────────────
            el( PanelBody, { title: 'Snippet preview', initialOpen: false, className: 'lumos-panel' },
                el( SnippetPreview, { title: previewTitle, url: previewUrl, desc: metaDesc } ),

                el( TextControl, {
                    label:    'SEO Title',
                    value:    metaTitle,
                    placeholder: postTitle,
                    onChange: v => setMeta( '_lumos_meta_title', v ),
                    help:     ( ( metaTitle || postTitle ).length ) + ' / 60 chars',
                    __nextHasNoMarginBottom: true,
                } ),
                el( CharBar, { value: metaTitle || postTitle, min: 30, max: 60 } ),

                el( 'div', { style: { height: 12 } } ),

                el( TextareaControl, {
                    label:    'Meta Description',
                    value:    metaDesc,
                    onChange: v => setMeta( '_lumos_meta_description', v ),
                    help:     metaDesc.length + ' / 158 chars',
                    rows:     3,
                    __nextHasNoMarginBottom: true,
                } ),
                el( CharBar, { value: metaDesc, min: 120, max: 158 } )
            ),

            // ── Open Graph ────────────────────────────────────────────────
            el( PanelBody, { title: 'Open Graph (Facebook · LinkedIn · WhatsApp)', initialOpen: false, className: 'lumos-panel' },
                el( 'p', { style: { fontSize: 11, color: '#999', margin: '0 0 10px', lineHeight: 1.5 } },
                    'Controls how your page appears when shared on social platforms.'
                ),
                el( TextControl, { label: 'og:title', value: ogTitle, placeholder: previewTitle || 'Defaults to SEO title',
                    onChange: v => setMeta( '_lumos_og_title', v ), __nextHasNoMarginBottom: true } ),
                el( 'div', { style: { height: 10 } } ),
                el( TextareaControl, { label: 'og:description', value: ogDesc, placeholder: 'Defaults to meta description',
                    onChange: v => setMeta( '_lumos_og_description', v ), rows: 3, __nextHasNoMarginBottom: true } ),
                el( 'div', { style: { height: 10 } } ),
                el( TextControl, { label: 'og:image (URL)', value: ogImage,
                    placeholder: 'https://yoursite.com/image.jpg — ideal 1200×630px',
                    onChange: v => setMeta( '_lumos_og_image', v ), __nextHasNoMarginBottom: true } ),
                ogImage && el( 'img', { src: ogImage, alt: '', style: { width: '100%', marginTop: 8, borderRadius: 4, border: '1px solid #ddd' } } ),
                el( 'div', { style: { height: 10 } } ),
                el( TextControl, { label: 'og:url', value: ogUrl, placeholder: previewUrl || 'Defaults to page URL',
                    onChange: v => setMeta( '_lumos_og_url', v ), __nextHasNoMarginBottom: true } ),
                el( 'div', { style: { height: 10 } } ),
                el( 'div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 } },
                    el( TextControl, { label: 'og:type', value: ogType, placeholder: 'article',
                        onChange: v => setMeta( '_lumos_og_type', v ), __nextHasNoMarginBottom: true } ),
                    el( TextControl, { label: 'og:locale', value: ogLocale, placeholder: 'en_US',
                        onChange: v => setMeta( '_lumos_og_locale', v ), __nextHasNoMarginBottom: true } )
                ),
                el( 'div', { style: { height: 10 } } ),
                el( TextControl, { label: 'og:site_name', value: ogSite,
                    placeholder: lumosSEO.siteName || 'Your Brand',
                    onChange: v => setMeta( '_lumos_og_site_name', v ), __nextHasNoMarginBottom: true } )
            ),

            // ── Twitter / X ───────────────────────────────────────────────
            el( PanelBody, { title: 'Twitter / X', initialOpen: false, className: 'lumos-panel' },
                el( 'p', { style: { fontSize: 11, color: '#999', margin: '0 0 10px', lineHeight: 1.5 } },
                    'Leave blank to fall back to Open Graph values.'
                ),
                el( 'div', { style: { marginBottom: 10 } },
                    el( 'label', { style: { fontSize: 11, fontWeight: 600, color: '#444', display: 'block', marginBottom: 4 } }, 'twitter:card' ),
                    el( 'select', {
                        value: twCard || 'summary_large_image',
                        onChange: e => setMeta( '_lumos_twitter_card', e.target.value ),
                        style: { width: '100%', padding: '6px 8px', border: '1px solid #ddd', borderRadius: 4, fontSize: 13 },
                    },
                        el( 'option', { value: 'summary_large_image' }, 'summary_large_image (large image)' ),
                        el( 'option', { value: 'summary' },             'summary (small image)' ),
                        el( 'option', { value: 'app' },                 'app' ),
                        el( 'option', { value: 'player' },              'player' )
                    )
                ),
                el( TextControl, { label: 'twitter:title', value: twTitle, placeholder: ogTitle || 'Falls back to og:title',
                    onChange: v => setMeta( '_lumos_twitter_title', v ), __nextHasNoMarginBottom: true } ),
                el( 'div', { style: { height: 10 } } ),
                el( TextareaControl, { label: 'twitter:description', value: twDesc, placeholder: 'Falls back to og:description',
                    onChange: v => setMeta( '_lumos_twitter_description', v ), rows: 2, __nextHasNoMarginBottom: true } ),
                el( 'div', { style: { height: 10 } } ),
                el( TextControl, { label: 'twitter:image (URL)', value: twImage, placeholder: 'Falls back to og:image',
                    onChange: v => setMeta( '_lumos_twitter_image', v ), __nextHasNoMarginBottom: true } ),
                twImage && el( 'img', { src: twImage, alt: '', style: { width: '100%', marginTop: 8, borderRadius: 4, border: '1px solid #ddd' } } )
            ),

            // ── Advanced ──────────────────────────────────────────────────
            el( PanelBody, { title: 'Advanced', initialOpen: false, className: 'lumos-panel' },
                el( TextControl, { label: 'Canonical URL', value: meta._lumos_canonical || '', placeholder: previewUrl || 'Defaults to page URL',
                    onChange: v => setMeta( '_lumos_canonical', v ), __nextHasNoMarginBottom: true,
                    help: 'Leave blank to use the page URL.' } ),
                el( 'div', { style: { height: 10 } } ),
                el( 'label', { style: { display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 13 } },
                    el( 'input', {
                        type:     'checkbox',
                        checked:  noindex === '1',
                        onChange: e => setMeta( '_lumos_noindex', e.target.checked ? '1' : '' ),
                    } ),
                    'No index, No follow'
                ),
                el( 'p', { style: { fontSize: 12, color: '#999', marginTop: 6 } },
                    'Prevents this page from appearing in search results.'
                )
            ),

            // ── Service Schema ─────────────────────────────────────────────
            el( PanelBody, { title: 'Service Schema', initialOpen: false, className: 'lumos-panel' },
                el( 'label', { style: { display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer', fontSize: 13 } },
                    el( 'input', {
                        type: 'checkbox',
                        checked: serviceSchemaEnabled === '1',
                        onChange: e => setMeta( '_lumos_service_schema_enabled', e.target.checked ? '1' : '' ),
                    } ),
                    'Output Service schema on this page'
                ),
                el( 'p', { style: { fontSize: 12, color: '#999', marginTop: 6 } },
                    'Use only on service pages. Paste JSON-LD object without <script> tags.'
                ),
                el( TextareaControl, {
                    label: 'Service JSON-LD',
                    value: serviceSchemaJson,
                    onChange: v => setMeta( '_lumos_service_schema_json', v ),
                    rows: 10,
                    __nextHasNoMarginBottom: true,
                } ),
                el( 'div', { style: { display: 'flex', justifyContent: 'flex-end', marginTop: 8 } },
                    el( Button, {
                        variant: 'secondary',
                        onClick: () => {
                            const prompt = [
                                'Improve this Service schema JSON-LD for SEO and rich results.',
                                'Return ONLY valid JSON object (no markdown, no <script> tags).',
                                'Keep @type as "Service" and preserve factual details.',
                                '',
                                serviceSchemaJson || '{"@context":"https://schema.org","@type":"Service","name":"..."}',
                            ].join( '\n' );
                            navigator.clipboard?.writeText( prompt );
                        },
                    }, 'Copy GPT prompt' )
                )
            )
        );
    }

    // ── Register plugin ───────────────────────────────────────────────────
    registerPlugin( 'lumos-seo', {
        icon: 'chart-line',
        render: function () {
            return el( Fragment, null,
                el( PluginSidebarMoreMenuItem, { target: 'lumos-seo-sidebar', icon: 'chart-line' }, 'Lumos SEO' ),
                el( PluginSidebar, { name: 'lumos-seo-sidebar', title: 'Lumos SEO', icon: 'chart-line' },
                    el( LumoSidebar )
                )
            );
        },
    } );

} )( jQuery );
