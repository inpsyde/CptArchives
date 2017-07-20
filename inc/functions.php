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

if ( defined( __NAMESPACE__ . '\\LOADED' ) ) {
	return;
}

const LOADED = 1;

/**
 * @param string|\WP_Post_Type|NULL $post_type
 * @param string                    $before
 * @param string                    $after
 *
 * @return string
 */
function archive_title( $post_type = null, string $before = '', string $after = '' ): string {

	return Archive::for_type( $post_type )
	              ->archive_title( $before, $after );
}

/**
 * @param string|\WP_Post_Type|NULL $post_type
 * @param string|null $more_link_text
 * @param bool        $strip_teaser
 *
 * @return string
 */
function archive_content(
	$post_type = null,
	string $more_link_text = null,
	bool $strip_teaser = false
): string {

	return Archive::for_type( $post_type )
	              ->archive_content( $more_link_text, $strip_teaser );
}

/**
 * @param string|\WP_Post_Type|NULL $post_type
 *
 * @return string
 */
function archive_excerpt( $post_type = null ): string {

	return Archive::for_type( $post_type )
	              ->archive_excerpt();
}

/**
 * @param string|\WP_Post_Type|NULL $post_type
 *
 * @return bool
 */
function archive_has_thumbnail( $post_type = null ): bool {

	return Archive::for_type( $post_type )
	              ->archive_has_thumbnail();
}

/**
 * @param string|\WP_Post_Type|NULL $post_type
 *
 * @return int
 */
function archive_thumbnail_id( $post_type = null ): int {

	return Archive::for_type( $post_type )
	              ->archive_thumbnail_id();
}

/**
 * @param string|\WP_Post_Type|NULL $post_type
 * @param string      $size
 * @param array       $attr
 *
 * @return string
 */
function archive_thumbnail( $post_type = null, string $size = 'post-thumbnail', array $attr = [] ): string {

	return Archive::for_type( $post_type )
	              ->archive_thumbnail( $size, $attr );

}

/**
 * @param string|\WP_Post_Type|NULL $post_type
 * @param string      $key
 * @param bool        $single
 *
 * @return mixed
 */
function archive_meta( $post_type = null, string $key = '', bool $single = false ) {

	return Archive::for_type( $post_type )
	              ->archive_meta( $key, $single );
}