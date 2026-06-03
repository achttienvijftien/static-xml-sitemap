# Static XML Sitemap plugin

The static XML Sitemap plugin generates static XML sitemaps for large-scale WordPress instances.

## Contributing

### Requirements

- [Docker](https://docs.docker.com/get-started/get-docker/)
- [Composer](https://getcomposer.org/download/)
- [nvm](https://github.com/nvm-sh/nvm#install--update-script)
- [Yarn](https://yarnpkg.com/getting-started/install)

### Testing

#### Setup

1. Install Composer packages: `composer install`
2. Install the correct Node.js version: `nvm install`
3. Install NPM packages: `yarn`
4. Start wp-env `yarn wp-env start`
5. Check if test suite is ready: `yarn test`
6. When test result is OK you're ready to start writing tests in test/php
