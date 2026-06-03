# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2026-06-03

### Fixed

- WP-2724 - Cast `WP_Post::$post_author` to int in `User\Watcher` to prevent TypeError on `post_updated` and `save_post`.

## [1.2.0] - 2026-05-18

### Fixed

- WP-2681 - Prevent warning and TypeError when a sitemap item references a missing user
- Disable `WPSEO_Sitemaps_Admin` hooks when replacing Yoast sitemaps. Its `transition_post_status` handler invalidates the WordPress `lastpostmodified` object cache on every post publish, causing expensive `ORDER BY post_modified_gmt DESC` full table scans on high-volume sites.

## [1.1.1] - 2026-01-20

### Fixed

- Add multisite support to (parts of) queries in WordPress SEO compatibility class
- Fixed slug of author sitemap urls

## [1.1.0] - 2025-12-23

### Fixed

- Increase lock timeout to 5 minutes
- Make is_updating() behave as expected and add checks for indexing status

### Added

- Add runtime cache flushing to decrease memory pressure

### Changed

- Clean up lock config
- Extract indexer inner loop to a new method
- Tweak lock max tries and refresh lock in indexer

## [1.0.0] - 2025-12-04

- Initial release

[unreleased]: https://github.com/achttienvijftien/static-xml-sitemap/compare/1.2.1...main

[1.2.1]: https://github.com/achttienvijftien/static-xml-sitemap/compare/1.2.0...1.2.1

[1.2.0]: https://github.com/achttienvijftien/static-xml-sitemap/compare/1.1.1...1.2.0

[1.1.1]: https://github.com/achttienvijftien/static-xml-sitemap/compare/1.1.0...1.1.1

[1.1.0]: https://github.com/achttienvijftien/static-xml-sitemap/compare/1.0.0...1.1.0

[1.0.0]: https://github.com/achttienvijftien/static-xml-sitemap/compare/fa9730d...1.0.0



