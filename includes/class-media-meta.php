<?php
/**
 * Media Library meta field for the AI label status.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds an enum dropdown to the attachment editor and persists it to post meta.
 */
class EU_AI_Label_Media_Meta {

	/**
	 * Field name used in the attachment edit form.
	 *
	 * @var string
	 */
	const FIELD = 'eu_ai_label_status';

	/**
	 * Media list-table column key.
	 *
	 * @var string
	 */
	const COLUMN = 'eu_ai_label';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_field' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'register_meta' ) );
		add_filter( 'manage_media_columns', array( $this, 'add_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_column' ), 10, 2 );

		// Free-plan cap, enforced at the meta layer so every write path is
		// covered (editor, REST, WP-CLI). Deletes are never blocked.
		add_filter( 'add_post_metadata', array( $this, 'enforce_label_cap' ), 10, 4 );
		add_filter( 'update_post_metadata', array( $this, 'enforce_label_cap' ), 10, 4 );
	}

	/**
	 * Total number of labeled attachments (any status).
	 *
	 * @return int
	 */
	public static function labeled_count() {
		return array_sum( EU_AI_Label_Settings::get_status_counts() );
	}

	/**
	 * Whether an attachment may (still) be labeled under the current plan.
	 *
	 * Pro is unlimited. On free, already-labeled attachments stay editable
	 * (changing or clearing a label never increases the count); unlabeled
	 * attachments require a free slot under FREE_LABEL_LIMIT.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function can_label( $attachment_id ) {
		if ( EU_AI_Label_License::is_pro() ) {
			return true;
		}

		if ( metadata_exists( 'post', (int) $attachment_id, EU_AI_Label_Plugin::META_KEY ) ) {
			return true;
		}

		return self::labeled_count() < EU_AI_Label_License::FREE_LABEL_LIMIT;
	}

	/**
	 * Meta-layer guard: block label writes beyond the free-plan cap.
	 *
	 * @param null|bool $check      Short-circuit flag from earlier filters.
	 * @param int       $object_id  Post ID.
	 * @param string    $meta_key   Meta key being written.
	 * @param mixed     $meta_value New value (unused).
	 * @return null|bool False to block the write, $check untouched otherwise.
	 */
	public function enforce_label_cap( $check, $object_id, $meta_key, $meta_value ) {
		if ( null !== $check ) {
			return $check;
		}

		if ( EU_AI_Label_Plugin::META_KEY !== $meta_key || 'attachment' !== get_post_type( (int) $object_id ) ) {
			return $check;
		}

		return self::can_label( (int) $object_id ) ? $check : false;
	}

	/**
	 * Register the meta key so it is sanitized and available via REST/JS.
	 *
	 * @return void
	 */
	public function register_meta() {
		register_post_meta(
			'attachment',
			EU_AI_Label_Plugin::META_KEY,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => array( __CLASS__, 'sanitize_status' ),
				'auth_callback'     => function () {
					return current_user_can( 'upload_files' );
				},
			)
		);
	}

	/**
	 * Sanitize a submitted status against the enum whitelist.
	 *
	 * Unknown values collapse to an empty string (treated as "no badge").
	 *
	 * @param mixed $value Candidate status.
	 * @return string Valid enum value or empty string.
	 */
	public static function sanitize_status( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';

		return EU_AI_Label_Plugin::is_valid_status( $value ) ? $value : '';
	}

	/**
	 * Human-readable labels for each enum value, localized.
	 *
	 * @return array<string,string>
	 */
	public static function status_choices() {
		return array(
			''                                      => __( '— No label —', 'eu-ai-label' ),
			EU_AI_Label_Plugin::STATUS_NO_AI        => __( 'No AI', 'eu-ai-label' ),
			EU_AI_Label_Plugin::STATUS_AI_EDITED    => __( 'AI modified', 'eu-ai-label' ),
			EU_AI_Label_Plugin::STATUS_AI_GENERATED => __( 'AI generated', 'eu-ai-label' ),
		);
	}

	/**
	 * Status chip markup shared by the Media Library column and the audit log.
	 *
	 * Mirrors the front-end badge pill so editors recognize the same mark
	 * across admin screens. Returns escaped, safe HTML.
	 *
	 * @param string $status Enum status ('' reads as "No label").
	 * @return string
	 */
	public static function status_chip( $status ) {
		$choices = self::status_choices();

		if ( '' === $status || ! isset( $choices[ $status ] ) ) {
			return sprintf(
				'<span class="eu-ai-label-chip eu-ai-label-chip--none">%s</span>',
				esc_html__( 'No label', 'eu-ai-label' )
			);
		}

		return sprintf(
			'<span class="eu-ai-label-chip eu-ai-label-chip--%1$s">%2$s</span>',
			sanitize_html_class( $status ),
			esc_html( $choices[ $status ] )
		);
	}

	/**
	 * Add the AI label column to the Media Library list table.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function add_column( $columns ) {
		$columns[ self::COLUMN ] = __( 'AI label', 'eu-ai-label' );

		return $columns;
	}

	/**
	 * Render the AI label column for one attachment row.
	 *
	 * Unlabeled rows print a muted dash (most media is unlabeled — a chip on
	 * every row would drown out the real disclosures); labeled rows get the
	 * status chip.
	 *
	 * @param string $column_name   Column being rendered.
	 * @param int    $attachment_id Attachment ID.
	 * @return void
	 */
	public function render_column( $column_name, $attachment_id ) {
		if ( self::COLUMN !== $column_name ) {
			return;
		}

		$status = get_post_meta( (int) $attachment_id, EU_AI_Label_Plugin::META_KEY, true );

		if ( ! EU_AI_Label_Plugin::is_valid_status( $status ) ) {
			echo '<span class="eu-ai-label-column-empty" aria-hidden="true">&#8212;</span>';
			return;
		}

		echo wp_kses_post( self::status_chip( $status ) );
	}

	/**
	 * Inject the dropdown into the attachment edit fields.
	 *
	 * @param array   $form_fields Existing fields.
	 * @param WP_Post $post        Attachment post.
	 * @return array
	 */
	public function add_field( $form_fields, $post ) {
		$current = get_post_meta( $post->ID, EU_AI_Label_Plugin::META_KEY, true );
		if ( ! EU_AI_Label_Plugin::is_valid_status( $current ) ) {
			$current = '';
		}

		$capped = ! self::can_label( (int) $post->ID );

		$html = sprintf(
			'<select name="attachments[%1$d][%2$s]" id="attachments-%1$d-%2$s"%3$s>',
			(int) $post->ID,
			esc_attr( self::FIELD ),
			$capped ? ' disabled="disabled"' : ''
		);
		foreach ( self::status_choices() as $value => $label ) {
			$html .= sprintf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		$html .= '</select>';

		$helps = __( 'Declare whether this image was generated or edited by AI (EU AI Act Art. 50).', 'eu-ai-label' );

		if ( $capped ) {
			$helps = sprintf(
				/* translators: %d: number of images the free plan can label. */
				__( 'Free plan limit reached: %d images are already labeled. Upgrade to Pro for unlimited labels.', 'eu-ai-label' ),
				EU_AI_Label_License::FREE_LABEL_LIMIT
			);
		} elseif ( EU_AI_Label_License::is_free() ) {
			$helps .= ' ' . sprintf(
				/* translators: 1: number of labeled images, 2: free-plan limit. */
				__( '%1$d of %2$d free image labels used.', 'eu-ai-label' ),
				self::labeled_count(),
				EU_AI_Label_License::FREE_LABEL_LIMIT
			);
		}

		$form_fields[ self::FIELD ] = array(
			'label' => __( 'AI label', 'eu-ai-label' ),
			'input' => 'html',
			'html'  => $html,
			'helps' => $helps,
		);

		return $form_fields;
	}

	/**
	 * Persist the submitted status to post meta.
	 *
	 * @param array $post       Attachment post data (unmodified here).
	 * @param array $attachment Submitted attachment fields.
	 * @return array
	 */
	public function save_field( $post, $attachment ) {
		if ( ! isset( $attachment[ self::FIELD ] ) ) {
			return $post;
		}

		if ( ! current_user_can( 'edit_post', (int) $post['ID'] ) ) {
			return $post;
		}

		$status = self::sanitize_status( $attachment[ self::FIELD ] );

		if ( '' === $status ) {
			delete_post_meta( (int) $post['ID'], EU_AI_Label_Plugin::META_KEY );
		} elseif ( self::can_label( (int) $post['ID'] ) ) {
			// The meta-layer cap filter is the backstop; checking here too keeps
			// the editor path from attempting writes it knows will be rejected.
			update_post_meta( (int) $post['ID'], EU_AI_Label_Plugin::META_KEY, $status );
		}

		return $post;
	}

	/**
	 * Register the shared admin stylesheet (chips, settings cards).
	 *
	 * Registered once here; the screens that need it (Media Library, the
	 * settings page) enqueue the handle.
	 *
	 * @return void
	 */
	public static function register_admin_style() {
		if ( wp_style_is( 'eu-ai-label-admin', 'registered' ) ) {
			return;
		}

		wp_register_style(
			'eu-ai-label-admin',
			EU_AI_LABEL_URL . 'assets/css/admin.css',
			array(),
			EU_AI_LABEL_VERSION
		);
	}

	/**
	 * Enqueue the grid-view helper script inside the media modal.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		self::register_admin_style();

		if ( 'upload.php' === $hook_suffix ) {
			wp_enqueue_style( 'eu-ai-label-admin' );
		}

		// Ensure the media modal machinery is present (upload.php, post editor, etc.).
		if ( ! wp_script_is( 'media-editor', 'enqueued' ) && ! did_action( 'wp_enqueue_media' ) ) {
			// Load on screens that use the media modal.
			$media_screens = array( 'upload.php', 'post.php', 'post-new.php' );
			if ( ! in_array( $hook_suffix, $media_screens, true ) ) {
				return;
			}
		}

		wp_enqueue_script(
			'eu-ai-label-media-meta',
			EU_AI_LABEL_URL . 'assets/js/media-meta.js',
			array( 'jquery' ),
			EU_AI_LABEL_VERSION,
			true
		);
	}
}
