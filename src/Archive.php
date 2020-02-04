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
 * Api object to get archive information.
 *
 * @package CptArchives
 */
class Archive {

	const TARGET_TYPE_KEY = '_target_post_type';

	/**
	 * @var Archive[]
	 */
	private static $instances = [];

	/**
	 * @var \WP_Post
	 */
	private $post;

	/**
	 * Named constructor that build an instance of archive API object for given post type.
	 *
	 * @param \WP_Post_Type|string $type
	 *
	 * @return Archive
	 */
	public static function for_type( $type = null ): Archive {

		if ( is_null( $type ) ) {
			return self::for_current_type();
		}

		$type_name = $type instanceof \WP_Post_Type ? $type->name : $type;
		is_string( $type_name ) or $type_name = '';

		if ( ! $type_name ) {
			return new static();
		}

		if ( array_key_exists( $type_name, self::$instances ) ) {
			return self::$instances[ $type_name ];
		}

		$post_type = get_post_type_object( $type_name );

		if ( ! $post_type || ! $post_type->has_archive ) {
			return new static();
		}

		$valid_types  = ArchiveType::target_post_types();

		if ( ! array_key_exists( $type_name, $valid_types ) ) {
			return new static();
		}

		$posts = get_posts(
			[
				'post_type'      => ArchiveType::SLUG,
				'post_status'    => [ 'draft', 'publish', 'future', 'pending', 'private' ],
				'posts_per_page' => 1,
				'meta_key'       => self::TARGET_TYPE_KEY,
				'meta_value'     => $type_name,
			]
		);

		if ( $posts ) {
			self::$instances[ $type_name ] = new static( reset( $posts ) );

			return self::$instances[ $type_name ];
		}

		$insert = wp_insert_post(
			[
				'post_type'   => ArchiveType::SLUG,
				'post_name'   => $type_name,
				'post_status' => 'draft',
				'post_title'  => apply_filters( 'post_type_archive_title', $post_type->labels->name, $post_type ),
				'meta_input'  => [
					self::TARGET_TYPE_KEY => $type_name,
				],
			]
		);

		if ( $insert && ! is_wp_error( $insert ) && ( $post = get_post( $insert ) ) ) {
			self::$instances[ $type_name ] = new static( $post );

			return self::$instances[ $type_name ];
		}

		return new static();
	}

	/**
	 * Named constructor that build an instance of archive API object for current post type when in post type archive
	 * pages.
	 *
	 * When no internal post exists yet for given type, it is created.
	 *
	 * @return Archive
	 */
	public static function for_current_type(): Archive {

		if ( ! is_post_type_archive() ) {
			return new static();
		}

		global $wp_query;
		$post_type = $wp_query->get( 'post_type' );
		is_array( $post_type ) and $post_type = reset( $post_type );

		return self::for_type( (string) $post_type );
	}

	/**
	 * Disabled on purpose, use named constructors.
	 *
	 * @param \WP_Post $post
	 */
	public function __construct( \WP_Post $post = null ) {

		$this->post = $post;
	}

	/**
	 * Return the internal WordPress post object.
	 *
	 * @return int
	 */
	public function archive_post_id(): int {

		return $this->post ? (int) $this->post->ID : 0;
	}

	/**
	 * @return string
	 */
	public function target_type(): string {

		$type = get_post_meta( $this->post->ID, self::TARGET_TYPE_KEY, TRUE );
		if ( ! $type || ! post_type_exists( $type ) ) {
			return '';
		}

		return $type;
	}

	/**
	 * Returns the title for the archive.
	 *
	 * @param string $before
	 * @param string $after
	 *
	 * @return string
	 */
	public function archive_title( string $before = '', string $after = '' ): string {

		if ( ! $this->is_valid() ) {
			return '';
		}

		$title = get_the_title( $this->post );

		if ( strlen( $title ) == 0 ) {
			return '';
		}

		return $before . $title . $after;
	}

	/**
	 * Returns the content for the archive.
	 *
	 * @param string|null $more_link_text
	 * @param bool        $strip_teaser
	 *
	 * @return string
	 */
	public function archive_content( string $more_link_text = null, bool $strip_teaser = FALSE ): string {

		if ( ! $this->is_valid() ) {
			return '';
		}

		setup_postdata( $this->post );
		ob_start();
		the_content( $more_link_text, $strip_teaser );
		$content = ob_get_clean();
		wp_reset_postdata();

		return $content;
	}

	/**
	 * Returns the excerpt for the archive.
	 *
	 * @return string
	 */
	public function archive_excerpt(): string {

		if ( ! $this->is_valid() ) {
			return '';
		}

		setup_postdata( $this->post );
		ob_start();
		the_excerpt();
		$excerpt = ob_get_clean();
		wp_reset_postdata();

		return $excerpt;
	}

	/**
	 * Returns true when a thumbnail is available for the archive.
	 *
	 * @return bool
	 */
	public function archive_has_thumbnail(): bool {

		return $this->is_valid()
			? has_post_thumbnail( $this->post )
			: FALSE;
	}

	/**
	 * Returns the archive thumbnail id or `0` when no thumbnail.
	 *
	 * @return int
	 */
	public function archive_thumbnail_id(): int {

		return $this->archive_has_thumbnail() ? (int) get_post_thumbnail_id( $this->post ) : 0;
	}

	/**
	 * Returns the archive thumbnail `img` tag or empty string when no thumbnail.
	 *
	 * @param string $size
	 * @param array  $attr
	 *
	 * @return string
	 */
	public function archive_thumbnail( string $size = 'post-thumbnail', array $attr = [] ): string {

		$thumb = $this->archive_thumbnail_id();

		if ( ! $thumb ) {
			return '';
		}

		return get_the_post_thumbnail( $this->post, $size, $attr );
	}

	/**
	 * Returns a custom field value for the archive.
	 *
	 * @param string $key
	 * @param bool   $single
	 *
	 * @return mixed
	 */
	public function archive_meta( string $key = '', bool $single = FALSE ) {

		if ( ! $this->is_valid( [ 'draft', 'publish', 'future', 'pending', 'private' ] ) ) {
			return $single && $key ? FALSE : [];
		}

		if ( ! $key ) {
			return get_post_custom( $this->post->ID );
		}

		return get_post_meta( $this->post->ID, $key, $single );
	}

	/**
	 * @param array $allowed_statuses
	 *
	 * @return bool
	 */
	private function is_valid( array $allowed_statuses = [ 'publish' ] ): bool {

		return $this->post && $this->post->ID && in_array( $this->post->post_status, $allowed_statuses );
	}

}