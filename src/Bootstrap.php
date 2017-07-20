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
class Bootstrap {

	/**
	 * @var bool
	 */
	private static $done = false;

	/**
	 * Launch all the  bootstrap tasks, according to current application "side".
	 *
	 * Ensure to run once per request.
	 *
	 * @return bool
	 */
	public static function bootstrap() {

		if ( self::$done ) {
			return false;
		}

		self::$done = true;
		$instance   = new static();

		$instance->core();
		is_admin() ? $instance->backend() : $instance->frontend();

		return true;
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

				return archive_title() ? : $value;
			}
		);

		add_filter(
			'get_the_archive_description',
			function ( $value ) {

				return archive_excerpt() ? : $value;
			}
		);
	}

}