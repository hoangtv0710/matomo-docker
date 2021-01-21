## Changelog

__3.6.0__
* Updated dependencies to fix possible compatibility issues with other plugins
* Translation updates

__3.5.1__
* Fix image path for WordPress installation

__3.5.0__
* Update database table definitions to work with `utf8mb4` without `innodb_large_prefix`

__3.4.2__
* Translation updates
* Tiny code improvements
* Lower positions will now be shown as better (requires Matomo 3.13)

__3.4.1__
* Compatibility with upcoming Matomo 3.12.0
* Removed obsolete Code for importing Google Crawl Stats & Errors
* Translation updates

__3.4.0__
* Use imported keywords for (subtable) reports of more reports:
    * *Acquisition > All channels > Channel type*
    * *Acquisition > All channels > Referrers*
    * *Acquisition > Search Engines*
* Show related reports for reports showing imported keywords to show originally tracked keywords instead
* Translations for German and Albanian

__3.3.2__
* Fix sorting for keyword tables
* Improved compatibility with Roll-Up Reporting plugin
* Translation updates

__3.3.1__
* Ensure at least one keyword type is configured for Google imports
* Deprecated Property Set and Android App imports
* Improve sorting of keyword reports by adding a secondary sort column
* Added proper handling for new Domain properties on Google Search Console

__3.3.0__
* Fixed bug with incorrect numbers for reports including day stats for Bing
* Improved validation of uploaded Google client configs
* Updated dependencies
* Deprecated Google Crawl Errors reports (due to Google API deprecation).
  Old reports will still be available, but no new data can be imported after end of March '19.
  New installs won't show those reports at all.
* Translation updates

__3.2.7__
* Fixed notice occurring if search import is force enabled

__3.2.6__
* Allow force enabling crawling error reports.
* Improve handling of Google import (avoid importing property set data since it does not exist)

__3.2.5__
* Security improvements
* Theme updates

__3.2.4__
* Improve handling of Bing Crawl Errors (fixes a notice while import)
* Improve Google import handling of empty results
* Security improvements
* UI improvements
* Translations for Polish

__3.2.3__
* Various code improvements
* Translations for Chinese (Taiwan) and Italian

__3.2.0__
* Changes the _Combined Keywords_ report to also include keywords reported by Referrers.getKeywords
* Adds new reports _Combined imported keywords_ (which is what the combined keywords was before)
* Replaces Referrers.getKeywords reports in order to change name and show it as related report
* Move all reports to the Search Engines & Keywords category (showing Search Engines last)

__3.1.0__
* New crawl errors reports und Pages > crawl errors showing pages having crawl issues on Google and Bing/Yahoo!

__3.0.10__
* Improved error handling
* Row evolution for combined keywords reports
* Fixed error when generating scheduled reports with evolution charts

__3.0.9__
* Renamed Piwik to Matomo

__3.0.8__
* Possibility to show keyword position as float instead of integer

__3.0.7__
* Added commands to trigger import using console command
* Various UI/UX improvements

__3.0.6__
* Now uses Piwik proxy config if defined

__3.0__
* Possibility to import keyords & crawl stats from Google Search Console
* Setting per website if web, image and/or video keywords should be imported
* Possibility to import keywords & crawl stats from Bing/Yahoo! Webmaster API
