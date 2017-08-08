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
class Bootstrap {

	/**
	 * @var bool
	 */
	private static $done = FALSE;

	/**
	 * Launch all the  bootstrap tasks, according to current application "side".
	 *
	 * Ensure to run once per request.
	 *
	 * @return bool
	 */
	public static function bootstrap() {

		if ( self::$done ) {
			return FALSE;
		}

		self::$done = TRUE;
		$instance   = new static();

		$instance->core();
		is_admin() ? $instance->backend() : $instance->frontend();

		return TRUE;
	}

	/**
	 * Do core bootstrap tasks.
	 */
	private function core() {

		require_once dirname( __DIR__ ) . '/inc/functions.php';

		$locale = apply_filters( 'plugin_locale', get_user_locale(), 'cpt-archives' );
		$path   = dirname( __DIR__ ) . '/languages';
		if ( is_readable( "{$path}/cpt-archives-{$locale}.mo" ) ) {
			load_textdomain( 'cpt-archives', "{$path}/cpt-archives-{$locale}.mo" );
		}

		add_action( 'init', [ new ArchiveType(), 'setup' ] );
	}

	/**
	 * Do bootstrap tasks for backend.
	 */
	private function backend() {

		add_action( 'admin_menu', [ new AdminUi(), 'setup' ] );

		add_action( 'current_screen', function ( \WP_Screen $screen ) {
			if ( AdminUi::is_archive_ui_screen() ) {
				$screen->post_type = ArchiveType::SLUG;
			}
		} );

		return $this;
	}

	/**
	 * Do bootstrap tasks for frontend.
	 */
	private function frontend() {

		add_filter(
			'post_type_archive_title',
			function ( $value ) {

				$title = archive_title();
				if ( $title ) {
					add_filter( 'get_the_archive_title', function ( $archive_title ) use ( $title ) {
						if ( $archive_title === sprintf( __( 'Archives: %s' ), $title ) ) {
							$archive_title = $title;
						}

						return $archive_title;
					} );
				}

				return $title ? : $value;
			}
		);

		add_filter(
			'get_the_archive_description',
			function ( $value ) {

				return archive_excerpt() ? : $value;
			}
		);

		add_action( 'admin_bar_menu', function ( \WP_Admin_Bar $wp_admin_bar ) {
			if ( ! is_post_type_archive() ) {
				return;
			}

			$archive = Archive::for_current_type();
			$post_id = $archive->archive_post_id();
			if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
				$wp_admin_bar->add_menu(
					[
						'id'    => 'edit',
						'title' => sprintf( __( 'Edit Archive', 'cpt-archives' ) ),
						'href'  => add_query_arg(
							[ 'page' => $archive->target_type() . AdminUi::MENU_SUFFIX, ],
							admin_url( 'admin.php' )
						)
					]
				);
			}
		}, 80 );
	}

}