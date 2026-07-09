# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Testing
- `bin/phpunit` - Run the test suite against a hot Docker container (starts the compose stack on first use); accepts all phpunit arguments, e.g. `bin/phpunit tests/php/Unit` or `bin/phpunit --filter InstallerTest`
- `composer test` - composer install + `bin/phpunit`
- `composer test:watch` - Run tests in watch mode with live reload

Environment variables for `bin/phpunit`:
- `PHPUNIT_PULL=0` - Skip pulling images when starting the compose stack
- `PHPUNIT_TTY=0|1` - Force-disable or force-enable a TTY (default: auto-detect)
- `WP_TESTS_DB_NAME=<name>` - Run against an isolated database, created on the fly (useful for parallel runs)

Tests live in `tests/php/Unit` and `tests/php/Integration` (both run inside the WordPress test framework; `tests/bootstrap.php` boots the plugin and installs its tables). The `wordpress` image tag in `compose.yaml` must match the `wp-phpunit/wp-phpunit` version in `composer.lock`.

### Code Quality
- `composer lint` - Run PHP CodeSniffer (phpcs) to check code standards
- `composer format` - Run PHP Code Beautifier (phpcbf) to fix code style issues

### Environment Setup
1. `composer install` - Install PHP dependencies
2. `bin/phpunit` - Pulls images, starts the Docker test environment and runs the suite

## Architecture Overview

This is a WordPress plugin that generates static XML sitemaps for large-scale sites. The architecture follows a modular provider-based pattern with dependency injection.

### Core Components

**Bootstrap & Container System:**
- `Bootstrap` class handles plugin initialization and error handling
- `Container` class provides dependency injection using PSR-11 container interface
- Configuration loaded from `config/parameters.php`

**Provider Architecture:**
The plugin uses a provider pattern where each content type (Post, User, Term) has:
- `Provider` - Main business logic for generating sitemap data
- `ItemStore` - Data persistence and retrieval
- `Watcher` - Hooks into WordPress events to track changes
- `Indexer` - Batch processing for large datasets

**Content Types:**
- **Post Provider** (`src/Post/`) - Handles posts, pages, custom post types
- **User Provider** (`src/User/`) - Handles user profiles
- **Term Provider** (`src/Term/`) - Handles categories, tags, custom taxonomies

**Sitemap Management:**
- `Sitemap` entity tracks indexing state with statuses: unindexed, indexed, indexing, updating
- `SitemapStore` manages sitemap metadata
- `SitemapRenderer` generates XML output
- `SitemapIndexRenderer` creates sitemap index files
- `Router` handles sitemap URL routing

**Job System:**
- Asynchronous processing using `JobStore` for large dataset indexing
- CLI commands via WP-CLI: `CreateIndex` and `RunJobs`

**WordPress Integration:**
- `Installer` handles plugin activation/deactivation
- `WordPressSeo` compatibility layer for Yoast SEO integration
- Hooks system for WordPress events (post save, user update, etc.)

### Key Patterns

**Entity Pattern:**
All data objects implement `EntityInterface` and use `EntityTrait` for consistent property access and JSON serialization.

**Store Pattern:**
Data stores implement `ItemStoreInterface` with methods for CRUD operations, pagination, and bulk operations.

**Status Tracking:**
Sitemaps track their state through status fields to handle incremental updates and prevent race conditions during indexing.

### File Organization

- `src/Container/` - Dependency injection container
- `src/Command/` - WP-CLI commands
- `src/Post/`, `src/User/`, `src/Term/` - Content type providers
- `src/Sitemap/` - Sitemap entities and stores
- `src/Renderer/` - XML generation
- `src/Compatibility/` - Third-party plugin integration
- `config/` - Configuration files
- `tests/php/` - PHPUnit tests

## Code style guidelines

### Code style standards

Code should follow the WordPress codestyle with these notable exeptions:

- Arrays MUST use the short form instead of `array()`
- Class names should be CamelCased and follow the PSR-4 standard.

### Avoiding deeply nested code

Deep nesting of code should be avoided. Within functions and methods, code should ideally indented
one level, and must not be indented more than two levels. Apply the following rules to further
minimize nesting:

- Prefer early returns over guarding the happy path with a if statement. In loop control structures,
  use `continue` to accomplish this.
- Avoid else-clauses by first specifying defaults for variables, then only assigning them a specific value
  if available.
- Merge nested if structures
- Use ternaries instead of if-statements if the branching expressions are not overly long. Do not
  nest ternaries.

### Typing

- Use typed parameters wherever possible.
- The first parameter of a method that is hooked to a WordPress action or filter MUST NOT be typed.
- For parameters that cannot be typed, document the expected type in the docblock. Use `<expected-type>|mixed` as the type.
- For array parameters, document the actual element type in the docblock if it can be deduced.
- Type return values as much as possible. Use `void` for void methods.

### Comments

- Only add docblocks for parameters that cannot be typed or where additional type information is needed (i.e. arrays).
- Do not add inline comments merely describing what the code is doing. Let the code speak for itself.
- Add a file-level docblock:
  - Description is the filename without extension.
  - Add a `@package` annotation with the namespace of the file.
- Add a class-level docblock for classes with the description `Class <class-name>`


