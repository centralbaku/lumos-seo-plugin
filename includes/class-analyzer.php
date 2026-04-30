<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Lumos_SEO_Analyzer {

    /**
     * Priority levels mapped to the SEO importance framework.
     * HIGH  → must-have, biggest ranking impact
     * MEDIUM → important, improves ranking
     * LOW   → nice-to-have, small impact
     */
    const HIGH   = 'high';
    const MEDIUM = 'medium';
    const LOW    = 'low';

    /**
     * @param int   $post_id
     * @param array $args  Override content/focus_keyword/meta_title/meta_description
     */
    public function analyze( $post_id, $args = [] ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return [ 'score' => 0, 'seo_score' => 0, 'read_score' => 0, 'seo' => [], 'read' => [] ];
        }

        // Prefer live editor values; fall back to saved meta / post content
        $content    = $args['content']          ?? $this->get_content( $post );
        $focus_kw   = $args['focus_keyword']    ?? get_post_meta( $post_id, '_lumos_focus_keyword', true );
        $meta_title = $args['meta_title']       ?? get_post_meta( $post_id, '_lumos_meta_title', true ) ?: get_the_title( $post_id );
        $meta_desc  = $args['meta_description'] ?? get_post_meta( $post_id, '_lumos_meta_description', true );
        $schema_enabled = $args['service_schema_enabled'] ?? get_post_meta( $post_id, '_lumos_service_schema_enabled', true );
        $schema_json    = $args['service_schema_json']    ?? get_post_meta( $post_id, '_lumos_service_schema_json', true );
        $plain      = wp_strip_all_tags( $content );

        $seo_checks  = [];
        $read_checks = [];

        // ── SEO checks ────────────────────────────────────────────────────
        if ( $focus_kw ) {
            $seo_checks[] = $this->check_kw_in_title( $focus_kw, $meta_title );
            $seo_checks[] = $this->check_kw_in_meta_desc( $focus_kw, $meta_desc );
            $seo_checks[] = $this->check_kw_in_first_paragraph( $focus_kw, $content );
            $seo_checks[] = $this->check_kw_in_headings( $focus_kw, $content );
            $seo_checks[] = $this->check_kw_in_url( $focus_kw, $post->post_name );
            $seo_checks[] = $this->check_kw_density( $focus_kw, $plain );
            $seo_checks[] = $this->check_kw_in_image_alt( $focus_kw, $content );
            $seo_checks[] = $this->check_previously_used_kw( $focus_kw, $post_id );
            $seo_checks[] = $this->check_competing_links( $focus_kw, $content );
        } else {
            $seo_checks[] = $this->make( 'kw_length',    'bad', self::HIGH,   'Keyphrase length: No focus keyphrase set. Add one to calculate your SEO score.' );
            $seo_checks[] = $this->make( 'kw_in_title',  'bad', self::HIGH,   'Keyphrase in SEO title: Please add a keyphrase and an SEO title beginning with it.' );
            $seo_checks[] = $this->make( 'kw_meta_desc', 'bad', self::HIGH,   'Keyphrase in meta description: Add a keyphrase and a meta description containing it.' );
            $seo_checks[] = $this->make( 'kw_intro',     'bad', self::HIGH,   'Keyphrase in introduction: Add a keyphrase and an introduction containing it.' );
            $seo_checks[] = $this->make( 'kw_density',   'bad', self::LOW,    'Keyphrase density: Add a keyphrase and content containing it.' );
            $seo_checks[] = $this->make( 'kw_img_alt',   'bad', self::MEDIUM, 'Keyphrase in image alt: Add images with alt attributes containing the keyphrase.' );
            $seo_checks[] = $this->make( 'kw_subheading','bad', self::MEDIUM, 'Keyphrase in subheading: Add a keyphrase and subheadings to receive feedback.' );
            $seo_checks[] = $this->make( 'previously_used','bad', self::MEDIUM,'Previously used keyphrase: No focus keyphrase set. Add one you haven\'t used before.' );
        }

        $seo_checks[] = $this->check_content_length( $plain );
        $seo_checks[] = $this->check_meta_title_length( $meta_title );
        $seo_checks[] = $this->check_meta_desc_length( $meta_desc );
        $seo_checks[] = $this->check_internal_links( $content );
        $seo_checks[] = $this->check_outbound_links( $content );
        $seo_checks[] = $this->check_images( $content );
        $seo_checks[] = $this->check_single_h1( $content );
        $seo_checks[] = $this->check_service_schema_enabled( $schema_enabled );
        $seo_checks[] = $this->check_service_schema_json( $schema_enabled, $schema_json );

        // ── Readability checks ─────────────────────────────────────────────
        $read_checks[] = $this->check_flesch( $plain );
        $read_checks[] = $this->check_paragraph_length( $content );
        $read_checks[] = $this->check_subheading_distribution( $content );
        $read_checks[] = $this->check_passive_voice( $plain );
        $read_checks[] = $this->check_transition_words( $plain );
        $read_checks[] = $this->check_sentence_length( $plain );
        $read_checks[] = $this->check_consecutive_sentences( $plain );

        $seo_score  = $this->calculate_score( $seo_checks );
        $read_score = $this->calculate_score( $read_checks );

        return [
            'score'      => intval( ( $seo_score + $read_score ) / 2 ),
            'seo_score'  => $seo_score,
            'read_score' => $read_score,
            'seo'        => $seo_checks,
            'read'       => $read_checks,
        ];
    }

    // ── Content source ─────────────────────────────────────────────────────

    private function get_content( $post ) {
        // Elementor: parse stored JSON elements
        if ( class_exists( '\Elementor\Plugin' ) ) {
            $data = get_post_meta( $post->ID, '_elementor_data', true );
            if ( $data ) {
                $elements = json_decode( $data, true );
                if ( $elements ) {
                    return $this->extract_elementor_html( $elements );
                }
            }
        }
        return $post->post_content;
    }

    private function extract_elementor_html( array $elements, &$html = '' ) {
        foreach ( $elements as $el ) {
            $settings = $el['settings'] ?? [];
            // Common Elementor text widget keys
            $text_keys = [ 'title', 'editor', 'text', 'description', 'caption', 'html', 'content' ];
            foreach ( $text_keys as $k ) {
                if ( ! empty( $settings[ $k ] ) && is_string( $settings[ $k ] ) ) {
                    $html .= ' ' . $settings[ $k ];
                }
            }
            if ( ! empty( $el['elements'] ) ) {
                $this->extract_elementor_html( $el['elements'], $html );
            }
        }
        return $html;
    }

    // ── SEO checks ─────────────────────────────────────────────────────────

    private function check_kw_in_title( $kw, $title ) {
        if ( stripos( $title, $kw ) === 0 ) {
            return $this->make( 'kw_in_title', 'good', self::HIGH, 'Keyphrase in SEO title: The focus keyphrase appears at the beginning. Well done!' );
        }
        if ( stripos( $title, $kw ) !== false ) {
            return $this->make( 'kw_in_title', 'ok', self::HIGH, 'Keyphrase in SEO title: The keyphrase appears in the title but not at the beginning. Move it to the front.' );
        }
        return $this->make( 'kw_in_title', 'bad', self::HIGH, 'Keyphrase in SEO title: The focus keyphrase does not appear in the SEO title.' );
    }

    private function check_kw_in_meta_desc( $kw, $desc ) {
        if ( ! $desc ) {
            return $this->make( 'kw_meta_desc', 'bad', self::HIGH, 'Keyphrase in meta description: No meta description. Add one containing the keyphrase.' );
        }
        $ok = stripos( $desc, $kw ) !== false;
        return $this->make( 'kw_meta_desc', $ok ? 'good' : 'ok', self::HIGH,
            $ok ? 'Keyphrase in meta description: The meta description contains the focus keyphrase.'
                : 'Keyphrase in meta description: The meta description does not contain the focus keyphrase.' );
    }

    private function check_kw_in_first_paragraph( $kw, $content ) {
        preg_match( '/<p[^>]*>(.*?)<\/p>/is', $content, $m );
        $first = wp_strip_all_tags( $m[0] ?? '' );
        if ( ! $first ) {
            return $this->make( 'kw_intro', 'bad', self::HIGH, 'Keyphrase in introduction: Your first paragraph is missing. Add one containing the keyphrase.' );
        }
        $ok = stripos( $first, $kw ) !== false;
        return $this->make( 'kw_intro', $ok ? 'good' : 'ok', self::HIGH,
            $ok ? 'Keyphrase in introduction: The focus keyphrase appears in the first paragraph. Well done!'
                : 'Keyphrase in introduction: The first paragraph does not contain the focus keyphrase. Fix that!' );
    }

    private function check_kw_in_headings( $kw, $content ) {
        preg_match_all( '/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $content, $matches );
        $headings = implode( ' ', $matches[1] ?? [] );
        $ok       = stripos( $headings, $kw ) !== false;
        return $this->make( 'kw_subheading', $ok ? 'good' : 'ok', self::MEDIUM,
            $ok ? 'Keyphrase in subheading: Your subheadings contain the focus keyphrase. Well done!'
                : 'Keyphrase in subheading: Your subheadings (H2–H6) do not reflect the focus keyphrase. Try adding it.' );
    }

    private function check_kw_in_url( $kw, $slug ) {
        $ok = stripos( $slug, sanitize_title( $kw ) ) !== false;
        return $this->make( 'kw_url', $ok ? 'good' : 'ok', self::MEDIUM,
            $ok ? 'Keyphrase in slug: The focus keyphrase appears in the URL slug.'
                : 'Keyphrase in slug: The focus keyphrase does not appear in the URL slug.' );
    }

    private function check_kw_density( $kw, $text ) {
        $words    = str_word_count( strtolower( $text ) );
        $kw_words = str_word_count( strtolower( $kw ) );
        if ( $words < 50 ) {
            return $this->make( 'kw_density', 'bad', self::LOW, 'Keyphrase density: The text is too short to calculate keyword density.' );
        }
        preg_match_all( '/' . preg_quote( strtolower( $kw ), '/' ) . '/i', strtolower( $text ), $m );
        $density = round( ( count( $m[0] ) * $kw_words ) / $words * 100, 1 );
        if ( $density < 0.5 ) return $this->make( 'kw_density', 'bad', self::LOW, "Keyphrase density: {$density}% — too low. Aim for 0.5–3%." );
        if ( $density > 3   ) return $this->make( 'kw_density', 'ok',  self::LOW, "Keyphrase density: {$density}% — may look like keyword stuffing. Keep it under 3%." );
        return $this->make( 'kw_density', 'good', self::LOW, "Keyphrase density: {$density}% — great." );
    }

    private function check_kw_in_image_alt( $kw, $content ) {
        preg_match_all( '/<img[^>]+>/i', $content, $imgs );
        if ( empty( $imgs[0] ) ) {
            return $this->make( 'kw_img_alt', 'bad', self::MEDIUM, 'Keyphrase in image alt: No images on this page. Add images with alt attributes containing the keyphrase.' );
        }
        preg_match_all( '/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', $content, $alts );
        $ok = stripos( implode( ' ', $alts[1] ?? [] ), $kw ) !== false;
        return $this->make( 'kw_img_alt', $ok ? 'good' : 'ok', self::MEDIUM,
            $ok ? 'Keyphrase in image alt: Images have alt attributes with the keyphrase. Well done!'
                : 'Keyphrase in image alt: Images do not have alt attributes containing the keyphrase.' );
    }

    private function check_previously_used_kw( $kw, $post_id ) {
        $existing = get_posts( [
            'post_type'      => 'any',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'post__not_in'   => [ $post_id ],
            'meta_query'     => [ [ 'key' => '_lumos_focus_keyword', 'value' => $kw ] ],
        ] );
        if ( $existing ) {
            $link = '<a href="' . esc_url( get_edit_post_link( $existing[0]->ID ) ) . '">' . esc_html( $existing[0]->post_title ) . '</a>';
            return $this->make( 'previously_used', 'bad', self::MEDIUM, "Previously used keyphrase: You've used this keyphrase before on {$link}. Use a unique keyphrase per page." );
        }
        return $this->make( 'previously_used', 'good', self::MEDIUM, 'Previously used keyphrase: This keyphrase has not been used before. Good job!' );
    }

    private function check_competing_links( $kw, $content ) {
        preg_match_all( '/<a[^>]+>(.*?)<\/a>/is', $content, $m );
        foreach ( $m[1] ?? [] as $anchor ) {
            if ( stripos( wp_strip_all_tags( $anchor ), $kw ) !== false ) {
                return $this->make( 'competing_links', 'bad', self::LOW, 'Competing links: A link uses your keyphrase as anchor text. Change or remove it to avoid confusing search engines.' );
            }
        }
        return $this->make( 'competing_links', 'good', self::LOW, 'Competing links: No links use your keyphrase as anchor text. Nice!' );
    }

    private function check_content_length( $text ) {
        $words = str_word_count( $text );
        if ( $words < 300 ) return $this->make( 'content_length', 'bad',  self::HIGH, "Text length: Only {$words} words. Aim for at least 300." );
        if ( $words < 600 ) return $this->make( 'content_length', 'ok',   self::HIGH, "Text length: {$words} words. Expanding to 600+ helps rankings." );
        return $this->make( 'content_length', 'good', self::HIGH, "Text length: The text contains {$words} words. Good job!" );
    }

    private function check_meta_title_length( $title ) {
        $len = mb_strlen( $title );
        if ( $len < 30 ) return $this->make( 'meta_title_width', 'bad',  self::HIGH, "SEO title width: Too short ({$len} chars). Use 30–60 characters." );
        if ( $len > 60 ) return $this->make( 'meta_title_width', 'ok',   self::HIGH, "SEO title width: {$len} chars — may be truncated in search results (max 60)." );
        return $this->make( 'meta_title_width', 'good', self::HIGH, 'SEO title width: Good job!' );
    }

    private function check_meta_desc_length( $desc ) {
        if ( ! $desc ) return $this->make( 'meta_desc_length', 'bad', self::HIGH, 'Meta description length: No meta description. Search engines will display page copy instead.' );
        $len = mb_strlen( $desc );
        if ( $len < 120 ) return $this->make( 'meta_desc_length', 'ok',   self::HIGH, "Meta description length: {$len} chars — aim for 120–158." );
        if ( $len > 158 ) return $this->make( 'meta_desc_length', 'ok',   self::HIGH, "Meta description length: {$len} chars — may be truncated (max 158)." );
        return $this->make( 'meta_desc_length', 'good', self::HIGH, 'Meta description length: Well done!' );
    }

    private function check_internal_links( $content ) {
        $host = preg_quote( parse_url( home_url(), PHP_URL_HOST ), '/' );
        preg_match_all( '/<a[^>]+href=["\']https?:\/\/' . $host . '[^"\']*["\'][^>]*>/i', $content, $m );
        $count = count( $m[0] );
        if ( ! $count ) return $this->make( 'internal_links', 'ok', self::HIGH, 'Internal links: No internal links found. Add some to improve site structure and navigation.' );
        return $this->make( 'internal_links', 'good', self::HIGH, "Internal links: You have {$count} internal link(s). Good job!" );
    }

    private function check_outbound_links( $content ) {
        $host = preg_quote( parse_url( home_url(), PHP_URL_HOST ), '/' );
        preg_match_all( '/<a[^>]+href=["\']https?:\/\/(?!' . $host . ')[^"\']+["\'][^>]*>/i', $content, $m );
        $count    = count( $m[0] );
        $nofollow = 0;
        foreach ( $m[0] as $link ) {
            if ( stripos( $link, 'nofollow' ) !== false ) $nofollow++;
        }
        if ( ! $count ) return $this->make( 'outbound_links', 'ok', self::MEDIUM, 'Outbound links: No outbound links. Consider linking to authoritative sources.' );
        if ( $nofollow === $count ) return $this->make( 'outbound_links', 'ok', self::MEDIUM, 'Outbound links: All outbound links are nofollowed. Add some normal links.' );
        return $this->make( 'outbound_links', 'good', self::MEDIUM, "Outbound links: Good — {$count} outbound link(s) found." );
    }

    private function check_images( $content ) {
        preg_match_all( '/<img[^>]+>/i', $content, $imgs );
        if ( ! count( $imgs[0] ) ) return $this->make( 'images', 'ok', self::MEDIUM, 'Images: No images on this page. Adding images improves engagement.' );
        $no_alt = 0;
        foreach ( $imgs[0] as $img ) {
            if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $img ) ) $no_alt++;
        }
        if ( $no_alt ) return $this->make( 'images', 'ok', self::MEDIUM, "Images: {$no_alt} image(s) missing alt text. Alt text helps SEO and accessibility." );
        return $this->make( 'images', 'good', self::MEDIUM, 'Images: Good job! All images have alt text.' );
    }

    private function check_single_h1( $content ) {
        preg_match_all( '/<h1[^>]*>/i', $content, $m );
        $count = count( $m[0] );
        if ( $count > 1 ) return $this->make( 'single_h1', 'bad', self::HIGH, "Single title: You have {$count} H1 headings. Only use one H1 per page." );
        return $this->make( 'single_h1', 'good', self::HIGH, "Single title: You don't have multiple H1 headings — well done!" );
    }

    private function check_service_schema_enabled( $enabled ) {
        if ( $enabled === '1' ) {
            return $this->make( 'service_schema_toggle', 'good', self::MEDIUM, 'Service schema: Enabled for this page.' );
        }
        return $this->make( 'service_schema_toggle', 'ok', self::MEDIUM, 'Service schema: Disabled. Enable only for pages that describe a service.' );
    }

    private function check_service_schema_json( $enabled, $schema_json ) {
        if ( $enabled !== '1' ) {
            return $this->make( 'service_schema_valid', 'ok', self::LOW, 'Service schema validation: Skipped because the feature is disabled.' );
        }
        if ( ! is_string( $schema_json ) || trim( $schema_json ) === '' ) {
            return $this->make( 'service_schema_valid', 'bad', self::HIGH, 'Service schema validation: Enabled but empty. Add valid JSON-LD.' );
        }

        $schema = json_decode( $schema_json, true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $schema ) ) {
            return $this->make( 'service_schema_valid', 'bad', self::HIGH, 'Service schema validation: Invalid JSON. Fix syntax before publishing.' );
        }

        if ( ( $schema['@type'] ?? '' ) !== 'Service' ) {
            return $this->make( 'service_schema_type', 'bad', self::HIGH, 'Service schema type: @type must be "Service".' );
        }

        $missing = [];
        foreach ( [ '@context', 'name', 'description', 'serviceType', 'provider' ] as $field ) {
            if ( empty( $schema[ $field ] ) ) {
                $missing[] = $field;
            }
        }
        if ( $missing ) {
            return $this->make(
                'service_schema_required_fields',
                'ok',
                self::MEDIUM,
                'Service schema fields: Missing recommended fields — ' . implode( ', ', $missing ) . '.'
            );
        }

        return $this->make( 'service_schema_valid', 'good', self::HIGH, 'Service schema validation: JSON-LD is valid and includes key fields.' );
    }

    // ── Readability checks ─────────────────────────────────────────────────

    private function check_flesch( $text ) {
        $sentences = max( 1, preg_match_all( '/[.!?]+/', $text, $m ) );
        $words     = max( 1, str_word_count( $text ) );
        $syllables = $this->count_syllables( $text );
        $score     = max( 0, min( 100, round( 206.835 - 1.015 * ( $words / $sentences ) - 84.6 * ( $syllables / $words ) ) ) );
        if ( $score >= 70 ) return $this->make( 'flesch', 'good', self::HIGH,   "Flesch Reading Ease: {$score}/100 — easy to read. Good job!" );
        if ( $score >= 50 ) return $this->make( 'flesch', 'ok',   self::HIGH,   "Flesch Reading Ease: {$score}/100 — fairly difficult. Try shorter sentences." );
        return $this->make( 'flesch', 'bad', self::HIGH, "Flesch Reading Ease: {$score}/100 — difficult. Simplify sentences and vocabulary." );
    }

    private function check_paragraph_length( $content ) {
        preg_match_all( '/<p[^>]*>(.*?)<\/p>/is', $content, $m );
        $long = 0;
        foreach ( $m[1] as $p ) {
            if ( str_word_count( wp_strip_all_tags( $p ) ) > 150 ) $long++;
        }
        if ( $long ) return $this->make( 'para_length', 'ok', self::MEDIUM, "{$long} paragraph(s) exceed 150 words. Break them up for easier reading." );
        return $this->make( 'para_length', 'good', self::MEDIUM, 'Paragraph length: There are no paragraphs that are too long. Great job!' );
    }

    private function check_subheading_distribution( $content ) {
        $words = str_word_count( wp_strip_all_tags( $content ) );
        if ( $words < 300 ) return $this->make( 'subheadings', 'ok', self::MEDIUM, 'Subheading distribution: Content is short — add subheadings to structure it.' );
        preg_match_all( '/<h[2-6][^>]*>/i', $content, $m );
        if ( ! count( $m[0] ) ) return $this->make( 'subheadings', 'bad', self::MEDIUM, 'Subheading distribution: No subheadings found. Use H2–H4 to break up content.' );
        $avg = intval( $words / ( count( $m[0] ) + 1 ) );
        if ( $avg > 300 ) return $this->make( 'subheadings', 'ok', self::MEDIUM, 'Subheading distribution: Some sections between headings exceed 300 words. Add more subheadings.' );
        return $this->make( 'subheadings', 'good', self::MEDIUM, 'Subheading distribution: Great job!' );
    }

    private function check_passive_voice( $text ) {
        $sentences = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $passive   = 0;
        foreach ( $sentences as $s ) {
            if ( preg_match( '/\b(am|is|are|was|were|be|being|been)\b.{0,30}?\w+ed\b/i', $s ) ) $passive++;
        }
        $total   = max( 1, count( $sentences ) );
        $percent = round( $passive / $total * 100 );
        if ( $percent > 20 ) return $this->make( 'passive_voice', 'ok', self::LOW, "Passive voice: {$percent}% of sentences use passive voice (aim < 20%). Use active voice more." );
        return $this->make( 'passive_voice', 'good', self::LOW, "Passive voice: You are not using too much passive voice ({$percent}%). That's great!" );
    }

    private function check_transition_words( $text ) {
        $transitions = [
            'however','therefore','moreover','furthermore','consequently','meanwhile',
            'nevertheless','additionally','similarly','alternatively','finally','thus',
            'hence','accordingly','although','because','whereas','instead','otherwise',
            'in addition','as a result','for example','for instance','in conclusion',
            'in summary','in contrast','first','second','third','also','besides',
            'especially','in fact','indeed','namely','certainly','clearly',
        ];
        $sentences = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $total     = max( 1, count( $sentences ) );
        $with_tw   = 0;
        foreach ( $sentences as $s ) {
            foreach ( $transitions as $t ) {
                if ( stripos( $s, $t ) !== false ) { $with_tw++; break; }
            }
        }
        $percent = round( $with_tw / $total * 100, 1 );
        if ( $percent < 25 ) return $this->make( 'transition_words', 'bad', self::LOW, "Transition words: Only {$percent}% of sentences contain transition words (aim 25%+). Use more of them." );
        return $this->make( 'transition_words', 'good', self::LOW, "Transition words: {$percent}% of sentences use transition words. Good job!" );
    }

    private function check_sentence_length( $text ) {
        $sentences = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $long      = 0;
        foreach ( $sentences as $s ) {
            if ( str_word_count( $s ) > 25 ) $long++;
        }
        $total   = max( 1, count( $sentences ) );
        $percent = round( $long / $total * 100 );
        if ( $percent > 25 ) return $this->make( 'sentence_length', 'ok', self::MEDIUM, "Sentence length: {$percent}% of sentences exceed 25 words. Shorten them." );
        return $this->make( 'sentence_length', 'good', self::MEDIUM, "Sentence length: Great! {$percent}% of sentences are too long." );
    }

    private function check_consecutive_sentences( $text ) {
        $sentences = preg_split( '/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );
        $prev = null; $streak = 0; $max = 0;
        foreach ( $sentences as $s ) {
            $first = strtolower( preg_replace( '/\W.*/', '', trim( $s ) ) );
            if ( $first && $first === $prev ) { $streak++; $max = max( $max, $streak ); }
            else { $streak = 1; }
            $prev = $first;
        }
        if ( $max >= 3 ) return $this->make( 'consecutive_sentences', 'ok', self::LOW, "Consecutive sentences: {$max} sentences start with the same word. Vary your sentence openings." );
        return $this->make( 'consecutive_sentences', 'good', self::LOW, 'Consecutive sentences: No repetitive sentence beginnings. That\'s great!' );
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function make( $id, $status, $priority, $message, $group = 'seo' ) {
        // Infer group from caller context — readability checks pass 'readability'
        $bt = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
        $caller = $bt[1]['function'] ?? '';
        if ( in_array( $caller, [ 'check_flesch', 'check_paragraph_length', 'check_subheading_distribution',
                                   'check_passive_voice', 'check_transition_words', 'check_sentence_length',
                                   'check_consecutive_sentences' ], true ) ) {
            $group = 'readability';
        }
        return compact( 'id', 'status', 'priority', 'message', 'group' );
    }

    private function calculate_score( $checks ) {
        // Weighted by priority: HIGH=3, MEDIUM=2, LOW=1
        $weights = [ self::HIGH => 3, self::MEDIUM => 2, self::LOW => 1 ];
        $points  = 0;
        $max     = 0;
        foreach ( $checks as $c ) {
            $w    = $weights[ $c['priority'] ] ?? 1;
            $max += $w * 2;
            if ( $c['status'] === 'good' ) $points += $w * 2;
            elseif ( $c['status'] === 'ok' ) $points += $w;
        }
        return $max > 0 ? round( $points / $max * 100 ) : 0;
    }

    private function count_syllables( $text ) {
        $total = 0;
        foreach ( preg_split( '/\s+/', strtolower( $text ) ) as $word ) {
            $word   = preg_replace( '/[^a-z]/', '', $word );
            $count  = preg_match_all( '/[aeiouy]+/', $word, $m );
            $total += max( 1, $count );
        }
        return $total;
    }
}
