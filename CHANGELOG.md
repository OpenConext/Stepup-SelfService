# Changelog

## 3.5.5
- Make the info and health endpoints available on both `/` and `/internal/` paths. 'Deprecating' the original /health and /info endpoints.

## 3.5.4
- Introduce a Github Actions workflow for auto-building tags

## 3.5.3
- Added browserlist entry in package.json to ensure IE 11 support

## 3.5.2
Update Stepup Bundle and Http Foundation to prevent deprecation warnings

## 3.5.1
Update stepup-saml-bundle and stepup-bundle

## 3.5.0
**Feature**
* Self vetting (vet token with previously RA vetted token) #227

## 3.3.0
**Feature**
* Add GSSP UserAttributes extensions to registration-SAMLAuthnRequest to pass user-information to GSSP's

## 3.0.3:
 * Add GSSP UserAttributes extensions to registration-SAMLAuthnRequest to pass user-information to GSSP's
 * Update dependencies

## 3.2.0
Platform update
 * Drop support for php 5.6, Use PHP7.2
 * Upgrade to Synfony 4
 * Allow HTML in explanation/description
 * Update dependencies

## 3.1.0
A release with bugfixes after initial FGA tests
 * Fix exception controller authentication exceptions #179
 * Update MW-client-bundle #178
 * Install various security updates #177

## 3.0.1 
This is a security release that will harden the application against CVE 2019-346
 * Upgrade Stepup-saml-bundle to version 4.1.8 #176
 
## 3.0.0 FGA (fine grained authorization)

The new fine grained authorization logic will allow Ra's from other institutions to accredidate RA's on behalf of another organisation. This is determined based on the institution configuration. https://github.com/OpenConext/Stepup-Deploy/wiki/rfc-fine-grained-authorization/b6852587baee698cccae7ebc922f29552420a296

**Features & Bugfixes**
The changes to SelfService in regards to the FGA changes only where to remain compatible with API changes made for Stepup-RA. No new features have been added.

## 2.10.7
This is a security release that will harden the application against CVE 2019-346
 * Upgrade Stepup-saml-bundle to version 4.1.8 #175

## 2.10.6
**Bugfix**
 * Create a pdf base template to support local url's #168

## 2.10.5
**Bugfix**
* Create a pdf base template to support local url's #168

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
