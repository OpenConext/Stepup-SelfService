Step-up Self-Service
====================

[![Run QA tests (static analysis, lint and unit tests)](https://github.com/OpenConext/Stepup-SelfService//actions/workflows/test-integration.yml/badge.svg)](https://github.com/OpenConext/Stepup-SelfService/actions/workflows/test-integration.yml)  
This component is part of "Step-up Authentication as-a Service" and requires other supporting components to function. See [Stepup-Deploy](https://github.com/OpenConext/Stepup-Deploy) for an overview.

## Requirements

 * PHP 8.2
 * [Composer](https://getcomposer.org/)
 * A web server (Apache, Nginx)
 * A working [Gateway](https://github.com/OpenConext/Stepup-Gateway)
 * Working [Middleware](https://github.com/OpenConext/Stepup-Middleware)

## Installation

Clone the repository or download the archive to a directory. Install the dependencies by running `composer install && composer frontend-install`.

## Updating translations

Run the following command to extract translation strings from templates, form labels, etc:

```bash
bin/extract-translations.sh
```

Then, translate the strings using the web interface available at: https://ss-dev.stepup.coin.surf.net/app_dev.php/_trans/

For more information about the JMSTranslationBundle, see http://jmsyst.com/bundles/JMSTranslationBundle

## Release strategy
Please read: https://github.com/OpenConext/Stepup-Deploy/wiki/Release-Management for more information on the release strategy used in Stepup projects.
