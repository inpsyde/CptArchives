<?php declare( strict_types=1 ); # -*- coding: utf-8 -*-
/*
 * This file is part of the inpsyde-teaser package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CptArchives;

/**
 * @package CptArchivesUI
 * @license http://opensource.org/licenses/MIT MIT
 */
class AdminUi {

	const FILTER_ALLOWED_CPTS = 'cpt-archives.allowed-cpts';
	const FILTER_CAPABILITY = 'cpt-archives.can-edit-archive';
	const MENU_SUFFIX = '-archive-post';

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
	 * @var \WP_Post_Type|null
	 */
	private static $target_post_type;

	/**
	 * Check if current admin screen is for the CPT archive post.
	 *
	 * We can't just look at ID, because it changes based on post type, and we can't look at screen post type
	 * because that's not set, not being a post edit page.
	 *
	 * @return bool
	 */
	public static function is_archive_ui_screen(): bool {

		if ( self::$target_post_type ) {
			return true;
		}

		$screen  = is_admin() && did_action( 'current_screen' ) ? get_current_screen() : null;
		$id_part = explode( '_', $screen->id, 2 );
		$suffix  = preg_quote( self::MENU_SUFFIX, '~' );

		if ( ! preg_match( '~^page_(.+)?' . $suffix . '$~', end( $id_part ), $matches ) ) {
			return false;
		}

		$type = $matches[ 1 ];
		if ( ! post_type_exists( $type ) ) {
			return false;
		}

		$type_obj = get_post_type_object( $type );
		if ( ! $type_obj || ! $type_obj->has_archive ) {
			return false;
		}

		$page = filter_input( INPUT_GET, 'page' );
		if ( ! preg_match( '~^(.+)?' . $suffix . '$~', $page, $matches ) ) {
			return false;
		}

		if ( $matches[ 1 ] === $type_obj->name ) {
			self::$target_post_type = $type_obj;

			return true;
		}

		return false;
	}

	/**
	 * Run all the setup tasks on admin screen.
	 *
	 * @return bool
	 */
	public function setup(): bool {

		if ( ! is_admin() || is_network_admin() || is_user_admin() ) {
			return false;
		}

		return
			$this->setup_menu_pages()
			&& $this->remove_slug_metabox()
			&& $this->fix_delete_text()
			&& $this->fix_side_metaboxes()
			&& $this->fix_post_messages();
	}

	/**
	 * Adds entry to admin menu.
	 *
	 * There's a parent entry, plus a submenu entry for each post type.
	 * Parent entry will be linked to first submenu entry, like usual in wordPress.
	 * However, when there's just one post type, the menu will show just the parent item. This is due to a limitation of
	 * WordPress that otherwise would add a submenu entry to match parent entry, which would be broken.
	 *
	 * @return bool
	 */
	private function setup_menu_pages(): bool {

		$target_post_types = $this->target_post_types();

		if ( ! $target_post_types ) {
			return false;
		}

		$done_count  = 0;
		$types_count = count( $target_post_types );

		/* Translators: 1 is the CPT label, e.g. "Products" */
		$type_label = esc_html__( '%1$s Archive', 'cpt-archives' );

		$f_type       = array_shift( $target_post_types );
		$f_slug       = $f_type->name . self::MENU_SUFFIX;
		$f_title      = sprintf( $type_label, $f_type->labels->name );
		$f_capability = apply_filters( self::FILTER_CAPABILITY, $f_type->cap->edit_others_posts, $f_type );
		$parent_title = $types_count > 1 ? esc_html__( 'CPT Archives', 'cpt-archives' ) : $f_title;

		add_menu_page( $parent_title, $parent_title, 'edit_posts', $f_slug, '', 'dashicons-archive' );

		$success = $this->setup_menu_page( $f_slug, $f_slug, $f_title, (string) $f_capability, $f_type );
		if ( ! $success ) {

			remove_menu_page( $f_slug );

			return false;
		}

		$done_count ++;

		/** @var \WP_Post_Type $post_type */
		foreach ( $target_post_types as $post_type ) {
			$type_title = sprintf( $type_label, $post_type->labels->name );
			$capability = apply_filters( self::FILTER_CAPABILITY, $post_type->cap->edit_others_posts, $post_type );
			$slug       = $post_type->name . self::MENU_SUFFIX;
			$success    = $this->setup_menu_page( $f_slug, $slug, $type_title, (string) $capability, $post_type );
			$success and $done_count ++;
		}

		return $done_count === $types_count;

	}

	/**
	 * Return the list of post types we should add an entry for.
	 *
	 * That is, those that have and archive and are public.
	 * Return value is filterable.
	 *
	 * @return \WP_Post_Type[]
	 */
	private function target_post_types() {

		$types = get_post_types( [ 'has_archive' => true, 'public' => true ], 'objects' );

		$allowed_types = (array) apply_filters( self::FILTER_ALLOWED_CPTS, $types );
		$allowed_types and $allowed_types = array_filter(
			$allowed_types,
			function ( $type ) {

				return
					$type instanceof \WP_Post_Type
					&& $type->_builtin === false
					&& ! in_array( $type->name, self::CORE_TYPES, true );
			}
		);

		return $allowed_types;
	}

	/**
	 * Add submenu entry with given arguments.
	 *
	 * Use this `render_menu_page()` method as me page callback.
	 *
	 * @param string        $parent
	 * @param string        $slug
	 * @param string        $title
	 * @param string        $capability
	 * @param \WP_Post_Type $cpt
	 *
	 * @return bool
	 *
	 * @see AdminUI::render_menu_page()
	 */
	private function setup_menu_page(
		string $parent,
		string $slug,
		string $title,
		string $capability,
		\WP_Post_Type $cpt
	): bool {

		return (bool) add_submenu_page(
			$parent,
			$title,
			$title,
			$capability,
			$slug,
			function () use ( $cpt, $capability ) {

				$this->render_menu_page( [ $cpt->name . self::MENU_SUFFIX => [ $capability, $cpt ] ] );
			}
		);
	}

	/**
	 * Render callback for menu pages.
	 *
	 * After having sanity checks, setup global variables and require core `edit-form-advanced.php`.
	 *
	 * @param array $type_data
	 */
	private function render_menu_page( array $type_data ) {

		if ( ! self::is_archive_ui_screen() ) {
			return;
		}

		$key = self::$target_post_type->name . self::MENU_SUFFIX;

		if ( empty( $type_data[ $key ] ) ) {
			return;
		}

		/**
		 * @var string        $capability
		 * @var \WP_Post_Type $target_type_object
		 */
		list( $capability, $target_type_object ) = $type_data[ $key ];

		if ( ! current_user_can( $capability ) || $target_type_object->name !== self::$target_post_type->name ) {
			$this->bail_on_render();

			return;
		}

		self::$target_post_type = $target_type_object;

		global $post_type, $post_type_object, $post, $post_ID, $action, $typenow, $is_IE, $title;

		$post_id = Archive::for_type( $target_type_object )
		                  ->archive_post_id();

		if ( ! $post_id || ! ( $post = get_post( $post_id ) ) ) {
			$this->bail_on_render();

			return;
		}

		$post_type_object = get_post_type_object( ArchiveType::SLUG );
		$post_type        = $typenow = $post_type_object->name;
		$post_ID          = $post_id;
		$action           = 'edit';

		global $wp_post_types;
		if ( ! empty( $wp_post_types[ $typenow ] ) ) {
			$wp_post_types[ $typenow ]->show_ui            = true;
			$wp_post_types[ $typenow ]->public             = true;
			$wp_post_types[ $typenow ]->publicly_queryable = true;
		}

		require_once ABSPATH . 'wp-admin/edit-form-advanced.php';
	}

	/**
	 * Remove the metabox for post slug, because unnecessary.
	 *
	 * @return bool
	 */
	private function remove_slug_metabox(): bool {

		return (bool) add_action(
			'add_meta_boxes',
			function ( $post_type ) {

				if ( $post_type === ArchiveType::SLUG ) {
					remove_meta_box( 'slugdiv', get_current_screen(), 'normal' );
				}
			}
		);
	}

	/**
	 * WordPress triggers metaboxes via `do_meta_boxes()` but for "side" metaboxes it used the post type name
	 * as first argument (used for key of `$wp_meta_boxes`). for other contexts it uses `null` that is then replaced
	 * with current screen id.
	 * For core this is not an issue, because screen id and post type matches, but for us screen id is very different
	 * than post type name, so we need to normalize "side" metaboxes to use post type name and all the others to use
	 * screen id.
	 *
	 * @return bool
	 */
	private function fix_side_metaboxes(): bool {

		return (bool) add_action(
			'edit_form_after_editor',
			function () {

				if ( ! self::is_archive_ui_screen() ) {
					return;
				}

				$screen = get_current_screen();
				global $wp_meta_boxes;

				$cpt_boxes      = $wp_meta_boxes[ ArchiveType::SLUG ] ?? [];
				$cpt_boxes_side = $cpt_boxes[ 'side' ] ?? [];
				unset( $cpt_boxes[ 'side' ], $wp_meta_boxes[ ArchiveType::SLUG ][ 'side' ] );

				$screen_boxes      = $wp_meta_boxes[ $screen->id ];
				$screen_boxes_side = $screen_boxes[ 'side' ] ?? [];
				unset( $screen_boxes[ 'side' ], $wp_meta_boxes[ $screen->id ][ 'side' ] );

				$wp_meta_boxes[ ArchiveType::SLUG ][ 'side' ] = array_merge_recursive(
					$cpt_boxes_side,
					$screen_boxes_side
				);

				foreach ( $cpt_boxes as $context => $context_cpt_boxes ) {

					$screen_boxes[ $context ] = array_key_exists( $context, $screen_boxes )
						? array_merge_recursive( $context_cpt_boxes, $screen_boxes[ $context ] )
						: $context_cpt_boxes;

					unset( $wp_meta_boxes[ ArchiveType::SLUG ][ $context ] );
				}

				$wp_meta_boxes[ $screen->id ] = $screen_boxes;
			}
		);
	}

	/**
	 * We don't support trash, and there's no way to change the wording in submit box other than acting on gettext.
	 */
	private function fix_delete_text(): bool {

		$fix = function ( $translation, $text, $domain ) {

			static $doing;
			if (
				! $doing
				&& $domain === 'default'
				&& ( $text === 'Move to Trash' || $text === 'Delete Permanently' )
			) {
				$doing       = true;
				$translation = esc_html__( 'Reset all the data.', 'cpt-archives' );
			}

			return $translation;
		};

		add_action(
			'post_submitbox_start',
			function () use ( $fix ) {

				if ( self::is_archive_ui_screen() ) {
					add_filter( 'gettext', $fix, 10, 3 );
				}
			}
		);

		add_action(
			'add_meta_boxes',
			function () use ( $fix ) {

				if ( self::is_archive_ui_screen() ) {
					remove_filter( 'gettext', $fix, 10 );
				}
			},
			0
		);

		return true;
	}

	/**
	 * By default, WordPress prints in `edit-form-advanced.php` messages like "Post updated" or "View post" that can
	 * be misleading, let's replace them using "Archive", e.g. "Archive updated" or "Archive post".
	 *
	 * @return bool
	 */
	private function fix_post_messages(): bool {

		return add_filter(
			'post_updated_messages',
			function ( array $messages ) {

				if ( ! self::is_archive_ui_screen() ) {
					return $messages;
				}

				global $post_ID, $post;
				$permalink      = esc_url( get_permalink( $post_ID ) ? : '' );
				$preview_url    = esc_url( get_preview_post_link( $post ) );
				$scheduled_date = date_i18n( __( 'M j, Y @ H:i' ), strtotime( $post->post_date ) );

				$link_format_blank = ' <a target="_blank" href="%1$s">%2$s</a>';
				$link_format       = ' <a href="%1$s">%2$s</a>';

				$preview           = esc_html__( 'Preview archive', 'cpt-archives' );
				$view              = esc_html__( 'View archive', 'cpt-archives' );
				$archive_updated   = esc_html__( 'Archive updated.', 'cpt-archives' );
				$archive_restored  = esc_html__( 'Archive restored to revision from %s.', 'cpt-archives' );
				$archive_published = esc_html__( 'Archive published.', 'cpt-archives' );
				$archive_saved     = esc_html__( 'Archive saved.', 'cpt-archives' );
				$archive_scheduled = esc_html__( 'Archive scheduled for: %s.', 'cpt-archives' );
				$archive_submitted = esc_html__( 'Archive submitted.', 'cpt-archives' );
				$cf_updated        = __( 'Custom field updated.' );

				$preview_link_html   = sprintf( $link_format_blank, esc_url( $preview_url ), $preview );
				$scheduled_link_html = sprintf( $link_format_blank, $permalink, $preview );
				$view_link_html      = sprintf( $link_format, $permalink, $view );

				$messages[ 'post' ] = [
					'',
					$archive_updated . $view_link_html,
					$cf_updated,
					$cf_updated,
					$archive_updated,
					isset( $_GET[ 'revision' ] )
						? sprintf( $archive_restored, wp_post_revision_title( (int) $_GET[ 'revision' ], false ) )
						: false,
					$archive_published . $view_link_html,
					$archive_saved,
					$archive_submitted . $preview_link_html,
					sprintf( $archive_scheduled, "<strong>{$scheduled_date}</strong>" ) . $scheduled_link_html,
					esc_html__( 'Archive draft updated.', 'cpt-archives' ) . $preview_link_html,
				];

				return $messages;
			}
		);
	}

	/**
	 * Prints an error message if it is not possible to properly render the edit screen.
	 *
	 * @return bool
	 */
	private function bail_on_render(): bool {

		echo '<h1>' . esc_html__( 'Something went wrong...', 'cpt-archives' ) . '</h1>';
		echo '<p>' . esc_html__( 'Sorry, it was not possible to find an archive to edit.', 'cpt-archives' ) . '</p>';

		return false;
	}
}