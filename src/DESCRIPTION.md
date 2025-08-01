Automatically push DNS zones defined in Plesk to deSEC.

This enables you to use deSEC for DNS hosting, benefitting from all of their nice features
and security functionality, whilst using Plesk to automate DNS management for your hosted domains.

deSEC is a registered non-profit organisation based in Germany. They operate a
not-for-profit anycast DNS hosting service as part of their mission to achieve
large-scale adoption of IT security techniques such as DNSSEC.

This extension is provided free and developed by Layershift as part of our
support and sponsorship of deSEC. Please submit issues, feature requests,
and PRs to https://github.com/layershift/ext-ls-desec/issues

Please consider donating to the deSEC project if you find this extension and their DNS hosting service useful!

## Known issues and limitations
* Domain aliases are not currently synchronised (only domains and subdomains)
* All domains are listed at once in a single table without pagination; this might cause performance issues when working with a large number of domains. 
  Pagination will be implemented in the next version of the extension(v1.0.1)






