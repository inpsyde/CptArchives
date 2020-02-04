# CPT Archives

> A Composer package that provides a post-like editing experience for post type archives.

---

## Features

- Post-like editing experience for post type archives with support for title, content, excerpt, custom fields, 
  post thumbnail, revisions.
- Extensible: it is possible to add metaboxes to edit screen like for any other post type
- Automatically filters `post_type_archive_title` and `get_the_archive_description` to return, 
  respectively, the _title_ and the _excerpt_ of assigned in backend (if any).
- Allows to add CPT archives link to WordPress nav menus, without using custom links.

---

## Usage

"CPT Archives" is **not** a plugin, but a Composer package. It can be required by themes, plugins or at website level
for sites entirely managed by Composer.

After it is installed via Composer, and composer autoload is required, CPT Archives needs to be bootstrapped, like this:

```php
CptArchives\Bootstrap\bootstrap();
```

- This can be done in any plugin, MU plugin or theme `functions.php` with no need to wrap the call in any hook.
- There's no need to check if the library is _already_ bootstrapped, the snippet above can be called multiple times
  without any negative effect.
  
After this single line of code is in place, all "CPT Archives" are fully working and its API is available for use.

---


## API

There are 2 API, one OOP and one procedural that wraps it.

OOP API is provided by method of the object **`CptArchives\Archive`** which has no public constructor and can be 
instantiated using one of its two named constructors:

-`CptArchives\Archive::for_type()` which takes as only argument the post type to build the object for 
  (as slug, so string, or as post type object).
-`CptArchives\Archive::for_current_type()` which takes no arguments and only works in post type archive pages 
  (when `is_post_type_archive()` is true).
  
Once an instance of the object is obtained, there are following methods available:

- `CptArchives\Archive::archive_title( string $before = '', string $after = '' ): string`
- `CptArchives\Archive::archive_content( string $more_link_text = null, bool $strip_teaser = false ): string`
- `CptArchives\Archive::archive_excerpt(): string`
- `CptArchives\Archive::archive_has_thumbnail(): bool`
- `CptArchives\Archive::archive_thumbnail_id(): int`
- `CptArchives\Archive::archive_thumbnail( string $size = 'post-thumbnail', array $attr = [] ): string`
- `CptArchives\Archive::archive_meta( string $key = '', bool $single = false ): mixed`

The signature is similar to post functions, and the naming should be self-explanatory.

The procedural API wraps the OOP API with functions in the `CptArchives` namespace.
API functions are named exactly like `CptArchives\Archive` object methods.

The signature is identical, but a parameter is always prepended: the post type to get information from, that can be 
provided as string (post type slug) or as post type object.

For example:

- `CptArchives\archive_title( $post_type, string $before = '', string $after = '' ): string`

and so on...

Note that is also possible to pass `null` as first argument to API function, and the current post type will be used, 
this only work when viewing post type archive in frontend (that is when `is_post_type_archive()` is true).

---

## Relevant Hooks

- **`'cpt-archives.can-edit-archive'`**. Filter. Allows to edit the capability necessary to edit a Post type archive
  (by default `$post_type->cap->edit_others_posts`).
- **`'cpt-archives.can-edit-archive'`**. Filter. Allows to edit for which post types the library should register
  admin screen and API. By default, all public, non builtin, post types that support archives.
- **`'cpt-archives.cpt-archive-args'`** Filter. Allows to edit the registration arguments for the intern CPT used to
  manage archives.

---

## Requirements

- PHP 7+
- Composer to install

---

## Installation

Via Composer, package name is **`inpsyde/cpt-archives`**.

---

## License and Copyright

Copyright (c) since 2017 Inpsyde GmbH.

"CPT Archives" code is licensed under [GPLv2+ license](LICENSE).

The team at [Inpsyde](https://inpsyde.com) is engineering the Web since 2006.
