<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Pull all saved values
$f = [];
foreach ( array_keys( Lumo_SEO_Meta_Box::FIELD_MAP ) as $key ) {
    $f[ $key ] = get_post_meta( $post->ID, $key, true );
}
$focus_kw   = $f['_lumo_focus_keyword'];
$meta_title = $f['_lumo_meta_title'] ?: get_the_title( $post->ID );
$meta_desc  = $f['_lumo_meta_description'];
$noindex    = $f['_lumo_noindex'];
?>

<div class="lumo-mb" id="lumo-meta-box">

    <?php wp_nonce_field( 'lumo_seo_save', 'lumo_seo_nonce' ); ?>

    <!-- ── Import from AI banner ───────────────────────────────────────── -->
    <div class="lumo-import-banner">
        <span>🤖 <strong>Import SEO from AI</strong> — paste JSON to fill all fields automatically</span>
        <button type="button" class="lumo-btn-import" id="lumo-open-import">
            Import JSON
        </button>
    </div>

    <!-- ── Tabs ───────────────────────────────────────────────────────── -->
    <div class="lumo-mb-tabs">
        <button type="button" class="lumo-mb-tab active" data-tab="seo">SEO</button>
        <button type="button" class="lumo-mb-tab" data-tab="opengraph">Open Graph</button>
        <button type="button" class="lumo-mb-tab" data-tab="twitter">Twitter / X</button>
        <button type="button" class="lumo-mb-tab" data-tab="advanced">Advanced</button>
    </div>

    <!-- ══════════════════════ SEO TAB ══════════════════════════════════ -->
    <div class="lumo-mb-panel active" id="lumo-tab-seo">

        <!-- Google snippet preview -->
        <div class="lumo-snippet-box">
            <div class="lumo-snippet-title" id="lumo-preview-title"><?php echo esc_html( $meta_title ); ?></div>
            <div class="lumo-snippet-url"><?php echo esc_url( get_permalink( $post->ID ) ); ?></div>
            <div class="lumo-snippet-desc" id="lumo-preview-desc"><?php echo $meta_desc ? esc_html( $meta_desc ) : '<span style="color:#aaa">No meta description set.</span>'; ?></div>
        </div>

        <div class="lumo-mb-grid">

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_focus_keyword">Focus Keyword</label>
                <input type="text" id="lm_focus_keyword" name="focus_keyword"
                       value="<?php echo esc_attr( $focus_kw ); ?>"
                       placeholder="e.g. best running shoes" class="lumo-input">
                <p class="lumo-hint">The keyword you want this page to rank for.</p>
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <div class="lumo-field-header">
                    <label for="lm_meta_title">SEO Title</label>
                    <span class="lumo-counter" id="lm_title_counter">0 / 60</span>
                </div>
                <input type="text" id="lm_meta_title" name="meta_title"
                       value="<?php echo esc_attr( $meta_title ); ?>"
                       class="lumo-input">
                <div class="lumo-progress-wrap"><div class="lumo-progress" id="lm_title_bar"></div></div>
                <p class="lumo-hint">30–60 characters recommended.</p>
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <div class="lumo-field-header">
                    <label for="lm_meta_description">Meta Description</label>
                    <span class="lumo-counter" id="lm_desc_counter">0 / 158</span>
                </div>
                <textarea id="lm_meta_description" name="meta_description" rows="3"
                          class="lumo-textarea"><?php echo esc_textarea( $meta_desc ); ?></textarea>
                <div class="lumo-progress-wrap"><div class="lumo-progress" id="lm_desc_bar"></div></div>
                <p class="lumo-hint">120–158 characters recommended.</p>
            </div>

        </div>

        <!-- Analyze button + results -->
        <div class="lumo-analyze-row">
            <button type="button" id="lumo-analyze-btn" class="lumo-btn-primary">
                Analyze SEO
            </button>
            <span id="lumo-analyzing" style="display:none;color:#999;font-size:12px">Analyzing…</span>
        </div>

        <div id="lumo-results" style="display:none;margin-top:12px">
            <div class="lumo-score-header">
                <div class="lumo-donut-wrap">
                    <svg class="lumo-donut" viewBox="0 0 36 36">
                        <path class="lumo-donut-bg" d="M18 2.0845 a15.9155 15.9155 0 0 1 0 31.831 a15.9155 15.9155 0 0 1 0-31.831"/>
                        <path class="lumo-donut-fill" id="lumo-arc" stroke-dasharray="0,100"
                              d="M18 2.0845 a15.9155 15.9155 0 0 1 0 31.831 a15.9155 15.9155 0 0 1 0-31.831"/>
                    </svg>
                    <span class="lumo-score-num" id="lumo-score-num">—</span>
                </div>
                <div class="lumo-score-meta">
                    <div id="lumo-score-label" style="font-weight:600;font-size:14px"></div>
                    <div style="font-size:12px;color:#888">Overall SEO Score</div>
                    <button type="button" id="lumo-copy-report" class="lumo-btn-copy-report" style="display:none">
                        📋 Copy report for GPT
                    </button>
                </div>
            </div>
            <div id="lumo-fix-summary" style="display:none;margin-bottom:10px"></div>
            <div id="lumo-checks-seo"></div>
            <div id="lumo-checks-read" style="margin-top:10px"></div>
        </div>

    </div><!-- /SEO -->

    <!-- ══════════════════════ OPEN GRAPH TAB ════════════════════════════ -->
    <div class="lumo-mb-panel" id="lumo-tab-opengraph">

        <div class="lumo-og-notice">
            👉 These define your preview on <strong>Facebook, LinkedIn, WhatsApp</strong>
        </div>

        <div class="lumo-mb-grid">

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_og_title">og:title</label>
                <input type="text" id="lm_og_title" name="og_title"
                       value="<?php echo esc_attr( $f['_lumo_og_title'] ); ?>"
                       placeholder="Defaults to SEO title" class="lumo-input">
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_og_description">og:description</label>
                <textarea id="lm_og_description" name="og_description" rows="2"
                          class="lumo-textarea"
                          placeholder="Defaults to meta description"><?php echo esc_textarea( $f['_lumo_og_description'] ); ?></textarea>
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_og_image">og:image <span class="lumo-badge">Ideal: 1200×630 px</span></label>
                <div class="lumo-media-row">
                    <input type="url" id="lm_og_image" name="og_image"
                           value="<?php echo esc_url( $f['_lumo_og_image'] ); ?>"
                           placeholder="https://yoursite.com/image.jpg" class="lumo-input">
                    <button type="button" class="lumo-btn-media" data-target="#lm_og_image" data-preview="#lm_og_image_preview">
                        Select image
                    </button>
                    <?php if ( $f['_lumo_og_image'] ) : ?>
                        <button type="button" class="lumo-btn-media-remove" data-target="#lm_og_image" data-preview="#lm_og_image_preview" title="Remove">✕</button>
                    <?php else : ?>
                        <button type="button" class="lumo-btn-media-remove" data-target="#lm_og_image" data-preview="#lm_og_image_preview" title="Remove" style="display:none">✕</button>
                    <?php endif; ?>
                </div>
                <?php if ( $f['_lumo_og_image'] ) : ?>
                    <img id="lm_og_image_preview" src="<?php echo esc_url( $f['_lumo_og_image'] ); ?>"
                         alt="" class="lumo-img-preview">
                <?php else : ?>
                    <img id="lm_og_image_preview" src="" alt="" class="lumo-img-preview" style="display:none">
                <?php endif; ?>
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_og_url">og:url</label>
                <input type="url" id="lm_og_url" name="og_url"
                       value="<?php echo esc_url( $f['_lumo_og_url'] ); ?>"
                       placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="lumo-input">
            </div>

            <div class="lumo-mb-field">
                <label for="lm_og_type">og:type</label>
                <select id="lm_og_type" name="og_type" class="lumo-select">
                    <?php foreach ( [ 'article','website','product','book','profile','video.movie','music.song' ] as $t ) : ?>
                        <option value="<?php echo esc_attr( $t ); ?>"
                            <?php selected( $f['_lumo_og_type'] ?: ( $post->post_type === 'post' ? 'article' : 'website' ), $t ); ?>>
                            <?php echo esc_html( $t ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lumo-mb-field">
                <label for="lm_og_locale">og:locale</label>
                <input type="text" id="lm_og_locale" name="og_locale"
                       value="<?php echo esc_attr( $f['_lumo_og_locale'] ); ?>"
                       placeholder="en_US" class="lumo-input">
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_og_site_name">og:site_name</label>
                <input type="text" id="lm_og_site_name" name="og_site_name"
                       value="<?php echo esc_attr( $f['_lumo_og_site_name'] ); ?>"
                       placeholder="<?php echo esc_attr( get_option( 'lumo_seo_site_name', get_bloginfo( 'name' ) ) ); ?>"
                       class="lumo-input">
            </div>

        </div>

        <!-- Social preview card -->
        <div class="lumo-og-preview-wrap">
            <div class="lumo-og-card" id="lumo-og-card">
                <div class="lumo-og-card-img" id="lumo-og-card-img"
                     <?php if ( $f['_lumo_og_image'] ) echo 'style="background-image:url(' . esc_url( $f['_lumo_og_image'] ) . ')"'; ?>></div>
                <div class="lumo-og-card-body">
                    <div class="lumo-og-card-domain"><?php echo esc_html( parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
                    <div class="lumo-og-card-title" id="lumo-og-card-title">
                        <?php echo esc_html( $f['_lumo_og_title'] ?: $meta_title ); ?>
                    </div>
                    <div class="lumo-og-card-desc" id="lumo-og-card-desc">
                        <?php echo esc_html( $f['_lumo_og_description'] ?: $meta_desc ); ?>
                    </div>
                </div>
            </div>
            <p class="lumo-hint" style="text-align:center;margin-top:6px">Live Facebook / LinkedIn preview</p>
        </div>

    </div><!-- /Open Graph -->

    <!-- ══════════════════════ TWITTER TAB ══════════════════════════════ -->
    <div class="lumo-mb-panel" id="lumo-tab-twitter">

        <div class="lumo-og-notice">
            👉 For better control + platform compatibility on <strong>Twitter / X</strong>
        </div>

        <div class="lumo-mb-grid">

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_twitter_card">twitter:card</label>
                <select id="lm_twitter_card" name="twitter_card" class="lumo-select">
                    <?php foreach ( [
                        'summary_large_image' => 'summary_large_image — large image card',
                        'summary'             => 'summary — small image card',
                        'app'                 => 'app',
                        'player'              => 'player',
                    ] as $val => $label ) : ?>
                        <option value="<?php echo esc_attr( $val ); ?>"
                            <?php selected( $f['_lumo_twitter_card'] ?: 'summary_large_image', $val ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_twitter_title">twitter:title</label>
                <input type="text" id="lm_twitter_title" name="twitter_title"
                       value="<?php echo esc_attr( $f['_lumo_twitter_title'] ); ?>"
                       placeholder="Falls back to og:title" class="lumo-input">
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_twitter_description">twitter:description</label>
                <textarea id="lm_twitter_description" name="twitter_description" rows="2"
                          class="lumo-textarea"
                          placeholder="Falls back to og:description"><?php echo esc_textarea( $f['_lumo_twitter_description'] ); ?></textarea>
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_twitter_image">twitter:image <span class="lumo-badge">Ideal: 1200×600 px</span></label>
                <div class="lumo-media-row">
                    <input type="url" id="lm_twitter_image" name="twitter_image"
                           value="<?php echo esc_url( $f['_lumo_twitter_image'] ); ?>"
                           placeholder="Falls back to og:image" class="lumo-input">
                    <button type="button" class="lumo-btn-media" data-target="#lm_twitter_image" data-preview="#lm_twitter_image_preview">
                        Select image
                    </button>
                    <?php if ( $f['_lumo_twitter_image'] ) : ?>
                        <button type="button" class="lumo-btn-media-remove" data-target="#lm_twitter_image" data-preview="#lm_twitter_image_preview" title="Remove">✕</button>
                    <?php else : ?>
                        <button type="button" class="lumo-btn-media-remove" data-target="#lm_twitter_image" data-preview="#lm_twitter_image_preview" title="Remove" style="display:none">✕</button>
                    <?php endif; ?>
                </div>
                <?php if ( $f['_lumo_twitter_image'] ) : ?>
                    <img id="lm_twitter_image_preview" src="<?php echo esc_url( $f['_lumo_twitter_image'] ); ?>"
                         alt="" class="lumo-img-preview">
                <?php else : ?>
                    <img id="lm_twitter_image_preview" src="" alt="" class="lumo-img-preview" style="display:none">
                <?php endif; ?>
            </div>

        </div>

        <!-- Twitter card preview -->
        <div class="lumo-og-preview-wrap">
            <div class="lumo-tw-card">
                <div class="lumo-tw-card-img" id="lumo-tw-card-img"
                     <?php
                     $twimg = $f['_lumo_twitter_image'] ?: $f['_lumo_og_image'];
                     if ( $twimg ) echo 'style="background-image:url(' . esc_url( $twimg ) . ')"';
                     ?>></div>
                <div class="lumo-tw-card-body">
                    <div class="lumo-tw-card-title" id="lumo-tw-card-title">
                        <?php echo esc_html( $f['_lumo_twitter_title'] ?: $f['_lumo_og_title'] ?: $meta_title ); ?>
                    </div>
                    <div class="lumo-tw-card-desc" id="lumo-tw-card-desc">
                        <?php echo esc_html( $f['_lumo_twitter_description'] ?: $f['_lumo_og_description'] ?: $meta_desc ); ?>
                    </div>
                    <div class="lumo-tw-card-domain"><?php echo esc_html( parse_url( home_url(), PHP_URL_HOST ) ); ?></div>
                </div>
            </div>
            <p class="lumo-hint" style="text-align:center;margin-top:6px">Live Twitter / X card preview</p>
        </div>

    </div><!-- /Twitter -->

    <!-- ══════════════════════ ADVANCED TAB ═════════════════════════════ -->
    <div class="lumo-mb-panel" id="lumo-tab-advanced">
        <div class="lumo-mb-grid">

            <div class="lumo-mb-field lumo-mb-full">
                <label for="lm_canonical">Canonical URL</label>
                <input type="url" id="lm_canonical" name="canonical"
                       value="<?php echo esc_url( $f['_lumo_canonical'] ); ?>"
                       placeholder="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="lumo-input">
                <p class="lumo-hint">Leave blank to use the page URL. Use when you have duplicate content.</p>
            </div>

            <div class="lumo-mb-field lumo-mb-full">
                <label class="lumo-checkbox-label">
                    <input type="checkbox" name="noindex" value="1"
                           <?php checked( $noindex, '1' ); ?>>
                    <span>No index, No follow — hide this page from search engines</span>
                </label>
                <p class="lumo-hint">Useful for thank-you pages, login pages, or duplicate content.</p>
            </div>

        </div>
    </div><!-- /Advanced -->

</div><!-- .lumo-mb -->

<!-- ── Import JSON Modal ─────────────────────────────────────────────────── -->
<div id="lumo-import-modal" class="lumo-modal-overlay" style="display:none">
    <div class="lumo-modal">
        <div class="lumo-modal-header">
            <h3>🤖 Import SEO from AI</h3>
            <button type="button" id="lumo-close-import" class="lumo-modal-close">✕</button>
        </div>
        <div class="lumo-modal-body">
            <p class="lumo-modal-desc">
                Paste the JSON generated by your AI assistant. All recognised fields are filled in automatically.
            </p>
            <div class="lumo-modal-hint">
                📋 Need a template?
                <button type="button" id="lumo-copy-example" class="lumo-link-btn">Copy example JSON</button>
            </div>

            <div class="lumo-file-drop" id="lumo-file-drop">
                <input type="file" id="lumo-json-file" accept=".json,application/json" class="lumo-file-input">
                <span class="lumo-file-icon">📂</span>
                <span class="lumo-file-label">Drop a <strong>.json</strong> file here or <span class="lumo-link-btn">browse</span></span>
                <span class="lumo-file-name" id="lumo-file-name"></span>
            </div>

            <div class="lumo-import-or"><span>or paste JSON below</span></div>

            <textarea id="lumo-import-json" class="lumo-import-textarea"
                      placeholder='{&#10;  "focus_keyword": "...",&#10;  "meta_title": "...",&#10;  "meta_description": "...",&#10;  "og_title": "...",&#10;  "og_image": "https://...",&#10;  ...&#10;}'></textarea>
            <div id="lumo-import-feedback"></div>
            <div id="lumo-import-advisory"></div>
        </div><!-- /.lumo-modal-body -->

        <div class="lumo-modal-actions">
            <button type="button" id="lumo-close-import-2" class="lumo-btn-ghost">Cancel</button>
            <button type="button" id="lumo-validate-import" class="lumo-btn-primary">Validate & Preview</button>
        </div>
    </div>
</div>
