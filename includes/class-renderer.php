<?php
/**
 * Front-end badge renderer.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wraps attachment-backed images with a localized AI badge overlay.
 *
 * Two rendering paths give full coverage:
 *  - wp_get_attachment_image (featured images, galleries via the function,
 *    WooCommerce product thumbnails, and any theme/plugin call).
 *  - the_content (in-content Image blocks, gallery blocks, and classic-editor
 *    images), matched by the wp-image-{ID} class WordPress bakes into them.
 */
class EU_AI_Label_Renderer {

	/**
	 * Whether the badge stylesheet has already been enqueued this request.
	 *
	 * @var bool
	 */
	private $style_enqueued = false;

	/**
	 * Attachment IDs resolved from image URLs during this request.
	 *
	 * Elementor post-content widgets can render the same image more than once,
	 * so cache both successful and failed lookups to avoid repeated queries.
	 *
	 * @var array<string,int>
	 */
	private $attachment_ids_by_url = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_get_attachment_image', array( $this, 'wrap_image' ), 10, 5 );
		// Priority 20 runs after do_blocks (9), do_shortcode (11), and wpautop (10),
		// so block/shortcode/classic images are all present and untouched afterward.
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_style' ) );
	}

	/**
	 * Register and enqueue the badge stylesheet on every front-end request.
	 *
	 * Elementor can inject Loop Grid and carousel items after the initial page
	 * response. Loading this small stylesheet up front keeps those Ajax-rendered
	 * labels styled even when the first response contained no labeled image.
	 *
	 * @return void
	 */
	public function register_style() {
		$path = EU_AI_LABEL_DIR . 'assets/css/badge.css';

		// In debug/dev, bust the browser cache on every edit by versioning
		// the stylesheet with its file mtime; production keeps the stable
		// plugin version so shared caches/CDNs stay effective.
		$version = EU_AI_LABEL_VERSION;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( $path ) ) {
			$version = (string) filemtime( $path );
		}

		wp_register_style(
			'eu-ai-label-badge',
			EU_AI_LABEL_URL . 'assets/css/badge.css',
			array(),
			$version
		);

		if ( ! is_admin() || wp_doing_ajax() ) {
			wp_enqueue_style( 'eu-ai-label-badge' );
			$this->style_enqueued = true;
		}
	}

	/**
	 * Localized badge text for a given status.
	 *
	 * @param string $status Enum status.
	 * @return string
	 */
	public static function badge_text( $status ) {
		switch ( $status ) {
			case EU_AI_Label_Plugin::STATUS_AI_GENERATED:
				return __( 'AI generated', 'eu-ai-label' );
			case EU_AI_Label_Plugin::STATUS_AI_EDITED:
				return __( 'AI modified', 'eu-ai-label' );
			case EU_AI_Label_Plugin::STATUS_AI_UNDISCLOSED:
				// Short mark for the "AI involved, details undisclosed" circle badge.
				return __( 'AI', 'eu-ai-label' );
			default:
				return '';
		}
	}

	/**
	 * Return the sanitized global badge position.
	 *
	 * @return string
	 */
	public static function badge_position() {
		$options   = EU_AI_Label_Settings::get_options();
		$position  = isset( $options['position'] ) ? sanitize_key( $options['position'] ) : '';
		$positions = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );

		return in_array( $position, $positions, true ) ? $position : 'bottom-left';
	}

	/**
	 * Return the positioning class shared by all rendering integrations.
	 *
	 * @return string
	 */
	public static function position_class() {
		return 'eu-ai-label-pos--' . self::badge_position();
	}

	/**
	 * Filter callback: wrap the image HTML when the attachment carries a badge status.
	 *
	 * @param string       $html          Original <img> markup.
	 * @param int          $attachment_id Attachment ID.
	 * @param string|int[] $size          Requested size (unused).
	 * @param bool         $icon          Whether an icon was requested (unused).
	 * @param array        $attr          Image attributes (unused).
	 * @return string
	 */
	public function wrap_image( $html, $attachment_id, $size = '', $icon = false, $attr = array() ) {
		if ( empty( $html ) ) {
			return $html;
		}

		// Admin screens (Media Library list thumbnails, editors) never load the
		// badge stylesheet, so the wrap would spill raw badge text into the UI.
		// Ajax is exempt: some themes render front-end content via admin-ajax.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $html;
		}

		$badge = $this->badge_markup_for_attachment( (int) $attachment_id );
		if ( '' === $badge ) {
			return $html;
		}

		return sprintf(
			'<figure class="eu-ai-label-wrap %1$s">%2$s%3$s</figure>',
			esc_attr( self::position_class() ),
			$html,
			$badge
		);
	}

	/**
	 * Build badge markup for an attachment without wrapping an image element.
	 *
	 * Elementor background images do not have an <img> node to filter. This
	 * public helper lets that integration reuse the exact same status checks,
	 * accessibility markup, Pro filters, and assets as normal attachment images.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Badge markup, or an empty string when no badge is required.
	 */
	public function badge_markup_for_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$status        = get_post_meta( $attachment_id, EU_AI_Label_Plugin::META_KEY, true );

		if ( ! EU_AI_Label_Plugin::status_has_badge( $status ) ) {
			return '';
		}

		$text = self::badge_text( $status );
		if ( '' === $text ) {
			return '';
		}

		if ( ! wp_style_is( 'eu-ai-label-badge', 'registered' ) ) {
			$this->register_style();
		} elseif ( ! $this->style_enqueued && ( ! is_admin() || wp_doing_ajax() ) ) {
			wp_enqueue_style( 'eu-ai-label-badge' );
			$this->style_enqueued = true;
		}

		return $this->badge_markup( $status, $text, $attachment_id );
	}

	/**
	 * Wrap attachment-backed images inside post content with the badge.
	 *
	 * Matches images by the `wp-image-{ID}` class that WordPress adds to
	 * media-library images (block editor, gallery block, and classic editor),
	 * so in-content images get the same disclosure as featured/gallery images.
	 *
	 * Elementor featured-image widgets sometimes render a plain image URL and
	 * omit WordPress' `wp-image-{ID}` class. Integrations can pass the known
	 * attachment ID as a fallback; it is applied to the first otherwise
	 * unidentified image only.
	 *
	 * @param string $content                Post content HTML.
	 * @param int    $fallback_attachment_id Optional attachment ID for the first
	 *                                       image without a wp-image class.
	 * @return string
	 */
	public function filter_content( $content, $fallback_attachment_id = 0 ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$fallback_attachment_id = (int) $fallback_attachment_id;

		// Same admin guard as wrap_image: no stylesheet there, so no wrapping.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $content;
		}

		// Fast path: there is nothing to inspect without an image element.
		if ( false === stripos( $content, '<img' ) || ! class_exists( 'DOMDocument' ) ) {
			return $content;
		}

		$dom          = new DOMDocument( '1.0', 'UTF-8' );
		$libxml_state = libxml_use_internal_errors( true );
		// The XML encoding PI forces UTF-8 parsing so multibyte text is not mangled.
		$loaded = $dom->loadHTML(
			'<?xml encoding="UTF-8" ?><eu-ai-root>' . $content . '</eu-ai-root>',
			LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $libxml_state );

		if ( ! $loaded ) {
			return $content;
		}

		$root = $dom->getElementsByTagName( 'eu-ai-root' )->item( 0 );
		if ( null === $root ) {
			return $content;
		}

		$changed       = false;
		$fallback_used = false;

		// Snapshot the list first: the tree is mutated inside the loop.
		foreach ( iterator_to_array( $dom->getElementsByTagName( 'img' ) ) as $img ) {
			if ( $this->within_wrap( $img ) ) {
				continue;
			}

			$class         = $img->getAttribute( 'class' );
			$attachment_id = 0;
			if ( preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', $class, $matches ) ) {
				$attachment_id = (int) $matches[1];
			} else {
				$attachment_id = $this->attachment_id_from_image_url( $img );
			}

			if ( 0 >= $attachment_id && 0 < $fallback_attachment_id && ! $fallback_used ) {
				$attachment_id = $fallback_attachment_id;
				$fallback_used = true;
			}

			if ( 0 >= $attachment_id ) {
				continue;
			}

			$badge = $this->badge_markup_for_attachment( $attachment_id );
			if ( '' === $badge ) {
				continue;
			}

			$parent = $img->parentNode;
			if ( null === $parent ) {
				continue;
			}

			// Wrap only the <img> in an inline span (valid inside <p>/<figure>),
			// then append the badge as a positioned sibling overlay.
			$wrap = $dom->createElement( 'span' );
			$wrap->setAttribute( 'class', 'eu-ai-label-wrap ' . self::position_class() );
			$parent->replaceChild( $wrap, $img );
			$wrap->appendChild( $img );

			$fragment = $dom->createDocumentFragment();
			// Badge markup is well-formed XML, so appendXML preserves SVG case.
			if ( $fragment->appendXML( $badge ) ) {
				$wrap->appendChild( $fragment );
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return $content;
		}

		$out = '';
		foreach ( $root->childNodes as $node ) {
			$out .= $dom->saveHTML( $node );
		}

		return $out;
	}

	/**
	 * Resolve an attachment ID from image URL attributes.
	 *
	 * Some Elementor post-content widgets omit the `wp-image-{ID}` class, while
	 * optimization plugins move the real URL from `src` into a lazy-load data
	 * attribute. WordPress' attachment lookup only returns locally registered
	 * Media Library files, so external and arbitrary URLs remain untouched.
	 *
	 * @param DOMElement $img Image node.
	 * @return int Attachment ID, or zero when the image is not locally owned.
	 */
	private function attachment_id_from_image_url( DOMElement $img ) {
		$url_attributes = array( 'src', 'data-src', 'data-lazy-src', 'data-lzl-src' );
		foreach ( $url_attributes as $attribute ) {
			$attachment_id = $this->attachment_id_from_url( $img->getAttribute( $attribute ) );
			if ( 0 < $attachment_id ) {
				return $attachment_id;
			}
		}

		$srcset_attributes = array( 'srcset', 'data-srcset', 'data-lazy-srcset', 'data-lzl-srcset' );
		foreach ( $srcset_attributes as $attribute ) {
			$srcset = trim( $img->getAttribute( $attribute ) );
			if ( '' === $srcset ) {
				continue;
			}

			$candidates = preg_split( '/\s*,\s*/', $srcset );
			if ( ! is_array( $candidates ) ) {
				continue;
			}

			foreach ( $candidates as $candidate ) {
				$parts = preg_split( '/\s+/', trim( $candidate ) );
				$url   = is_array( $parts ) && isset( $parts[0] ) ? $parts[0] : '';

				$attachment_id = $this->attachment_id_from_url( $url );
				if ( 0 < $attachment_id ) {
					return $attachment_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Resolve one local Media Library URL, including generated size variants.
	 *
	 * @param string $url Candidate image URL.
	 * @return int Attachment ID, or zero when not found.
	 */
	private function attachment_id_from_url( $url ) {
		$url = html_entity_decode( trim( (string) $url ), ENT_QUOTES, 'UTF-8' );
		if ( '' === $url || 0 === strpos( $url, 'data:' ) || 0 === strpos( $url, 'blob:' ) ) {
			return 0;
		}

		// Query strings and fragments are not part of WordPress' attached-file
		// metadata and would prevent attachment_url_to_postid() from matching.
		$clean_url = preg_replace( '/[?#].*$/', '', $url );
		if ( ! is_string( $clean_url ) || '' === $clean_url ) {
			return 0;
		}

		$urls = array( $clean_url );
		// WordPress stores the original/scaled upload path, not each generated
		// `-WIDTHxHEIGHT` file, in the attachment's _wp_attached_file metadata.
		$full_size_url = preg_replace( '/-\d+x\d+(?=\.(?:avif|gif|jpe?g|png|webp)$)/i', '', $clean_url );
		if ( is_string( $full_size_url ) && $full_size_url !== $clean_url ) {
			$urls[] = $full_size_url;
		}

		foreach ( $urls as $candidate_url ) {
			if ( ! array_key_exists( $candidate_url, $this->attachment_ids_by_url ) ) {
				$attachment_id = (int) attachment_url_to_postid( $candidate_url );
				if ( 0 < $attachment_id && 'attachment' !== get_post_type( $attachment_id ) ) {
					$attachment_id = 0;
				}
				$this->attachment_ids_by_url[ $candidate_url ] = $attachment_id;
			}

			if ( 0 < $this->attachment_ids_by_url[ $candidate_url ] ) {
				return $this->attachment_ids_by_url[ $candidate_url ];
			}
		}

		return 0;
	}

	/**
	 * Whether a node already sits inside an EU AI Label wrapper.
	 *
	 * Prevents double-wrapping images that a shortcode already rendered through
	 * wp_get_attachment_image (e.g. the classic [gallery]).
	 *
	 * @param DOMNode $node Node to test.
	 * @return bool
	 */
	private function within_wrap( DOMNode $node ) {
		for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
			$class = $parent->getAttribute( 'class' );
			if ( '' !== $class && false !== strpos( $class, 'eu-ai-label-wrap' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build the accessible badge markup shared by both rendering paths.
	 *
	 * Exposed to assistive tech as a single labelled image (role="img" +
	 * aria-label) so screen readers announce the disclosure once; the
	 * duplicated visible text is hidden from the accessibility tree. The text
	 * is always rendered (no hover/click) and the style is fixed for contrast.
	 * The output is well-formed XML so it can be parsed by DOMDocument.
	 *
	 * @param string $status        Enum status.
	 * @param string $text          Localized badge text.
	 * @param int    $attachment_id Attachment ID (used by the Pro variant filter).
	 * @return string
	 */
	private function badge_markup( $status, $text, $attachment_id = 0 ) {
		$is_pro = EU_AI_Label_License::is_pro();

		$attrs = array(
			'class'      => 'eu-ai-label-badge eu-ai-label--' . sanitize_html_class( $status ),
			'role'       => 'img',
			'aria-label' => $text,
		);

		if ( $is_pro ) {
			/**
			 * Filter the badge variant on Pro sites.
			 *
			 * The Pro add-on hooks this to return an auto-contrast "adaptive"
			 * variant computed from the underlying image. Free sites never run
			 * this and always use the fixed, WCAG-safe default. Returning
			 * 'default' (or an empty string) emits no extra class.
			 *
			 * @param string $variant       Variant slug.
			 * @param int    $attachment_id Attachment ID.
			 */
			$variant = (string) apply_filters( 'eu_ai_label_badge_variant', 'default', (int) $attachment_id );
			if ( '' !== $variant && 'default' !== $variant ) {
				$attrs['class'] .= ' eu-ai-label--variant-' . sanitize_html_class( $variant );
			}

			/**
			 * Filter the badge span attributes on Pro sites.
			 *
			 * The Pro alteration-details feature adds `tabindex` and
			 * `aria-describedby` here so the badge can anchor an accessible
			 * tooltip. Free sites always emit the fixed attribute set.
			 *
			 * @param array<string,string> $attrs         Attribute name => value map.
			 * @param int                  $attachment_id Attachment ID.
			 * @param string               $status        Enum status.
			 */
			$attrs = (array) apply_filters( 'eu_ai_label_badge_attrs', $attrs, (int) $attachment_id, $status );
		}

		$attr_html = '';
		foreach ( $attrs as $name => $value ) {
			$name = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $name ) );
			if ( '' === $name || ! is_scalar( $value ) ) {
				continue;
			}
			$attr_html .= sprintf( ' %s="%s"', $name, esc_attr( (string) $value ) );
		}

		$icon = '';
		if ( $is_pro ) {
			/**
			 * Filter decorative markup displayed before the badge text on Pro sites.
			 *
			 * Returned markup MUST be well-formed XML and hidden from assistive
			 * technology; the badge's aria-label remains its accessible name.
			 *
			 * @param string $icon          Icon markup.
			 * @param int    $attachment_id Attachment ID.
			 * @param string $status        Enum status.
			 */
			$icon = (string) apply_filters( 'eu_ai_label_badge_icon', '', (int) $attachment_id, $status );
		}

		$markup = sprintf(
			'<span%1$s>%2$s<span class="eu-ai-label-text" aria-hidden="true">%3$s</span></span>',
			$attr_html,
			$icon,
			esc_html( $text )
		);

		if ( $is_pro ) {
			/**
			 * Filter the final badge markup on Pro sites.
			 *
			 * The Pro alteration-details feature appends a `role="tooltip"`
			 * sibling span here. The returned markup MUST stay well-formed XML:
			 * the content path inserts it via DOMDocumentFragment::appendXML().
			 *
			 * @param string $markup        Badge markup.
			 * @param int    $attachment_id Attachment ID.
			 * @param string $status        Enum status.
			 */
			$markup = (string) apply_filters( 'eu_ai_label_badge_markup', $markup, (int) $attachment_id, $status );
		}

		return $markup;
	}
}
