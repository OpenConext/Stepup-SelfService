Step-up Self-Service
====================

[![Build Status](https://travis-ci.org/OpenConext/Stepup-SelfService.svg)](https://travis-ci.org/OpenConext/Stepup-SelfService) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/OpenConext/Stepup-SelfService/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/OpenConext/Stepup-SelfService/?branch=develop) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/d3edfdf9-2619-49d2-8f6f-cacc5492ce83/mini.png)](https://insight.sensiolabs.com/projects/d3edfdf9-2619-49d2-8f6f-cacc5492ce83)

This component is part of "Step-up Authentication as-a Service" and requires other supporting components to function. See [Stepup-Deploy](https://github.com/OpenConext/Stepup-Deploy) for an overview.

## Requirements

 * PHP 7.2
 * [Composer](https://getcomposer.org/)
 * A web server (Apache, Nginx)
 * Graylog2 (or disable this Monolog handler)
 * A working [Gateway](https://github.com/OpenConext/Stepup-Gateway)
 * Working [Middleware](https://github.com/OpenConext/Stepup-Middleware)

## Installation

Clone the repository or download the archive to a directory. Install the dependencies by running `composer install && yarn install`.

## Updating translations

Run the following command to extract translation strings from templates, form labels, etc:

```bash
bin/extract-translations.sh
```

Then, translate the strings using the web interface available at: https://ss-dev.stepup.coin.surf.net/app_dev.php/_trans/

For more information about the JMSTranslationBundle, see http://jmsyst.com/bundles/JMSTranslationBundle

## Release strategy
Please read: https://github.com/OpenConext/Stepup-Deploy/wiki/Release-Management fro more information on the release strategy used in Stepup projects.
