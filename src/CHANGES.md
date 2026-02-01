## 1.0.0-6 (January 2026):

---

### Improvements:
- Before initiating a Long Task, an additional check was implemented to prevent duplicate task execution and avoid polluting the task pool.
- Automatic of DNS records is now possible for subdomains and additional domains in Plesk subscriptions
- Synchronization status updates were automatic until the introduction of Plesk's Long Tasks. We now relay task status to the React frontend by listening for the plesk:taskComplete event.

### Bug Fixes:

* Correctly processing the deregistration of a domain when the domain was deleted from the deSEC dashboard, while the extension page is still open(and more accurate data wasn't fetched)


## 1.0.0-5 (November 2025):

---

This release incorporates recommendations from the Plesk Extension Certification team, bringing improved automation, better stability, and critical bug fixes.

### Improvements:
- Newly registered domains are now automatically synced with deSEC
- Introduced Plesk Long Task support to improve performance and reliability of API operations
- Added the ability to open a domain's details tab directly from the extension
- Added full IDN (Internationalized Domain Name) support
- Domain renames are now handled automatically (re-registration and deSEC sync included)

### Fixes:
- Fixed an issue where domains deleted from the Plesk dashboard were not removed from deSEC
- Resolved a problem where the extension continued syncing zones that had already been removed from deSEC

## 1.0.0-4 (October 2025):

---

Following the recommendations of the Plesk Extension Certification team, this release includes the following improvements & fixes:

### Improvements:

- Removed redundant bootstrap.php file and tested the extension installation process.

### Bug Fixes:
- Domain wasn't properly deleted from deSEC because of the bool value returned from `normalizeBool` function.
- Both bulk `Enable/Disable auto-sync` buttons were capable of triggering the reverse action as well.


## 1.0.0-3 (September 2025):

---

Based on the recommendations from the Plesk Extension Certification team, this release provides the following fixes:

### Improvements:

- Switched to using the init() method instead of the default constructor in ApiController.php
- Removed isAdmin() redundant check
- Fixed composer.json file placement and published the correct vendor/ directory
- Updated axios to 1.12.2

### Bug Fixes:

Bug Fixes:

- Newly created tokens used in a GET request on `auth/account/` received 403(expected behaviour), 
but instead of the response being passed back to the validateToken(), and treated according to the 401/403 cases, 
the response was being picked up as an error, and the entire token validation process would halt.


## 1.0.0-2 (September 2025):

---

This release contains fixes recommended by the Plesk Extension Certification team; therefore:

### Improvements:

- Removed the favicon.ico file - unnecessary according to Plesk documentation
- Resized the screenshots to 1024x768
- Uploaded a 128x128 icon of the extension
- Added description and release tag in the meta.xml file
- Implemented Composer in the plib/ directory
- Updated setting names accordingly
- Logging is now handled by a dedicated Logger class
- Removed log verbosity UI toggle. Log verbosity is controlled using the standard Plesk `[log]filter.priority` setting in panel.ini instead.
- Implemented additional validation for backend API endpoints receiving data from the frontend of the extension
- Refactored to remove duplicate code - all deSEC API requests use a central method to ease ongoing maintenance

### Bug Fixes:
- deSEC domain "registration" status is inaccurate when registering multiple domains, if deSEC zone creation for one of the domains throws an error


## 1.0.0 (August 2025):

---

### Features

- Automatic creation of DNS zone in deSEC for domains hosted on the Plesk server or
  with authoritative DNS managed by the Plesk server
- One-way synchronisation of the primary DNS zone (managed by Plesk) with the secondary zone(deSEC)
- Ability to disable DNS synchronisation for any individual domain (e.g. for domains with DNS served or managed elsewhere)
- Ability to perform the following bulk operations of selected DNS zones:
    * Creation of new zones at deSEC
    * Manual synchronisation
    * Enable/Disable automatic synchronisation
- Sort by domain name or last synchronisation status
- (Optional) automatic deletion of the DNS zone from deSEC when a domain is removed from Plesk
- Enable/disable verbose logging of user's actions and API responses within extension settings for troubleshooting purposes
