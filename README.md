Step-up Self-Service
====================

[![Build Status](https://travis-ci.org/SURFnet/Stepup-SelfService.svg)](https://travis-ci.org/SURFnet/Stepup-SelfService) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/SURFnet/Stepup-SelfService/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/SURFnet/Stepup-SelfService/?branch=develop) [![SensioLabs Insight](https://insight.sensiolabs.com/projects/d3edfdf9-2619-49d2-8f6f-cacc5492ce83/mini.png)](https://insight.sensiolabs.com/projects/d3edfdf9-2619-49d2-8f6f-cacc5492ce83)

## Requirements

 * PHP 5.6 (Note that we test on 7.0 but do not run or support it officially)
 * [Composer](https://getcomposer.org/)
 * A web server (Apache, Nginx)
 * Graylog2 (or disable this Monolog handler)
 * A working [Gateway](https://github.com/SURFnet/Stepup-Gateway)
 * Working [Middleware](https://github.com/SURFnet/Stepup-Middleware)

## Installation

Clone the repository or download the archive to a directory. Install the dependencies by running `composer install`.

Run `app/console mopa:bootstrap:symlink:less` to configure Bootstrap symlinks.
