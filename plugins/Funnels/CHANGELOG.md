## Changelog

3.1.22
- Fix possible error in an API when a certain period/date combination is used

3.1.21
- Improve lock creation when archiving

3.1.20
- Fix exits were not always calculated correctly

3.1.19
- Rearchive some previously archived data for better accuracy

3.1.18
- Fix possible notice in row evolution

3.1.17
- Improve compatibility with Matomo 3.12
- Add new language

3.1.16
- Reuse transaction level from core when possible

3.1.15
- Make query that populates funnel data non-locking by setting different transaction level 

3.1.14
- Support usage of a reader DB when configured

3.1.13
- Archiving improvements
- Translation updates

3.1.12
- Improve adding primary key if there are some duplicate keys

3.1.11
- Add primary key to funnel log table for better replication

3.1.10
- Performance improvements when generating reports

3.1.9
- Support more translations
- Use new brand colors

3.1.8
- Added social media support
- Internal changes

3.1.7
- Fix possible error in sales funnel

3.1.6
- Support new "Write" role

3.1.5
- When a user reloads a page that is part of funnel, do not show it as an exit page
- Improve archiver to let more archivers run in parallel

3.1.4
- Validate any entered regular expression when configuring a funnel

3.1.3
- Changed the default type for a DB column to unsigned

3.1.2
- Renamed Piwik to Matomo

3.1.1
- Faster archiving

3.1.0
- Support matching of page titles, event categories, event names, and event actions

3.0.4
- Make sure validating URL funnel works correctly with HTML entities

3.0.3
- Add possibility to define sales funnel

3.0.2
- Make sure HTML entities can be used in patterns

3.0.1
- Performance improvement in Archiver

3.0.0 

- Initial version
