=== EAD XML Importer ===
Contributors: yourusername
Tags: ead, xml, import, archive, custom post type
Requires at least: 5.0
Tested up to: 6.4.2
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Import EAD (Encoded Archival Description) XML files into WordPress custom post types.

== Description ==
EAD XML Importer allows you to import EAD (Encoded Archival Description) XML files into WordPress custom post types. The plugin supports both manual imports and automated scheduled imports.
Features:

Import EAD XML files from URLs
Convert EAD XML to custom post types
Support for ACF repeater fields
Fallback for non-ACF installations
Automated imports via WP Cron
Customizable field mappings

Supported Fields:

Title
Publisher
Publication Date
Creation Date
Languages
Physical Description
Unit Date
Corporate Name
Notes
Scope Content

== Installation ==

Upload the plugin files to the /wp-content/plugins/ead-xml-importer directory, or install the plugin through the WordPress plugins screen.
Activate the plugin through the 'Plugins' screen in WordPress
Configure the plugin settings under 'Settings > EAD XML Importer'

== Usage ==
Manual Import:

Go to Tools > EAD XML Importer
Enter the XML URL
Select import options
Click "Import"

Automated Import:

Go to Settings > EAD XML Importer
Configure your XML source URLs
Set up import schedule
Save settings

== Frequently Asked Questions ==

= Does this plugin require Advanced Custom Fields? =
No, but it works better with ACF Pro installed. The plugin includes a fallback for standard WordPress meta fields.

= Can I import multiple XML files? =
Yes, you can configure multiple XML sources for import.

= What post types are supported? =
By default, the plugin creates an 'archive' post type, but you can configure it to use any custom post type.

== Changelog ==
= 1.0.0 =

Initial release

== Upgrade Notice ==
= 1.0.0 =
Initial release