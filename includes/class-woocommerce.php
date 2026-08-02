<?php
/**
 * WooCommerce product-image integration.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds product-focused controls and declares WooCommerce compatibility.
 */
class EU_AI_Label_WooCommerce {

	/**
	 * Product editor nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'eu_ai_label_save_product_images';

	/**
	 * Product editor nonce field.
	 *
	 * @var string
	 */
	const NONCE_FIELD = 'eu_ai_label_product_images_nonce';

	/**
	 * Product editor field name.
	 *
	 * @var string
	 */
	const FIELD = 'eu_ai_label_product_images';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_compatibility' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_images' ), 30, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Declare compatibility with High-Performance Order Storage.
	 *
	 * The plugin does not read or write order data.
	 *
	 * @return void
	 */
	public static function declare_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				EU_AI_LABEL_FILE,
				true
			);
		}
	}

	/**
	 * Add a native WooCommerce Product Data tab.
	 *
	 * @param array<string,array<string,mixed>> $tabs Product Data tabs.
	 * @return array<string,array<string,mixed>>
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['eu_ai_label'] = array(
			'label'    => __( 'AI image labels', 'eu-ai-label' ),
			'target'   => 'eu_ai_label_product_data',
			'class'    => array(),
			'priority' => 85,
		);

		return $tabs;
	}

	/**
	 * Return the featured and gallery attachment IDs for a product.
	 *
	 * @param int $product_id Product post ID.
	 * @return int[]
	 */
	public static function product_image_ids( $product_id ) {
		$image_ids = array();
		$featured  = (int) get_post_thumbnail_id( $product_id );

		if ( $featured > 0 ) {
			$image_ids[] = $featured;
		}

		$gallery = (string) get_post_meta( $product_id, '_product_image_gallery', true );
		if ( '' !== $gallery ) {
			foreach ( explode( ',', $gallery ) as $image_id ) {
				$image_id = (int) $image_id;
				if ( $image_id > 0 ) {
					$image_ids[] = $image_id;
				}
			}
		}

		return array_values( array_unique( $image_ids ) );
	}

	/**
	 * Render native Product Data controls for every product image.
	 *
	 * @return void
	 */
	public function render_product_data_panel() {
		global $post;

		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return;
		}

		$image_ids = self::product_image_ids( (int) $post->ID );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div id="eu_ai_label_product_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group eu-ai-label-product-data__intro">
				<p>
					<strong><?php echo esc_html__( 'AI labels for product images', 'eu-ai-label' ); ?></strong><br />
					<span class="description"><?php echo esc_html__( 'Set the disclosure shown anywhere each product image appears in your store.', 'eu-ai-label' ); ?></span>
					<span class="description"><?php echo esc_html__( 'After adding or removing product images, save the product once to refresh this list.', 'eu-ai-label' ); ?></span>
					<?php if ( class_exists( 'EU_AI_Label_Pro_Label_Studio' ) ) : ?>
						<a class="button eu-ai-label-product-data__studio-link" href="<?php echo esc_url( admin_url( 'options-general.php?page=' . EU_AI_Label_Settings::PAGE . '&tab=' . EU_AI_Label_Pro_Label_Studio::TAB ) ); ?>"><?php echo esc_html__( 'Open Label Studio', 'eu-ai-label' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
		<?php

		if ( empty( $image_ids ) ) {
			echo '<div class="eu-ai-label-product-data__empty"><p>' . esc_html__( 'Add a product image or gallery image, then save the product to configure its AI labels here.', 'eu-ai-label' ) . '</p></div>';
			echo '</div>';
			return;
		}

		echo '<div class="eu-ai-label-product-images">';

		foreach ( $image_ids as $index => $image_id ) {
			$this->render_image_control( $image_id, 0 === $index );
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render one product-image control.
	 *
	 * @param int  $image_id Attachment ID.
	 * @param bool $featured Whether this is the featured product image.
	 * @return void
	 */
	private function render_image_control( $image_id, $featured ) {
		$status = get_post_meta( $image_id, EU_AI_Label_Plugin::META_KEY, true );
		if ( ! EU_AI_Label_Plugin::is_valid_status( $status ) ) {
			$status = '';
		}

		$details = class_exists( 'EU_AI_Label_Pro_Details' )
			? EU_AI_Label_Pro_Details::get_details( $image_id )
			: array();
		$title   = get_the_title( $image_id );
		$title   = '' !== $title ? $title : __( 'Untitled image', 'eu-ai-label' );
		?>
		<div class="eu-ai-label-product-image">
			<div class="eu-ai-label-product-image__preview">
				<?php echo wp_kses_post( wp_get_attachment_image( $image_id, array( 96, 96 ) ) ); ?>
			</div>
			<div class="eu-ai-label-product-image__fields">
				<div class="eu-ai-label-product-image__heading">
					<div>
						<strong><?php echo esc_html( $featured ? __( 'Product image', 'eu-ai-label' ) : __( 'Gallery image', 'eu-ai-label' ) ); ?></strong>
						<span class="description"><?php echo esc_html( $title ); ?></span>
					</div>
					<span class="eu-ai-label-product-image__status" aria-live="polite">
						<?php echo wp_kses_post( EU_AI_Label_Media_Meta::status_chip( $status ) ); ?>
					</span>
				</div>
				<label for="eu-ai-label-product-image-<?php echo esc_attr( (string) $image_id ); ?>">
					<span><?php echo esc_html__( 'AI label', 'eu-ai-label' ); ?></span>
					<select class="wc-enhanced-select eu-ai-label-product-image__select" id="eu-ai-label-product-image-<?php echo esc_attr( (string) $image_id ); ?>" name="<?php echo esc_attr( self::FIELD ); ?>[<?php echo esc_attr( (string) $image_id ); ?>][status]">
						<?php foreach ( EU_AI_Label_Media_Meta::status_choices() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<?php if ( class_exists( 'EU_AI_Label_Pro_Details' ) ) : ?>
					<fieldset class="eu-ai-label-product-image__details">
						<legend><?php echo esc_html__( 'AI alterations', 'eu-ai-label' ); ?></legend>
						<?php foreach ( EU_AI_Label_Pro_Details::choices() as $key => $label ) : ?>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>[<?php echo esc_attr( (string) $image_id ); ?>][details][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $details, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save labels for the product's featured and gallery images.
	 *
	 * @param int     $post_id Product post ID.
	 * @param WP_Post $post    Product post.
	 * @return void
	 */
	public function save_product_images( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| 'product' !== $post->post_type
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$submitted = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] )
			? map_deep( wp_unslash( $_POST[ self::FIELD ] ), 'sanitize_text_field' )
			: array();

		foreach ( self::product_image_ids( $post_id ) as $image_id ) {
			$image_data = isset( $submitted[ $image_id ] ) && is_array( $submitted[ $image_id ] )
				? $submitted[ $image_id ]
				: array();
			$status     = isset( $image_data['status'] )
				? EU_AI_Label_Media_Meta::sanitize_status( $image_data['status'] )
				: '';

			if ( '' === $status ) {
				delete_post_meta( $image_id, EU_AI_Label_Plugin::META_KEY );
			} else {
				update_post_meta( $image_id, EU_AI_Label_Plugin::META_KEY, $status );
			}

			if ( class_exists( 'EU_AI_Label_Pro_Details' ) ) {
				$details = EU_AI_Label_Plugin::status_has_badge( $status ) && isset( $image_data['details'] )
					? EU_AI_Label_Pro_Details::sanitize_details( $image_data['details'] )
					: array();

				if ( empty( $details ) ) {
					delete_post_meta( $image_id, EU_AI_Label_Pro_Details::META_KEY );
				} else {
					update_post_meta( $image_id, EU_AI_Label_Pro_Details::META_KEY, $details );
				}
			}
		}
	}

	/**
	 * Enqueue shared admin styling on product edit screens.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		EU_AI_Label_Media_Meta::register_admin_style();
		wp_enqueue_style( 'eu-ai-label-admin' );
		wp_enqueue_script(
			'eu-ai-label-woocommerce-product-editor',
			EU_AI_LABEL_URL . 'assets/js/woocommerce-product-editor.js',
			array( 'jquery' ),
			EU_AI_LABEL_VERSION,
			true
		);
	}
}
