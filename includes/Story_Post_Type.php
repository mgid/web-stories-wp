<?php
/**
 * Class Story_Post_Type.
 *
 * @package   Google\Web_Stories
 * @copyright 2020 Google LLC
 * @license   https://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
 * @link      https://github.com/google/web-stories-wp
 */

/**
 * Copyright 2020 Google LLC
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Web_Stories;

use Google\Web_Stories\Model\Story;
use Google\Web_Stories\REST_API\Stories_Controller;
use Google\Web_Stories\Story_Renderer\Embed;
use Google\Web_Stories\Traits\Assets;
use Google\Web_Stories\Traits\Publisher;
use Google\Web_Stories\Traits\Types;
use WP_Post;
use WP_Role;
use WP_Post_Type;
use WP_Screen;

/**
 * Class Story_Post_Type.
 */
class Story_Post_Type {
	use Publisher;
	use Types;
	use Assets;

	/**
	 * The slug of the stories post type.
	 *
	 * @var string
	 */
	const POST_TYPE_SLUG = 'web-story';

	/**
	 * Slug of the AMP validated URL post type.
	 *
	 * @var string
	 */
	const AMP_VALIDATED_URL_POST_TYPE = 'amp_validated_url';

	/**
	 * Web Stories editor script handle.
	 *
	 * @var string
	 */
	const WEB_STORIES_SCRIPT_HANDLE = 'edit-story';

	/**
	 * The rewrite slug for this post type.
	 *
	 * @var string
	 */
	const REWRITE_SLUG = 'stories';

	/**
	 * Style Present options name.
	 *
	 * @var string
	 */
	const STYLE_PRESETS_OPTION = 'web_stories_style_presets';

	/**
	 * Experiments instance.
	 *
	 * @var Experiments Experiments instance.
	 */
	private $experiments;

	/**
	 * Dashboard constructor.
	 *
	 * @param Experiments $experiments Experiments instance.
	 */
	public function __construct( Experiments $experiments ) {
		$this->experiments = $experiments;
	}

	/**
	 * Registers the post type for stories.
	 *
	 * @todo refactor
	 *
	 * @return void
	 */
	public function init() {
		register_post_type(
			self::POST_TYPE_SLUG,
			[
				'labels'                => [
					'name'                     => _x( 'Stories', 'post type general name', 'web-stories' ),
					'singular_name'            => _x( 'Story', 'post type singular name', 'web-stories' ),
					'add_new'                  => _x( 'Add New', 'story', 'web-stories' ),
					'add_new_item'             => __( 'Add New Story', 'web-stories' ),
					'edit_item'                => __( 'Edit Story', 'web-stories' ),
					'new_item'                 => __( 'New Story', 'web-stories' ),
					'view_item'                => __( 'View Story', 'web-stories' ),
					'view_items'               => __( 'View Stories', 'web-stories' ),
					'search_items'             => __( 'Search Stories', 'web-stories' ),
					'not_found'                => __( 'No stories found.', 'web-stories' ),
					'not_found_in_trash'       => __( 'No stories found in Trash.', 'web-stories' ),
					'all_items'                => __( 'All Stories', 'web-stories' ),
					'archives'                 => __( 'Story Archives', 'web-stories' ),
					'attributes'               => __( 'Story Attributes', 'web-stories' ),
					'insert_into_item'         => __( 'Insert into story', 'web-stories' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this story', 'web-stories' ),
					'featured_image'           => __( 'Featured Image', 'web-stories' ),
					'set_featured_image'       => __( 'Set featured image', 'web-stories' ),
					'remove_featured_image'    => __( 'Remove featured image', 'web-stories' ),
					'use_featured_image'       => __( 'Use as featured image', 'web-stories' ),
					'filter_items_list'        => __( 'Filter stories list', 'web-stories' ),
					'items_list_navigation'    => __( 'Stories list navigation', 'web-stories' ),
					'items_list'               => __( 'Stories list', 'web-stories' ),
					'item_published'           => __( 'Story published.', 'web-stories' ),
					'item_published_privately' => __( 'Story published privately.', 'web-stories' ),
					'item_reverted_to_draft'   => __( 'Story reverted to draft.', 'web-stories' ),
					'item_scheduled'           => __( 'Story scheduled', 'web-stories' ),
					'item_updated'             => __( 'Story updated.', 'web-stories' ),
					'menu_name'                => _x( 'Stories', 'admin menu', 'web-stories' ),
					'name_admin_bar'           => _x( 'Story', 'add new on admin bar', 'web-stories' ),
				],
				'menu_icon'             => $this->get_post_type_icon(),
				'supports'              => [
					'title', // Used for amp-story[title].
					'author',
					'editor',
					'excerpt',
					'thumbnail', // Used for poster images.
					'revisions', // Without this, the REST API will return 404 for an autosave request.
				],
				'rewrite'               => [
					'slug' => self::REWRITE_SLUG,
				],
				'public'                => true,
				'has_archive'           => true,
				'show_ui'               => true,
				'show_in_rest'          => true,
				'rest_controller_class' => Stories_Controller::class,
				'capability_type'       => [ 'web-story', 'web-stories' ],
				'map_meta_cap'          => true,
			]
		);

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_filter( 'show_admin_bar', [ $this, 'show_admin_bar' ] ); // phpcs:ignore WordPressVIPMinimum.UserExperience.AdminBarRemoval.RemovalDetected
		add_filter( 'replace_editor', [ $this, 'replace_editor' ], 10, 2 );
		add_filter( 'use_block_editor_for_post_type', [ $this, 'filter_use_block_editor_for_post_type' ], 10, 2 );

		add_filter( 'rest_' . self::POST_TYPE_SLUG . '_collection_params', [ $this, 'filter_rest_collection_params' ], 10, 2 );

		// Select the single-web-story.php template for Stories.
		add_filter( 'template_include', [ $this, 'filter_template_include' ], PHP_INT_MAX );

		add_filter( 'option_amp-options', [ $this, 'filter_amp_options' ] );
		add_filter( 'amp_supportable_post_types', [ $this, 'filter_supportable_post_types' ] );

		add_filter( '_wp_post_revision_fields', [ $this, 'filter_revision_fields' ], 10, 2 );

		add_filter( 'the_content', [ $this, 'embed_player' ], PHP_INT_MAX );
		add_filter( 'the_excerpt', [ $this, 'embed_player' ], PHP_INT_MAX );

		// See https://github.com/Automattic/jetpack/blob/4b85be883b3c584c64eeb2fb0f3fcc15dabe2d30/modules/custom-post-types/portfolios.php#L80.
		if ( defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			add_filter( 'wpcom_sitemap_post_types', [ $this, 'add_to_jetpack_sitemap' ] );
		} else {
			add_filter( 'jetpack_sitemap_post_types', [ $this, 'add_to_jetpack_sitemap' ] );
		}
	}

	/**
	 * Adds story capabilities to default user roles.
	 *
	 * This gives WordPress site owners more granular control over story management,
	 * as they can customize this to their liking.
	 *
	 * @return void
	 */
	public function add_caps_to_roles() {
		$post_type_object = get_post_type_object( self::POST_TYPE_SLUG );

		if ( ! $post_type_object ) {
			return;
		}

		$all_capabilities = array_values( (array) $post_type_object->cap );

		$administrator = get_role( 'administrator' );
		$editor        = get_role( 'editor' );
		$author        = get_role( 'author' );
		$contributor   = get_role( 'contributor' );

		if ( $administrator instanceof WP_Role ) {
			foreach ( $all_capabilities as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		if ( $editor instanceof WP_Role ) {
			foreach ( $all_capabilities as $cap ) {
				$editor->add_cap( $cap );
			}
		}

		if ( $author instanceof WP_Role ) {
			$author->add_cap( 'edit_web-stories' );
			$author->add_cap( 'edit_published_web-stories' );
			$author->add_cap( 'delete_web-stories' );
			$author->add_cap( 'delete_published_web-stories' );
			$author->add_cap( 'publish_web-stories' );
		}

		if ( $contributor instanceof WP_Role ) {
			$contributor->add_cap( 'edit_web-stories' );
			$contributor->add_cap( 'delete_web-stories' );
		}

		/**
		 * Fires when adding the custom capabilities to existing roles.
		 *
		 * Can be used to add the capabilities to other, custom roles.
		 *
		 * @param array $all_capabilities List of all post type capabilities, for reference.
		 */
		do_action( 'web_stories_add_capabilities', $all_capabilities );
	}

	/**
	 * Base64 encoded svg icon.
	 *
	 * @return string Base64-encoded SVG icon.
	 */
	protected function get_post_type_icon() {
		return 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjMiIGhlaWdodD0iNTUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0iTTAgOGg0djM5SDBWOHpNNTkgOGg0djM5aC00Vjh6TTUwIDBIMTN2NTVoMzdWMHoiIGZpbGw9ImN1cnJlbnRDb2xvciIvPjwvc3ZnPg==';
	}

	/**
	 * Get the post type for the current request.
	 *
	 * @return string|null
	 */
	protected function get_request_post_type() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( did_action( 'wp' ) && is_singular() ) {
			$post_type = get_post_type( get_queried_object_id() );
			return $post_type ?: null;
		}

		if (
			is_admin()
			&&
			isset( $_GET['action'], $_GET['post'] )
			&&
			'amp_validate' === $_GET['action']
			&&
			get_post_type( (int) $_GET['post'] ) === self::AMP_VALIDATED_URL_POST_TYPE
		) {
			return $this->get_validated_url_post_type( (int) $_GET['post'] );
		}

		$current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $current_screen instanceof WP_Screen ) {
			$current_post = get_post();

			if ( self::AMP_VALIDATED_URL_POST_TYPE === $current_screen->post_type && $current_post instanceof WP_Post && $current_post->post_type === $current_screen->post_type ) {
				$validated_url_post_type = $this->get_validated_url_post_type( $current_post->ID );
				if ( $validated_url_post_type ) {
					return $validated_url_post_type;
				}
			}

			if ( $current_screen->post_type ) {
				return $current_screen->post_type;
			}

			return null;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), '/web-stories/v1/web-story/' ) ) {
			return self::POST_TYPE_SLUG;
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return null;
	}

	/**
	 * Get the singular post type which is the queried object for the given validated URL post.
	 *
	 * @param int $post_id Post ID for Validated URL Post.
	 * @return string|null Post type or null if validated URL is not for a singular post.
	 */
	protected function get_validated_url_post_type( $post_id ) {
		if ( empty( $post_id ) ) {
			return null;
		}

		$post = get_post( $post_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return null;
		}

		if ( self::AMP_VALIDATED_URL_POST_TYPE !== $post->post_type ) {
			return null;
		}

		$queried_object = get_post_meta( $post->ID, '_amp_queried_object', true );
		if ( isset( $queried_object['id'], $queried_object['type'] ) && 'post' === $queried_object['type'] ) {
			$post_type = get_post_type( $queried_object['id'] );
			if ( $post_type ) {
				return $post_type;
			}
		}
		return null;
	}

	/**
	 * Filter AMP options to force Standard mode (AMP-first) when a web story is being requested.
	 *
	 * @param array $options Options.
	 * @return array Filtered options.
	 */
	public function filter_amp_options( $options ) {
		if ( $this->get_request_post_type() === self::POST_TYPE_SLUG ) {
			$options['theme_support']          = 'standard';
			$options['supported_post_types'][] = self::POST_TYPE_SLUG;
			$options['supported_templates'][]  = 'is_singular';
		}
		return $options;
	}

	/**
	 * Filter the post types which are supportable.
	 *
	 * Remove web-stories from the list unless the currently requested post type is for a web-story. This is done in
	 * order to hide stories from the list of supportable post types on the AMP Settings screen.
	 *
	 * @param string[] $post_types Post types.
	 * @return array Supportable post types.
	 */
	public function filter_supportable_post_types( $post_types ) {
		if ( $this->get_request_post_type() === self::POST_TYPE_SLUG ) {
			$post_types = array_merge( $post_types, [ self::POST_TYPE_SLUG ] );
		} else {
			$post_types = array_diff( $post_types, [ self::POST_TYPE_SLUG ] );
		}

		return array_values( $post_types );
	}

	/**
	 * Add story_author as allowed orderby value for REST API.
	 *
	 * @param array        $query_params Array of allowed query params.
	 * @param WP_Post_Type $post_type Post type.
	 * @return array Array of query params.
	 */
	public function filter_rest_collection_params( $query_params, $post_type ) {
		if ( self::POST_TYPE_SLUG !== $post_type->name ) {
			return $query_params;
		}

		if ( empty( $query_params['orderby'] ) ) {
			return $query_params;
		}
		$query_params['orderby']['enum'][] = 'story_author';
		return $query_params;
	}

	/**
	 * Filters the revision fields to ensure that JSON representation gets saved to Story revisions.
	 *
	 * @param array $fields Array of allowed revision fields.
	 * @param array $story Story post array.
	 * @return array Array of allowed fields.
	 */
	public function filter_revision_fields( $fields, $story ) {
		if ( self::POST_TYPE_SLUG === $story['post_type'] ) {
			$fields['post_content_filtered'] = __( 'Story data', 'web-stories' );
		}
		return $fields;
	}

	/**
	 * Filter if show admin bar on single post type.
	 *
	 * @param boolean $show Current value of filter.
	 *
	 * @return bool
	 */
	public function show_admin_bar( $show ) {
		if ( is_singular( self::POST_TYPE_SLUG ) ) {
			$show = false;
		}

		return $show;
	}

	/**
	 * Replace default post editor with our own implementation.
	 *
	 * @codeCoverageIgnore
	 *
	 * @param bool    $replace Bool if to replace editor or not.
	 * @param WP_Post $post    Current post object.
	 *
	 * @return bool Whether the editor has been replaced.
	 */
	public function replace_editor( $replace, $post ) {
		if ( self::POST_TYPE_SLUG === get_post_type( $post ) ) {

			// Since the 'replace_editor' filter can be run multiple times, only load the
			// custom editor after the 'current_screen' action when we can be certain the
			// $post_type, $post_type_object, $post globals are all set by WordPress.
			if ( did_action( 'current_screen' ) ) {
				require_once WEBSTORIES_PLUGIN_DIR_PATH . 'includes/templates/admin/edit-story.php';
			}

			return true;
		}

		return $replace;
	}

	/**
	 * Filters whether post type supports the block editor.
	 *
	 * Disables the block editor and associated logic (like enqueueing assets)
	 * for the story post type.
	 *
	 * @param bool   $use_block_editor  Whether the post type can be edited or not. Default true.
	 * @param string $post_type         The post type being checked.
	 * @return bool Whether to use the block editor.
	 */
	public function filter_use_block_editor_for_post_type( $use_block_editor, $post_type ) {
		if ( self::POST_TYPE_SLUG === $post_type ) {
			return false;
		}

		return $use_block_editor;
	}

	/**
	 *
	 * Enqueue scripts for the element editor.
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		if ( self::POST_TYPE_SLUG !== $screen->post_type ) {
			return;
		}

		// Only output scripts and styles where in edit screens.
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		// Force media model to load.
		wp_enqueue_media();

		wp_register_style(
			'roboto',
			'https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&display=swap',
			[],
			WEBSTORIES_VERSION
		);

		$this->enqueue_script( self::WEB_STORIES_SCRIPT_HANDLE, [ Tracking::SCRIPT_HANDLE ] );
		$this->enqueue_style( self::WEB_STORIES_SCRIPT_HANDLE, [ 'roboto' ] );

		wp_localize_script(
			self::WEB_STORIES_SCRIPT_HANDLE,
			'webStoriesEditorSettings',
			$this->get_editor_settings()
		);

		// Dequeue forms.css, see https://github.com/google/web-stories-wp/issues/349 .
		$this->remove_admin_style( [ 'forms' ] );
	}

	/**
	 * Get editor settings as an array.
	 *
	 * @return array
	 */
	public function get_editor_settings() {
		$post                     = get_post();
		$story_id                 = ( $post ) ? $post->ID : null;
		$rest_base                = self::POST_TYPE_SLUG;
		$has_publish_action       = false;
		$has_assign_author_action = false;
		$has_upload_media_action  = current_user_can( 'upload_files' );
		$post_type_object         = get_post_type_object( self::POST_TYPE_SLUG );

		if ( $post_type_object instanceof WP_Post_Type ) {
			$rest_base = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;
			if ( property_exists( $post_type_object->cap, 'publish_posts' ) ) {
				$has_publish_action = current_user_can( $post_type_object->cap->publish_posts );
			}
			if ( property_exists( $post_type_object->cap, 'edit_others_posts' ) ) {
				$has_assign_author_action = current_user_can( $post_type_object->cap->edit_others_posts );
			}
		}

		// Media settings.
		$max_upload_size = wp_max_upload_size();
		if ( ! $max_upload_size ) {
			$max_upload_size = 0;
		}

		$preview_query_args = [
			'preview_id'    => $story_id,
			// Leveraging the default WP post preview logic.
			'preview_nonce' => wp_create_nonce( 'post_preview_' . $story_id ),
		];


		$settings = [
			'id'     => 'edit-story',
			'config' => [
				'autoSaveInterval' => defined( 'AUTOSAVE_INTERVAL' ) ? AUTOSAVE_INTERVAL : null,
				'isRTL'            => is_rtl(),
				'dateFormat'       => get_option( 'date_format' ),
				'timeFormat'       => get_option( 'time_format' ),
				'allowedMimeTypes' => $this->get_allowed_mime_types(),
				'allowedFileTypes' => $this->get_allowed_file_types(),
				'postType'         => self::POST_TYPE_SLUG,
				'storyId'          => $story_id,
				'previewLink'      => get_preview_post_link( $story_id, $preview_query_args ),
				'maxUpload'        => $max_upload_size,
				'capabilities'     => [
					'hasPublishAction'      => $has_publish_action,
					'hasAssignAuthorAction' => $has_assign_author_action,
					'hasUploadMediaAction'  => $has_upload_media_action,
				],
				'api'              => [
					'users'   => '/wp/v2/users',
					'stories' => sprintf( '/web-stories/v1/%s', $rest_base ),
					'media'   => '/web-stories/v1/media',
					'fonts'   => '/web-stories/v1/fonts',
					'link'    => '/web-stories/v1/link',
				],
				'metadata'         => [
					'publisher'       => $this->get_publisher_data(),
					'logoPlaceholder' => $this->get_publisher_logo_placeholder(),
					'fallbackPoster'  => plugins_url( 'assets/images/fallback-poster.jpg', WEBSTORIES_PLUGIN_FILE ),
				],
			],
			'flags'  => array_merge(
				$this->experiments->get_experiment_statuses( 'general' ),
				$this->experiments->get_experiment_statuses( 'editor' )
			),
		];

		/**
		 * Filters settings passed to the web stories editor.
		 *
		 * @param array $settings Array of settings passed to web stories editor.
		 */
		return apply_filters( 'web_stories_editor_settings', $settings );
	}

	/**
	 * Set template for web-story post type.
	 *
	 * @param string $template Template.
	 *
	 * @return string Template.
	 */
	public function filter_template_include( $template ) {
		if ( is_singular( self::POST_TYPE_SLUG ) && ! is_embed() ) {
			$template = WEBSTORIES_PLUGIN_DIR_PATH . 'includes/templates/frontend/single-web-story.php';
		}

		return $template;
	}

	/**
	 * Adds the web story post type to Jetpack / WordPress.com sitemaps.
	 *
	 * @see https://github.com/Automattic/jetpack/blob/4b85be883b3c584c64eeb2fb0f3fcc15dabe2d30/modules/custom-post-types/portfolios.php#L80
	 *
	 * @param array $post_types Array of post types.
	 *
	 * @return array Modified list of post types.
	 */
	public function add_to_jetpack_sitemap( $post_types ) {
		$post_types[] = self::POST_TYPE_SLUG;

		return $post_types;
	}

	/**
	 * Change the content to an embedded player
	 *
	 * @param string $content Current content of filter.
	 *
	 * @return string
	 */
	public function embed_player( $content ) {
		$post = get_post();

		if ( is_feed() ) {
			return $content;
		}

		if ( ! is_search() && ! is_post_type_archive() ) {
			return $content;
		}

		if ( $post instanceof WP_Post && self::POST_TYPE_SLUG === $post->post_type ) {
			$story = new Story();
			$story->load_from_post( $post );

			$embed   = new Embed( $story );
			$content = $embed->render();
		}

		return $content;
	}
}
