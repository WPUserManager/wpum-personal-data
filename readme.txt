=== WP User Manager Personal Data ===
Contributors: alessandro.tesoro, wpusermanager
Tags: community, member, gdpr, data export, data erasure
Requires at least: 4.9.6
Tested up to: 4.9
Requires PHP: 5.5
Stable tag: 1.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allow the user to request an export or erasure of personal data from the frontend account page. This is an addon for WP User Manager.

== Description ==
= WP User Manager =

Allow the user to request an export of personal data and request erasure of personal data **from the account page**. This is an addon for WP User Manager.

> This is a free add-on for the [WP User Manager plugin](https://wordpress.org/plugins/wp-user-manager/). You must download and install the [WP User Manager plugin](https://wordpress.org/plugins/wp-user-manager/) before you can use this addon.
> Download WPUM for free here [https://wordpress.org/plugins/wp-user-manager/](https://wordpress.org/plugins/wp-user-manager/)

[website](https://wpusermanager.com) | [addons](https://wpusermanager.com/addons) | [documentation](http://docs.wpusermanager.com/) | [support](https://wpusermanager.com/support/)

= How it works =

Once installed, a new tab called "Personal data" will be created within the **user's account page**. The user will be requested to type his current password before requesting an export of data or before requesting erasure of personal data.

Upon successful submission, requests will be logged under "Tools -> Export personal data" and/or "Tools -> Erase personal data" within your admin dashboard from where you can manage requests.

This add-on follows the way WordPress itself provides integration for privacy tools. The addon will evolve alongside WordPress when new privacy tools will be introduced.

= Email notifications =

When requesting an export or erasure of personal data, the user will receive a confirmation request email with a special link that once clicked, will mark the request as confirmed in your dashboard so that administrators can either send an export of data or erase personal data.

== Installation ==
Please refer to the [documentation here](https://docs.wpusermanager.com/category/422-personal-data).

== Screenshots ==
1. New tab within the account page from where users can process their own data.

== Changelog ==

= Version 1.1.3 =
- Fix: fatal error when the add-on could not find the activation class.

= Version 1.1.2 =
- Fix: compatibility with older version of php