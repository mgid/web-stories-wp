<?php
/**
 * The story editor.
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

// don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

global $post_type, $post_type_object, $post;

/**
 * Filters the default content when creating a new story.
 *
 * @param string  $post_content Default post content.
 * @param WP_Post $post         Post object.
 */
$post->post_content_filtered = (string) apply_filters( 'web_stories_default_content', $post->post_content_filtered, $post );

add_filter(
	'rest_prepare_' . \Google\Web_Stories\Story_Post_Type::POST_TYPE_SLUG,
	static function( WP_REST_Response $response ) use ( $post ) {
		$data = $response->get_data();
		if ( array_key_exists( 'story_data', $data ) ) {
			$data['story_data'] = json_decode( $post->post_content_filtered, true );
			$response->set_data( $data );
		}

		return $response;
	},
	10
);

$rest_base = ! empty( $post_type_object->rest_base ) ? $post_type_object->rest_base : $post_type_object->name;

// Preload common data.
$preload_paths = [
	sprintf( '/web-stories/v1/%s/%s?context=edit', $rest_base, $post->ID ),
	'/web-stories/v1/media?context=edit&per_page=100&page=1&_web_stories_envelope=true',
	'/web-stories/v1/fonts',
];

/**
 * Preload common data by specifying an array of REST API paths that will be preloaded.
 *
 * Filters the array of paths that will be preloaded.
 *
 * @param string[] $preload_paths Array of paths to preload.
 * @param WP_Post  $post          Post being edited.
 */
$preload_paths = apply_filters( 'web_stories_editor_preload_paths', $preload_paths, $post );

/*
 * Ensure the global $post remains the same after API data is preloaded.
 * Because API preloading can call the_content and other filters, plugins
 * can unexpectedly modify $post.
 */
$backup_global_post = $post;

$preload_data = array_reduce(
	$preload_paths,
	'rest_preload_api_request',
	[]
);

// Restore the global $post as it was before API preloading.
$post = $backup_global_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

wp_add_inline_script(
	'wp-api-fetch',
	sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', wp_json_encode( $preload_data ) ),
	'after'
);

require_once ABSPATH . 'wp-admin/admin-header.php';
require_once __DIR__ . '/error-no-js.php';
?>

<div id="edit-story" class="hide-if-no-js">
	<h1 class="loading-message"><?php esc_html_e( 'Please wait...', 'web-stories' ); ?></h1>
</div>
