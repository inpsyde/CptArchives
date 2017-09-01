<?php declare( strict_types=1 ); # -*- coding: utf-8 -*-
/*
 * This file is part of the CptArchives package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CptArchives;

/**
 * @package CptArchives
 * @license http://opensource.org/licenses/MIT MIT
 */
class ArchiveType {

	const SLUG = 'cpt_archive';
	const FILTER_ARGS = 'cpt-archives.cpt-archive-args';
	const FILTER_ALLOWED_CPTS = 'cpt-archives.allowed-cpts';

	const CORE_TYPES = [
		'post',
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
	];

	/**
	 * Return the list of post types we should add an entry for.
	 *
	 * That is, those that have and archive and are public.
	 * Return value is filterable.
	 *
	 * @return \WP_Post_Type[]
	 */
	public static function target_post_types(): array {

		$types = get_post_types(
			[
				'has_archive'        => TRUE,
				'publicly_queryable' => TRUE,
				'_builtin'           => FALSE
			],
			'objects'
		);

		$allowed_types = (array) apply_filters( self::FILTER_ALLOWED_CPTS, $types );
		$allowed_types and $allowed_types = array_filter(
			$allowed_types,
			function ( $type ) {

				return
					$type instanceof \WP_Post_Type
					&& $type->has_archive
					&& $type->publicly_queryable
					&& $type->_builtin === FALSE
					&& ! in_array( $type->name, self::CORE_TYPES, TRUE );
			}
		);

		return $allowed_types;
	}

	/**
	 * Setup the post type and other necessary task to make it work.
	 *
	 * @return bool
	 */
	public function setup(): bool {

		if ( $this->register_post_type() ) {
			$this->filter_urls();

			return TRUE;
		}

		return FALSE;
	}

	/**
	 * Register archive post type.
	 *
	 * Many post type arguments are filterable, some other are enforced.
	 *
	 * @return bool
	 */
	private function register_post_type(): bool {
		
		$args = [
			'label'  => __( 'CPT Archives', 'cpt-archives' ),
			'labels' => [
				'name'                  => __( 'CPT Archives', 'cpt-archives' ),
				'singular_name'         => __( 'CPT Archive', 'cpt-archives' ),
				'add_new_item'          => __( 'Add new CPT Archive', 'cpt-archives' ),
				'edit_item'             => __( 'EditCPT Archive', 'cpt-archives' ),
				'new_item'              => __( 'New CPT Archive', 'cpt-archives' ),
				'view_item'             => __( 'View CPT Archive', 'cpt-archives' ),
				'view_items'            => __( 'View CPT Archives', 'cpt-archives' ),
				'search_items'          => __( 'Search CPT Archives', 'cpt-archives' ),
				'not_found'             => __( 'No CPT Archives found.', 'cpt-archives' ),
				'not_found_in_trash'    => __( 'No CPT Archives found in trash.', 'cpt-archives' ),
				'parent_item_colon'     => __( 'Parent CPT Archive:', 'cpt-archives' ),
				'all_items'             => __( 'All CPT Archives', 'cpt-archives' ),
				'archives'              => __( 'CPT Archive archives', 'cpt-archives' ),
				'attributes'            => __( 'CPT Archive attributes', 'cpt-archives' ),
				'insert_into_item'      => __( 'Insert into CPT Archive', 'cpt-archives' ),
				'uploaded_to_this_item' => __( 'Uploaded to this CPT Archive', 'cpt-archives' ),
			],

			'capability_type'   => 'post',
			'map_meta_cap'      => TRUE,
			'capabilities'      => [ 'create_posts' => 'do_not_allow' ],
			'supports'          => [
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'custom-fields',
				'revisions',
			],
			'show_in_nav_menus' => TRUE,
			'show_in_rest'      => FALSE,
		];

		$args = (array) apply_filters( self::FILTER_ARGS, $args );

		$forced = [
			'public'              => FALSE,
			'hierarchical'        => FALSE,
			'exclude_from_search' => TRUE,
			'publicly_queryable'  => FALSE,
			'show_ui'             => FALSE,
			'show_in_menu'        => FALSE,
			'show_in_admin_bar'   => FALSE,
			'has_archive'         => FALSE,
			'rewrite'             => FALSE,
			'query_var'           => FALSE,
			'permalink_epmask'    => EP_NONE,
			'delete_with_user'    => FALSE,
		];

		$registered = register_post_type( self::SLUG, array_merge( $args, $forced ) );

		return $registered && ! is_wp_error( $registered );
	}

	/**
	 * Archive post type is not a _real_ post type, and its URL must be replaced according to context.
	 */
	private function filter_urls() {

		add_action(
			'wp_insert_post',
			function ( $post_id, \WP_Post $post ) {

				if ( $post->post_type !== self::SLUG ) {
					return;
				}

				$target_type = get_post_meta( $post_id, Archive::TARGET_TYPE_KEY, TRUE );
				if ( ! $target_type || ! post_type_exists( $target_type ) ) {
					return;
				}

				$post_type             = get_post_type_object( $post->post_type );
				$post_type->_edit_link = add_query_arg(
					[ 'page' => $target_type . AdminUi::MENU_SUFFIX ],
					'admin.php'
				);
			},
			0,
			2
		);

		add_filter(
			'get_delete_post_link',
			function ( $link, $post_id ) {

				$post = get_post( $post_id );

				if ( ! $post || $post->post_type !== self::SLUG ) {
					return $link;
				}

				$target_type = get_post_meta( $post_id, Archive::TARGET_TYPE_KEY, TRUE );
				if ( ! $target_type || ! post_type_exists( $target_type ) ) {
					return $link;
				}

				$delete_link = add_query_arg(
					[ 'post' => $post_id, 'action' => 'delete', ],
					'post.php'
				);

				return wp_nonce_url( $delete_link, "delete-post_{$post_id}" );
			},
			99,
			2
		);

		add_filter(
			'post_type_link',
			function ( $post_link, \WP_Post $post ) {

				if ( $post->post_type !== ArchiveType::SLUG ) {
					return $post_link;
				}

				$target_type = get_post_meta( $post->ID, Archive::TARGET_TYPE_KEY, TRUE );

				if ( ! $target_type ) {
					return $post_link;
				}

				return get_post_type_archive_link( $target_type ) ? : home_url();
			},
			99,
			2
		);

		add_filter(
			'preview_post_link',
			function ( $preview_link, \WP_Post $post ) {

				if ( $post->post_type !== ArchiveType::SLUG ) {
					return $preview_link;
				}

				$target_type = get_post_meta( $post->ID, Archive::TARGET_TYPE_KEY, TRUE );

				if ( ! $target_type ) {
					return $preview_link;
				}

				return get_post_type_archive_link( $target_type ) ? : home_url();
			},
			99,
			2
		);

		add_filter(
			'get_sample_permalink',
			function ( $permalink, $post_id, $title, $name, \WP_Post $post ) {

				if ( $post->post_type !== ArchiveType::SLUG ) {
					return $permalink;
				}

				$target_type = get_post_meta( $post->ID, Archive::TARGET_TYPE_KEY, TRUE );

				if ( ! $target_type ) {
					return $permalink;
				}

				return [ get_post_type_archive_link( $target_type ) ? : home_url(), $target_type ];
			},
			99,
			5
		);

	}

}