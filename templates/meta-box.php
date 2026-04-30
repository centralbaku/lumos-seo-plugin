<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Pull all saved values
$f = [];
foreach ( array_keys( Lumos_SEO_Meta_Box::FIELD_MAP ) as $key ) {
    $f[ $key ] = get_post_meta( $post->ID, $key, true );
}
$focus_kw   = $f['_lumos_focus_keyword'];
$meta_title = $f['_lumos_meta_title'] ?: get_the_title( $post->ID );
$meta_desc  = $f['_lumos_meta_description'];
$noindex    = $f['_lumos_noindex'];
?>

<div class="lumos-mb" id="lumos-meta-box">

    <?php wp_nonce_field( 'lumos_seo_save', 'lumos_seo_nonce' ); ?>

    <!-- ── Import from AI banner ───────────────────────────────────────── -->
    <div class="lumos-import-banner">
        <span>🤖 <strong>Import SEO from AI</strong> — paste JSON to fill all fields automatically</span>
        <button type="button" class="lumos-btn-import" id="lumos-open-import">
            Import JSON
        </button>
    </div>

    <!-- ── Tabs ───────────────────────────────────────────────────────── -->
    <div class="lumos-mb-tabs">
        <button type="button" class="lumos-mb-tab active" data-tab="seo">SEO</button>
        <button type="button" class="lumos-mb-tab" data-tab="opengraph">Open Graph</button>
        <button type="button" class="lumos-mb-tab" data-tab="twitter">Twitter / X</button>
        <button type="button" class="lumos-mb-tab" data-tab="schema">Service Schema</button>
        <button type="button" class="lumos-mb-tab" data-tab="advanced">Advanced</button>
    </div>

    <!-- ══════════════════════ SEO TAB ══════════════════════════════════ -->
    <div class="lumos-mb-panel active" id="lumos-tab-seo">

        <!-- Google snippet preview -->
        <div class="lumos-snippet-box">
            <div class="lumos-snippet-title" id="lumos-preview-title"><?php echo esc_html( $meta_title ); ?></div>
            <div class="lumos-snippet-url"><?php echo esc_url( get_permalink( $post->ID ) ); ?></div>
            <div class="lumos-snippet-desc" id="lumos-preview-desc"><?php echo $meta_desc ? esc_html( $meta_desc ) : '<span style="color:#aaa">No meta description set.</span>'; ?></div>
        </div>

        <div class="lumos-mb-grid">

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_focus_keyword">Focus Keyword</label>
                <input type="text" id="lm_focus_keyword" name="focus_keyword"
                       value="<?php echo esc_attr( $focus_kw ); ?>"
                       placeholder="e.g. best running shoes" class="lumos-input">
                <p class="lumos-hint">The keyword you want this page to rank for.</p>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <div class="lumos-field-header">
                    <label for="lm_meta_title">SEO Title</label>
                    <span class="lumos-counter" id="lm_title_counter">0 / 60</span>
                </div>
                <input type="text" id="lm_meta_title" name="meta_title"
                       value="<?php echo esc_attr( $meta_title ); ?>"
                       class="lumos-input">
                <div class="lumos-progress-wrap"><div class="lumos-progress" id="lm_title_bar"></div></div>
                <p class="lumos-hint">30–60 characters recommended.</p>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <div class="lumos-field-header">
                    <label for="lm_meta_description">Meta Description</label>
                    <span class="lumos-counter" id="lm_desc_counter">0 / 158</span>
                </div>
                <textarea id="lm_meta_description" name="meta_description" rows="3"
                          class="lumos-textarea"><?php echo esc_textarea( $meta_desc ); ?></textarea>
                <div class="lumos-progress-wrap"><div class="lumos-progress" id="lm_desc_bar"></div></div>
                <p class="lumos-hint">120–158 characters recommended.</p>
            </div>

        </div>

        <!-- Analyze button + results -->
        <div class="lumos-analyze-row">
            <button type="button" id="lumos-analyze-btn" class="lumos-btn-primary">
                Analyze SEO
            </button>
            <span id="lumos-analyzing" style="display:none;color:#999;font-size:12px">Analyzing…</span>
        </div>

        <div id="lumos-results" style="display:none;margin-top:12px">
            <div class="lumos-score-header">
                <div class="lumos-donut-wrap">
                    <svg class="lumos-donut" viewBox="0 0 36 36">
                        <path class="lumos-donut-bg" d="M18 2.0845 a15.9155 15.9155 0 0 1 0 31.831 a15.9155 15.9155 0 0 1 0-31.831"/>
                        <path class="lumos-donut-fill" id="lumos-arc" stroke-dasharray="0,100"
                              d="M18 2.0845 a15.9155 15.9155 0 0 1 0 31.831 a15.9155 15.9155 0 0 1 0-31.831"/>
                    </svg>
                    <span class="lumos-score-num" id="lumos-score-num">—</span>
                </div>
                <div class="lumos-score-meta">
                    <div id="lumos-score-label" style="font-weight:600;font-size:14px"></div>
                    <div style="font-size:12px;color:#888">Overall SEO Score</div>
                    <button type="button" id="lumos-copy-report" class="lumos-btn-copy-report" style="display:none">
                        📋 Copy report for GPT
                    </button>
                </div>
            </div>
            <div id="lumos-fix-summary" style="display:none;margin-bottom:10px"></div>
            <div id="lumos-checks-seo"></div>
            <div id="lumos-checks-read" style="margin-top:10px"></div>
        </div>

    </div><!-- /SEO -->

    <!-- ══════════════════════ OPEN GRAPH TAB ════════════════════════════ -->
    <div class="lumos-mb-panel" id="lumos-tab-opengraph">

        <div class="lumos-og-notice">
            👉 These define your preview on <strong>Facebook, LinkedIn, WhatsApp</strong>
        </div>

        <div class="lumos-mb-grid">

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_og_title">og:title</label>
                <input type="text" id="lm_og_title" name="og_title"
                       value="<?php echo esc_attr( $f['_lumos_og_title'] ); ?>"
                       placeholder="Defaults to SEO title" class="lumos-input">
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_og_description">og:description</label>
                <textarea id="lm_og_description" name="og_description" rows="2"
                          class="lumos-textarea"
                          placeholder="Defaults to meta description"><?php echo esc_textarea( $f['_lumos_og_description'] ); ?></textarea>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_og_image">og:image <span class="lumos-badge">Ideal: 1200×630 px</span></label>
                <div class="lumos-media-row">
                    <input type="url" id="lm_og_image" name="og_image"
                           value="<?php echo esc_url( $f['_lumos_og_image'] ); ?>"
                           placeholder="https://yoursite.com/image.jpg" class="lumos-input">
                    <button type="button" class="lumos-btn-media" data-target="#lm_og_image" data-preview="#lm_og_image_preview">
                        Select image
                    </button>
                    <?php if ( $f['_lumos_og_image'] ) : ?>
                        <button type="button" class="lumos-btn-media-remove" data-target="#lm_og_image" data-preview="#lm_og_image_preview" title="Remove">✕</button>
                    <?php else : ?>
                        <button type="button" class="lumos-btn-media-remove" data-target="#lm_og_image" data-preview="#lm_og_image_preview" title="Remove" style="display:none">✕</button>
                    <?php endif; ?>
                </div>
                <?php if ( $f['_lumos_og_image'] ) : ?>
                    <img id="lm_og_image_preview" src="<?php echo esc_url( $f['_lumos_og_image'] ); ?>"
                         alt="" class="lumos-img-preview">
                <?php else : ?>
                    <img id="lm_og_image_preview" src="" alt="" class="lumos-img-preview" style="display:none">
                <?php endif; ?>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_og_url">og:url</label>
                <input type="url" id="lm_og_url" name="og_url"
                       value="<?php echo esc_url( $f['_lumos_og_url'] ); ?>"
                       placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="lumos-input">
            </div>

            <div class="lumos-mb-field">
                <label for="lm_og_type">og:type</label>
                <select id="lm_og_type" name="og_type" class="lumos-select">
                    <?php foreach ( [ 'article','website','product','book','profile','video.movie','music.song' ] as $t ) : ?>
                        <option value="<?php echo esc_attr( $t ); ?>"
                            <?php selected( $f['_lumos_og_type'] ?: ( $post->post_type === 'post' ? 'article' : 'website' ), $t ); ?>>
                            <?php echo esc_html( $t ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lumos-mb-field">
                <label for="lm_og_locale">og:locale</label>
                <input type="text" id="lm_og_locale" name="og_locale"
                       value="<?php echo esc_attr( $f['_lumos_og_locale'] ); ?>"
                       placeholder="en_US" class="lumos-input">
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_og_site_name">og:site_name</label>
                <input type="text" id="lm_og_site_name" name="og_site_name"
                       value="<?php echo esc_attr( $f['_lumos_og_site_name'] ); ?>"
                       placeholder="<?php echo esc_attr( get_option( 'lumos_seo_site_name', get_bloginfo( 'name' ) ) ); ?>"
                       class="lumos-input">
            </div>

        </div>

        <!-- Social preview card -->
        <div class="lumos-og-preview-wrap">
            <div class="lumos-og-card" id="lumos-og-card">
                <div class="lumos-og-card-img" id="lumos-og-card-img"
                     <?php if ( $f['_lumos_og_image'] ) echo 'style="background-image:url(' . esc_url( $f['_lumos_og_image'] ) . ')"'; ?>></div>
                <div class="lumos-og-card-body">
                    <div class="lumos-og-card-domain"><?php echo esc_html( parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
                    <div class="lumos-og-card-title" id="lumos-og-card-title">
                        <?php echo esc_html( $f['_lumos_og_title'] ?: $meta_title ); ?>
                    </div>
                    <div class="lumos-og-card-desc" id="lumos-og-card-desc">
                        <?php echo esc_html( $f['_lumos_og_description'] ?: $meta_desc ); ?>
                    </div>
                </div>
            </div>
            <p class="lumos-hint" style="text-align:center;margin-top:6px">Live Facebook / LinkedIn preview</p>
        </div>

    </div><!-- /Open Graph -->

    <!-- ══════════════════════ TWITTER TAB ══════════════════════════════ -->
    <div class="lumos-mb-panel" id="lumos-tab-twitter">

        <div class="lumos-og-notice">
            👉 For better control + platform compatibility on <strong>Twitter / X</strong>
        </div>

        <div class="lumos-mb-grid">

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_twitter_card">twitter:card</label>
                <select id="lm_twitter_card" name="twitter_card" class="lumos-select">
                    <?php foreach ( [
                        'summary_large_image' => 'summary_large_image — large image card',
                        'summary'             => 'summary — small image card',
                        'app'                 => 'app',
                        'player'              => 'player',
                    ] as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"
                            <?php selected( $f['_lumos_twitter_card'] ?: 'summary_large_image', $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_twitter_title">twitter:title</label>
                <input type="text" id="lm_twitter_title" name="twitter_title"
                       value="<?php echo esc_attr( $f['_lumos_twitter_title'] ); ?>"
                       placeholder="Falls back to og:title" class="lumos-input">
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_twitter_description">twitter:description</label>
                <textarea id="lm_twitter_description" name="twitter_description" rows="2"
                          class="lumos-textarea"
                          placeholder="Falls back to og:description"><?php echo esc_textarea( $f['_lumos_twitter_description'] ); ?></textarea>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_twitter_image">twitter:image <span class="lumos-badge">Ideal: 1200×600 px</span></label>
                <div class="lumos-media-row">
                    <input type="url" id="lm_twitter_image" name="twitter_image"
                           value="<?php echo esc_url( $f['_lumos_twitter_image'] ); ?>"
                           placeholder="Falls back to og:image" class="lumos-input">
                    <button type="button" class="lumos-btn-media" data-target="#lm_twitter_image" data-preview="#lm_twitter_image_preview">
                        Select image
                    </button>
                    <?php if ( $f['_lumos_twitter_image'] ) : ?>
                        <button type="button" class="lumos-btn-media-remove" data-target="#lm_twitter_image" data-preview="#lm_twitter_image_preview" title="Remove">✕</button>
                    <?php else : ?>
                        <button type="button" class="lumos-btn-media-remove" data-target="#lm_twitter_image" data-preview="#lm_twitter_image_preview" title="Remove" style="display:none">✕</button>
                    <?php endif; ?>
                </div>
                <?php if ( $f['_lumos_twitter_image'] ) : ?>
                    <img id="lm_twitter_image_preview" src="<?php echo esc_url( $f['_lumos_twitter_image'] ); ?>"
                         alt="" class="lumos-img-preview">
                <?php else : ?>
                    <img id="lm_twitter_image_preview" src="" alt="" class="lumos-img-preview" style="display:none">
                <?php endif; ?>
            </div>

        </div>

        <!-- Twitter card preview -->
        <div class="lumos-og-preview-wrap">
            <div class="lumos-tw-card">
                <div class="lumos-tw-card-img" id="lumos-tw-card-img"
                     <?php
                     $twimg = $f['_lumos_twitter_image'] ?: $f['_lumos_og_image'];
                     if ( $twimg ) echo 'style="background-image:url(' . esc_url( $twimg ) . ')"';
                     ?>></div>
                <div class="lumos-tw-card-body">
                    <div class="lumos-tw-card-title" id="lumos-tw-card-title">
                        <?php echo esc_html( $f['_lumos_twitter_title'] ?: $f['_lumos_og_title'] ?: $meta_title ); ?>
                    </div>
                    <div class="lumos-tw-card-desc" id="lumos-tw-card-desc">
                        <?php echo esc_html( $f['_lumos_twitter_description'] ?: $f['_lumos_og_description'] ?: $meta_desc ); ?>
                    </div>
                    <div class="lumos-tw-card-domain"><?php echo esc_html( parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
                </div>
            </div>
            <p class="lumos-hint" style="text-align:center;margin-top:6px">Live Twitter / X card preview</p>
        </div>

    </div><!-- /Twitter -->

    <!-- ══════════════════════ SERVICE SCHEMA TAB ═══════════════════════ -->
    <div class="lumos-mb-panel" id="lumos-tab-schema">
        <div class="lumos-og-notice">
            👉 Enable this only when this page represents a <strong>service</strong>. Valid JSON-LD can unlock rich result visibility.
        </div>

        <div class="lumos-mb-grid">
            <div class="lumos-mb-field lumos-mb-full">
                <label class="lumos-checkbox-label">
                    <input type="checkbox" name="service_schema_enabled" id="lm_service_schema_enabled" value="1"
                           <?php checked( $f['_lumos_service_schema_enabled'], '1' ); ?>>
                    <span>Output Service schema on this page</span>
                </label>
                <p class="lumos-hint">Turn off if this page is not a service page.</p>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <div class="lumos-field-header">
                    <label for="lm_service_schema_json">Service JSON-LD</label>
                    <button type="button" class="lumos-link-btn" id="lumos-copy-service-schema-prompt">Copy GPT prompt</button>
                </div>
                <textarea id="lm_service_schema_json" name="service_schema_json" rows="12"
                          class="lumos-textarea lumos-code-textarea"
                          placeholder='{"@context":"https://schema.org","@type":"Service","name":"..."}'><?php echo esc_textarea( $f['_lumos_service_schema_json'] ); ?></textarea>
                <p class="lumos-hint">Paste JSON only (without &lt;script&gt; tag). You can also import it from a .json file in the Import modal.</p>
                <div class="lumos-analyze-row">
                    <button type="button" id="lumos-update-schema-analysis" class="lumos-btn-primary">Update Analysis</button>
                    <span id="lumos-schema-analyzing" style="display:none;color:#999;font-size:12px">Analyzing…</span>
                </div>
            </div>
        </div>

        <div id="lumos-service-schema-analysis" class="lumos-schema-analysis"></div>
    </div><!-- /Service Schema -->

    <!-- ══════════════════════ ADVANCED TAB ═════════════════════════════ -->
    <div class="lumos-mb-panel" id="lumos-tab-advanced">
        <div class="lumos-mb-grid">

            <div class="lumos-mb-field lumos-mb-full">
                <label for="lm_canonical">Canonical URL</label>
                <input type="url" id="lm_canonical" name="canonical"
                       value="<?php echo esc_url( $f['_lumos_canonical'] ); ?>"
                       placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="lumos-input">
                <p class="lumos-hint">Leave blank to use the page URL. Use when you have duplicate content.</p>
            </div>

            <div class="lumos-mb-field lumos-mb-full">
                <label class="lumos-checkbox-label">
                    <input type="checkbox" name="noindex" value="1"
                           <?php checked( $noindex, '1' ); ?>>
                    <span>No index, No follow — hide this page from search engines</span>
                </label>
                <p class="lumos-hint">Useful for thank-you pages, login pages, or duplicate content.</p>
            </div>

        </div>
    </div><!-- /Advanced -->

</div><!-- .lumos-mb -->

<!-- ── Import JSON Modal ─────────────────────────────────────────────────── -->
<div id="lumos-import-modal" class="lumos-modal-overlay" style="display:none">
    <div class="lumos-modal">
        <div class="lumos-modal-header">
            <h3>🤖 Import SEO from AI</h3>
            <button type="button" id="lumos-close-import" class="lumos-modal-close">✕</button>
        </div>
        <div class="lumos-modal-body">
            <p class="lumos-modal-desc">
                Paste the JSON generated by your AI assistant. All recognised fields are filled in automatically.
            </p>
            <div class="lumos-modal-hint">
                📋 Need a template?
                <button type="button" id="lumos-copy-example" class="lumos-link-btn">Copy example JSON</button>
            </div>

            <div class="lumos-file-drop" id="lumos-file-drop">
                <input type="file" id="lumos-json-file" accept=".json,application/json" class="lumos-file-input">
                <span class="lumos-file-icon">📂</span>
                <span class="lumos-file-label">Drop a <strong>.json</strong> file here or <span class="lumos-link-btn">browse</span></span>
                <span class="lumos-file-name" id="lumos-file-name"></span>
            </div>

            <div class="lumos-import-or"><span>or paste JSON below</span></div>

            <textarea id="lumos-import-json" class="lumos-import-textarea"
                      placeholder='{&#10;  "focus_keyword": "...",&#10;  "meta_title": "...",&#10;  "meta_description": "...",&#10;  "og_title": "...",&#10;  "og_image": "https://...",&#10;  ...&#10;}'></textarea>
            <div id="lumos-import-feedback"></div>
            <div id="lumos-import-advisory"></div>
        </div><!-- /.lumos-modal-body -->

        <div class="lumos-modal-actions">
            <button type="button" id="lumos-close-import-2" class="lumos-btn-ghost">Cancel</button>
            <button type="button" id="lumos-validate-import" class="lumos-btn-primary">Validate & Preview</button>
        </div>
    </div>
</div>
