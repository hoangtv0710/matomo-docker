## Changelog
3.3.2 
- When force SAML is enabled, A Super User should be able to reset its password
- Allow customers to decide when session expires (Matomo settings or SAML SessionNotOnOrAfter value)
- Be able to change user profile settings. There was an error raised even if email/password was not changed on the view that prevented updating other user settings fields.
- Update php-saml dependency to 3.2.1
- Adds Dutch translations

3.3.1
- Fix issue w/ setting initial websites with view access for new users when trying to login w/ existing user.

3.3.0 
- Compatibility with PHP 7.2 and PHP 7.3
- Fix issue with Just-in-time provisioning of users and assigning 'Initial Websites With View Access For New Users' when 'Access Synchronization Settings' is disabled or access data could not be retrieved from SAML attributes
- Allow using `?normal` URL query parameter (to force standard login screen) even when ForceSAML is enabled 
- When logging in, redirect to the URL that was requested before login
- New diagnostics checking that openssl PHP extension is activated
- When importing new metadata, old metadata is now removed
- Whenever an error occurs during SAML process, the error message is now displayed to the user
- Single Log Out (SLO) was not always working
- Updated translations

3.2.2
- Updated translations

3.2.0
- Add new feature to force SAML authentication. You can now force users to use SAML authentication by enabling the “Force SAML Login” setting. Doing this will redirect all users directly to the Identity Provider, so the Matomo login screen will never be displayed. Super Users will still have to login normally to, for example, configure the SAML plugin. Super Users can login through the Matomo login screen by appending ?normal to the URL when visiting Matomo. (Note: other users will not be able to login this way.)

3.1.0
- Compatibility with Matomo 3.6.0 

3.0.3
 - Fixed issue with SAML redirect URLs using HTTP instead of HTTPS when Matomo is setup behind a [reverse proxy](http://piwik.org/faq/how-to-install/faq_98/).
