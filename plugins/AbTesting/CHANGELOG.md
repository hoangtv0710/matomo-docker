## Changelog

3.2.18
- Better support for Safari's new ITP
- Added new tracker methods `AbTesting::disableWhenItp`, `AbTesting::disable`, `AbTesting::enable`, `AbTesting::isEnabled`

3.2.17
- Ensure to set samesite flag when setting a cookie

3.2.16
- Fix a possible notice in RemainingVisitors calculation

3.2.15
- Prevent possible notice during archiving
- Added new language
- Better support for Matomo 3.12.0

3.2.14
- Use Reader DB in all archiver queries when configured

3.2.13
- Improvements for Matomo 3.12 to support faster segment archiving

3.2.12
- Show search box for entities
- Support usage of a reader DB when configured

3.2.11
- Adjust help text for page targeting
- Fix opacity for drop downs
- Added new language

3.2.10
- title-text of JavaScript Tracking option help box shows HTML
- Fix unsupported operand notice might appear under circumstances in the overview report

3.2.9
- Added various new languages
- Removed configuration for original redirect url as it should be configured and could lead to issues if it is.

3.2.8
- Improve compatibility with Matomo 3.9

3.2.7
- Ensure the color of a warning in the test is readable

3.2.6
- Improve compatibility with Tag Manager

3.2.5
- Support more languages
- Use new brand colors

3.2.4
- Add possibility to force multiple variations through a URL parameter when multiple tests are running on the same page

3.2.3
- Use API requests internally
- View user can now request the experiment configuration

3.2.2
- Support new Write role

3.2.1
- Show experiment participation in visitor log 

3.2.0
- Rename Experiments to A/B tests

3.1.11
- Prevent possible fatal error when opening manage screen for all websites
- Validate any entered regular expression when configuring an experiment
- Ignore URL parameters "pk_abe" and "pk_abv" in page URLs
- When tracking an A/B test, do not validate the target page server side

3.1.10
- Renamed Piwik to Matomo

3.1.9
- Fix only max 100 experiments where loaded when managing experiments for one specific site.
- Use better random number generators if available when using server side redirects feature.

3.1.8
 - Make sure to find all matches for a root folder when "equals simple" is used
 
3.1.7
- Fix typo in example embed code
- Improve variation detection by ignoring case

3.1.6
- Fix a possible notice during tracking
- Make sure HTML entities can be used in page targets

3.1.5
- When using an "equals exactly" comparison, ignore a trailing slash when there is no path set

3.1.4
- Fix a server side redirect issue where a stored cookie value might be ignored for the original version

3.1.3
- Enrich System Summary widget

3.1.2
- Show manage experiments in reporting menu

3.1.1
- Show summary row in overview report

3.1.0
- Added possibility to define redirects in UI
- Fix preview images was not shown

3.0.2
- Added new feature to force a specific variation via URL

3.0.1
- Added Experiments overview page
- When creating a new experiment for an ecommerce shop, pre-select Ecommerce Orders and Ecommerce Revenue success metric automatically
- Make sure A/B Test reports work when range dates are disabled 

3.0.0 
- Initial version
