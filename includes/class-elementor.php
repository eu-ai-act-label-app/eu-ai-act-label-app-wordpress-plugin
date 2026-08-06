<?php
/**
 * Elementor compatibility layer.
 *
 * @package EU_AI_Label
 */

defined( 'ABSPATH' ) || exit;

/**
 * Covers Elementor rendering paths that bypass wp_get_attachment_image().
 */
class EU_AI_Label_Elementor {

	/**
	 * Shared front-end renderer.
	 *
	 * @var EU_AI_Label_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param EU_AI_Label_Renderer $renderer Shared renderer instance.
	 */
	public function __construct( EU_AI_Label_Renderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Register Elementor hooks. They are harmless when Elementor is inactive.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'elementor/widget/render_content', array( $this, 'filter_widget_content' ), 20, 2 );
		add_action( 'elementor/frontend/before_render', array( $this, 'label_background' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'front_assets' ), 14 );
	}

	/**
	 * Process raw widget HTML from galleries/carousels that does not use the
	 * normal WordPress attachment-image function.
	 *
	 * @param string $content Rendered widget HTML.
	 * @param object $widget  Elementor widget instance (unused).
	 * @return string
	 */
	public function filter_widget_content( $content, $widget = null ) {
		$featured_image_id = $this->featured_image_id( $widget );

		return $this->renderer->filter_content( $content, $featured_image_id );
	}

	/**
	 * Resolve the current post thumbnail for featured-image widgets that render
	 * a raw URL instead of calling wp_get_attachment_image().
	 *
	 * Keeping an explicit allow-list prevents a plain image widget from being
	 * mislabeled with the current post's featured-image status. Sites can extend
	 * the list for another compatible third-party featured-image widget.
	 *
	 * @param object|null $widget Elementor widget instance.
	 * @return int Attachment ID, or zero when the fallback must not be used.
	 */
	private function featured_image_id( $widget ) {
		if ( ! is_object( $widget ) || ! method_exists( $widget, 'get_name' ) ) {
			return 0;
		}

		$widget_name = sanitize_key( (string) $widget->get_name() );
		$widgets     = array(
			'theme-post-featured-image',
			'wpr-post-media',
		);

		/**
		 * Filter Elementor widget names that render the current post thumbnail.
		 *
		 * @param string[] $widgets Known featured-image widget names.
		 * @param object   $widget  Current Elementor widget instance.
		 */
		$widgets = (array) apply_filters( 'eu_ai_label_elementor_featured_image_widgets', $widgets, $widget );
		$widgets = array_map( 'sanitize_key', $widgets );

		if ( ! in_array( $widget_name, $widgets, true ) ) {
			return 0;
		}

		return (int) get_post_thumbnail_id();
	}

	/**
	 * Add a badge payload for a labeled static Elementor background image.
	 *
	 * Backgrounds have no <img> element, so PHP adds the exact badge markup as
	 * data and a tiny front-end helper moves it into the rendered wrapper. A
	 * slideshow is deliberately ignored because one fixed badge cannot describe
	 * whichever image is currently visible.
	 *
	 * @param object $element Elementor element instance.
	 * @return void
	 */
	public function label_background( $element ) {
		if ( ! is_object( $element ) || ! method_exists( $element, 'get_settings_for_display' ) || ! method_exists( $element, 'add_render_attribute' ) ) {
			return;
		}

		$backgrounds = array(
			array( 'background_image', 'background_background' ),
			array( 'background_overlay_image', 'background_overlay_background' ),
			array( '_background_image', '_background_background' ),
			array( '_background_overlay_image', '_background_overlay_background' ),
		);

		foreach ( $backgrounds as $keys ) {
			$type = $element->get_settings_for_display( $keys[1] );
			if ( 'slideshow' === $type ) {
				continue;
			}

			$image = $element->get_settings_for_display( $keys[0] );
			if ( ! is_array( $image ) || empty( $image['id'] ) ) {
				continue;
			}

			$badge = $this->renderer->badge_markup_for_attachment( (int) $image['id'] );
			if ( '' === $badge ) {
				continue;
			}

			$element->add_render_attribute(
				'_wrapper',
				array(
					'class'                       => 'eu-ai-label-background-wrap ' . EU_AI_Label_Renderer::position_class(),
					'data-eu-ai-label-background' => wp_json_encode( $badge ),
				)
			);
			break;
		}
	}

	/**
	 * Load the background-image helper on Elementor sites.
	 *
	 * @return void
	 */
	public function front_assets() {
		if ( ! defined( 'ELEMENTOR_VERSION' ) && ! did_action( 'elementor/loaded' ) ) {
			return;
		}

		wp_enqueue_script(
			'eu-ai-label-elementor',
			EU_AI_LABEL_URL . 'assets/js/elementor.js',
			array(),
			EU_AI_LABEL_VERSION,
			true
		);
	}
}
