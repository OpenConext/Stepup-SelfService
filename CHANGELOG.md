# Changelog

## 3.0.0 FGA (fine grained authorization)

The new fine grained authorization logic will allow Ra's from other institutions to accredidate RA's on behalf of another organisation. This is determined based on the institution configuration. https://github.com/OpenConext/Stepup-Deploy/wiki/rfc-fine-grained-authorization/b6852587baee698cccae7ebc922f29552420a296

**Features & Bugfixes**
The changes to SelfService in regards to the FGA changes only where to remain compatible with API changes made for Stepup-RA. No new features have been added.

## 2.10.4
**Improvement**
* Optimized the PSR-4 autoload configuration

## 2.10.3
**Bugfix**
* Rebuilt the Composer lockfile

## 2.10.2
**Improvements**
* Open help in new tab #165
* Introduce multi-lingual logout redirect #164

**Improvments for testing**
* Ensure middleware API is used in test mode #157
* Use 'shared' certificates and private keys when in test mode #157
* Provide behat support #156
* Set Symfony environment from cookie if available #229 

**Bugfixes**
* Fix art for authentication #163
* Fix mpdf relative url's and logging #162 

## 2.10.1
**Bugfixes**
* Prevent form loading issue #155

## 2.10.0
**Features & Bugfixes**
* Fixed missing translations for validation messages on forms #154

**Improvements**
* Symfony 3.4.15 upgrade #153
* Behat test support #152