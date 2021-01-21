## Changelog

3.1.27
- Improve archive command

3.1.26
- List dimension IDs in glossary

3.1.25
- Add new config setting `custom_reports_disabled_dimensions` to disable dimensions

3.1.24
- Ignore new region dimension as it won't work

3.1.23
- Fix view link in manage custom reports may not work when report is configured for all websites
- Fix goalId archiving

3.1.22
- Fix archiver

3.1.21
- Add possibility to set max execution time

3.1.20
- Better segment filter check

3.1.19
- Apply segment filter in segmented visitor log
- Better support for Matomo 3.12

3.1.18
- Sort aggregated reports before generating the report (week, month, year, range)
- Compatibility with Matomo 3.12

3.1.17
- Add more options to archive command

3.1.16
- Support new segmentation in Matomo 3.12

3.1.15
- Compatibility with Matomo 3.12

3.1.14
- Show search box for entities
- Support usage of a reader DB when configured

3.1.13
- Enable more dimensions (visitorId, geolocation)

3.1.12
- Add more translations
- Make sure a report can be moved to its own page after it was assigned to another page

3.1.11
- Add Turkish translation
- Enable Order ID dimension

3.1.10
- Improve report generation for some combination of dimensions

3.1.9
- Fix report preview unter circumstances doesn't show column names when no report is configured yet

3.1.8
- Add config setting to always show unique visitors in all periods

3.1.7
- Improve handling of unique visitors and users

3.1.6
- Use correct category names
- Calculate unique visitors and users from raw data for periods != day if enabled in config in evolution graphs when only these metrics are used

3.1.5
- Support more languages
- Added command to archive reports in past

3.1.4
- Support new languages
- Use new brand colors
- Ensure segment definition is shown correctly

3.1.3
- Fix possible combination with event name and event value may not return a result

3.1.2
- Add dimensions and metrics information to glossary
- Support new "Write" role

3.1.1
- Make sure pie and bar graphs show available columns

3.1.0
- Support [Roll-Up Reporting](https://plugins.matomo.org/RollUpReporting). Create custom reports across multiple sites.

3.0.6
- Prevent possible fatal error when opening manage screen for all websites
- New config setting `custom_reports_validate_report_content_all_websites` which, when enabled under the `[CustomReports]` section, allows the creation of Custom Reports on "All websites", even those that contain "Custom dimensions" or other entities which may not be present on all websites. This is useful when you have many (or all) websites with the exact same dimensions Ids and/or Goals Ids across all websites.


3.0.5
- Renamed Piwik to Matomo

3.0.4
- Prevent possible error when putting a custom report to another custom report page

3.0.3
- Prevent possible problems with custom dimensions in custom reports when also using roll-ups.

3.0.2
- Added German translation
- When generating report data and data needs to be truncated, make sure to sort the data by the first column of the report
- Make number of rows within a datatable configurable 
- Make sure aggregated reports are truncated if needed

3.0.1
- Make sure custom reports category can be always selected when creating a new custom report

3.0.0
- Custom Reports for Piwik 3
