## Changelog

3.1.28
- Performance improvements by forcing mysql to use a specifc index on few queries

3.1.27
- Archiving improvements

3.1.26
- More efficient tracking

3.1.25
- Fix changing the graph in row evolution fails with an error

3.1.24
- Let users configure auto form creation up to 150 forms

3.1.23
- Remove form log entries for deleted forms monthly

3.1.22
- Improvements for Matomo 3.12 to support faster segment archiving

3.1.21
- Show search box for entities
- Support usage of a DB reader when configured

3.1.20
- Fix SQL error in segment if a table prefix is specified

3.1.19
- Prevent memory issues in archiver by limiting the number of rows to 500

3.1.18
- Internal tracker performance improvements

3.1.17
- Improve isBlank detection for radio and check box fields.
- Add new language
- Remove live reports from scheduled reports

3.1.16
- Improve update script that adds primary key.

3.1.15
- Add primary key to funnel log table for better replication

3.1.14
- Limit form fields to 2500 fields per form
- title-text of JavaScript Tracking option help box shows HTML
- Fix row evolution for form field reports was broken
- Don't show archived or deleted forms in the live reports

3.1.13
- Queue tracking requests when possible for better performance
- Improve compatibility with Matomo 3.9 (visitor log)
- Limit some field values to ensure they will be recorded

3.1.12
- Improve compatibility with Queued Tracking

3.1.11
- Improve compatibility with Tag Manager

3.1.9
- Support more translations
- Use new brand colors
- Do not fail track request if time to submit is too large

3.1.8
- Improve drop off field calculation if form was converted but not submitted

3.1.7
- Prevent a browser from becoming unresponsive when a form has too many fields
- Make sure form fields are encoded correctly when processing a tracking request

3.1.6
- Support new "Write" role

3.1.5
- Ignore tracking requests that don't match any form to avoid for example visits with no actions around midnight in edge cases

3.1.4
- Increase size of database table column "fields" to mediumtext

3.1.3
- Validate any entered regular expression when configuring a form

3.1.2
- Added logic to support for more Matomo GDPR features.

3.1.1
- Added logic to support Matomo GDPR features.
- Link form name to reports in visitor log & profile

3.1.0
- Improve how form and form field interactions are shown in the visitor log
- Improved API response for form interactions in the Live API methods
- Support `matomo` keyword in attributes and properties when customizing the tracking

3.0.15
- Prevent possible fatal error when opening manage screen for all websites
- Keep a visitors session (visit) alive every couple of minutes
- Better error message when renaming a form but the name of the form is already in use

3.0.14
- Renamed Piwik to Matomo

3.0.13
- Fix possible bug in visitor profile where a wrong value may be assigned.

3.0.12
- Fix possible bug in visitor log when there are no visitors

3.0.11
- Improve memory usage and performance of performance of visitor log and visitor profile integration

3.0.10
- Improve performance of visitor log and visitor profile
- Format sparkline metrics
- Fix a bug when viewing visitor log as user with view access only

3.0.9
- Show form interactions in visitor log and visitor profile

3.0.8
- Fix max 100 forms per page where loaded when managing forms for a site
- Added support for Custom Reports plugin
- Send several form views along a page view instead of only one to reduce server load

3.0.7
- Fix a form conversion may under circumstances not be tracked if a form is interacted with without any break or when it only includes a submit button.

3.0.6
- Make sure to count a new form start after a form submission
- Prevent some edge case racing conditions when a form submit and conversion is tracked directly after another

3.0.5
- Make sure form rules work fine when using HTML entities

3.0.4
- Add support for TinyMCE
- Add support for select2

3.0.3
- Enrich system summary widget with the number of forms
- Fix all columns view in Live widget did not show label

3.0.2
- Fix a tracking bug on IE9 and older

3.0.1
- Show Manage Forms in reporting menu

3.0.0 
- Initial version
