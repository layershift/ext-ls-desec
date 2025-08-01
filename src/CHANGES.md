## 1.0.0 (August 2025):
---

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
