## Changelog

__3.4.0__

- New events for adding and removing user capabilities

__3.3.1__

- Fixed bug where not all users where displayed in filter drop down
- Added translations for Portuguese

__3.3.0__

- improved SQL table definition
- translation updates

__3.2.5__

- New event: Deleting visits using GDPR tools

__3.2.4__

- Added translations for Turkish and French

__3.2.3__

- Added translation for Albanian
- New events for two factor auth
- mask passwords in settings activities

__3.2.2__

- Added translations for Spanish and Italian
- Compatibility / Fix for PHP 7.3

__3.2.1__

- Improved compatibility with older Matomo versions

__3.2.0__

- New event: Scheduled report unsubscription
- New event: Raw data anonymization triggered
- Support new "Write" role

__3.1.2__

- Rename Piwik to Matomo

__3.1.1__

- Possibility to configure permission required to view activity log (view / admin / super user)
- Show IP and country as tooltip on country flag in activity log

__3.1.0__

- Log country and ip of user for all activities

__3.0.1__

- Post an event when logging an activity so plugins can modify it

__2.0 / 3.0__

Tracked core events:

* Annotation added
* Annotation changed
* Annotation deleted
* Custom Alert added
* Custom Alert changed
* Custom Alert deleted
* Custom Dimension configured
* Custom Dimension changed
* Goal added
* Goal changed
* Goal deleted
* Measurable created
* Measurable removed
* Plugin activated
* Plugin deactivated
* Privacy: Enable DNT support
* Privacy: Disable DNT support
* Privacy: Set IP Anonymise settings 
* Privacy: Set delete logs settings
* Privacy: Set delete reports settings
* Privacy: Set scheduled report deletion setting
* Scheduled report created
* Scheduled report changed
* Scheduled report deleted
* Segment created
* Segment updated
* Segment deleted
* Site access changed
* Site settings updated
* Super user access changed
* System settings updated
* User created
* User removed
* User changed
* User settings updated
* User sets preference

Other plugins' activity log events:

* A/B testing
    - Experiment added
    - Experiment settings updated
    - Experiment status changed (Started, Finished, Archived)
    - Experiment deleted
* Referrers Manager
    - Search engine added
    - Search engine removed
    - Social network added
    - Social network removed
    
