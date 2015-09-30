#2.2.6

##Fixes
* [wpmlst-469] Solved `Warning: in_array() expects parameter 2 to be array, null given`
* [wpmlst-432] Clear the current string language cache when switching languages via 'wpml_switch_language' action

##Performances
* [wpmlst-462] Fixed too many SQL queries when the user's administrator language is not one of the active languages
* [wpmlst-460] Fixed `icl_register_string` to reduce the number of SQL queries
* [wpmlst-467] Improve performance of string translation
* [wpmlst-461] Improved performance with slug translation

#2.2.5
* Fixed performance issue with string translation when looking up translations by name

#2.2.4

##Fixes
* Solved the "Invalid argument supplied for foreach()" PHP warning
* Fixed a typo in a gettext string

#2.2.3

##Fixes
* Fixed issues translating widget strings
* Fixed a problem with slug translation showing translated slug for English on Multilingual Content Setup when Admin language is other than English
* Fixed slug translation so it works with the default permalink structure
* Fixed caching problem with admin texts which caused some admin texts to not update correctly
* Removed `PHP Fatal error: Specified key was too long; max key length is 1000 bytes` caused by `gettext_context_md5`
* Fixed string scanning issues
* Fixed slug translations so that they are not used when they are disabled
* Fixed Auto register strings for translation
* Fixed admin texts so the settings are loaded from the default language and not the administrator's language
* Fixed fatal error when an old version of WPML is active
* Fixed an issue where wrong translations were displayed for strings registered by version 2.2 and older if the database contained the same string value for different string names
* Replaced deprecated constructor of Multilingual Widget for compatibility with WP 4.3

##New
* Support multi-line strings when importing and exporting po files
* Support gettext contexts in string translation
* Updated dependency check module

##API
* New hooks added (see https://wpml.org/documentation/support/wpml-coding-api/wpml-hooks-reference/)
	* Filters
		* `wpml_get_translated_slug` to get the translated slug for a custom post type

#2.2.2

##Fixes
* Resolved problem with "removed" strings

##New
* Updated dependency check module

#2.2.1

##New
* Updated dependency check module

#2.2

##Fixes
* Improved handling of strings with names or contexts longer than the DB field lengths
* Fixed PHP errors and notices
* Fixed custom menu item translation problems
* Fixed custom post type slug translation issues when cpt key is different to slug and when has_archive is set to a string
* Fixed admin text string registration from admin and wpml-config.xml

##Improvements
* Fixed plugin dependency to the core
* Performance improvements

##API
* Improved API and created documentation for it in wpml.org

#2.1.3

##Fixes
* Fixed issues with broken URL rewrite and translatable custom post types

#2.1.2

* Works with WPML 3.1.9.4 on WordPress 4

#2.1.1

* Additional fixes to URLs with non-English characters

#2.1

* Security update

#2.0.14

##Fixes
* Fixed a menu synchronisation issue with custom links string

#2.0.13

##Fixes
* Fixed an issue that prevented _n and _nx gettext tags from being properly parsed and imported.

#2.0.12

##Fixes
* Fixed 'translate_string' filter which now takes arguments in the right order and returns the right value when WPML/ST are not active

#2.0.11

##Fixes
* Removed PHP Warnings during image uploading

#2.0.10

##Improvements
* Speed improvements in functions responsible for downloading and scanning .mo files.
* Added support for _n() strings

##Fixes
* Fixed fatal error when bulk updating plugins
* Removed infinite loop in Appearance > Menu on secondary language when updating menus
* Fixed: when user was editing translated post, admin language changed to this language when he saved. 

#2.0.9

##Fixes
* The previously fixed dependency bug still didn't cover the case of String Translation being activate by users before WPML and was still causing an issue, making the plugin not visible. This should be now fixed.

#2.0.8

##Fixes
* Fixed dependency bug: plugin should avoid any functionality when WPML is not active

#2.0.7

##Improvements
* New way to translate strings from plugins and themes: being on plugin/theme configuration screen, switch language using switcher in admin bar and provide translation.

##Compatibility
* "woocommerce_email_from_name" and "woocommerce_email_from_address" are translatable now

##Fixes
* Removed PHP notices


#2.0.6

##Improvements
* New way to define plugin url is now tolerant for different server settings

##Fixes
* Minor syntax fixes
* Fixed possible SQL injections
* If string data was stored as serialized array, indexed by numbers, position 0 was not displayed on front-end. 
* Fixed issues with caching values in icl_translate()
* WordPress sometimes displayed wrong blog name when configured in multi site mode. It is also fixed. 


#2.0.5

##Fixes
* Fixed Slug translation issues leading to 404 in some circumstances
* Support for gettext strings with ampersand in context name
* Updated links to wpml.org
* Updated issues about WPDB class, now we do not call mysql_* functions directly and we use WPDB::prepare() in correct way
* Handled case where ICL_PLUGIN_PATH constant is not defined (i.e. when plugin is activated before WPML core)
* Removed closing php tags + line breaks, causing PHP notices, in some cases and during plugin activation
* Fixed typos when calling in some places _() instead of __()
* Fixed Korean locale in .mo file name

#2.0.4

##Fixes
* Fixed issue translating strings when default site language is not "English"
* Fixed locale for Vietnamese (from "vn" to "vi")
* Updated translations
* Removed attempts to show warning when in the login page
* Replace hardcoded references of 'wpml-string-translation' with WPML_ST_FOLDER

#2.0.3

##Fixes
* Handled dependency from SitePress::get_setting()

#2.0.2

##Performances
* Reduced the number of calls to *$sitepress->get_current_language()*, *$this->get_active_languages()* and *$this->get_default_language()*, to avoid running the same queries more times than needed
* No more queries when translating strings from default String Translation language, when calling l18n functions (e.g. __(), _x(), etc.)

##Feature
* Added WPML capabilities (see online documentation)

##Fixes
* Fixed bug in slug translation when the slug is empty
* Removed html escaping before sending strings to professional translation
