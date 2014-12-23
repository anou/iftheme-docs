**2.0.14**
* **Fix**
	* Fixed a menu synchronisation issue with custom links string
	
**2.0.13**
* **Fix**
	* Fixed an issue that prevented _n and _nx gettext tags from being properly parsed and imported.

**2.0.12**

* **Fix**
	* Fixed 'translate_string' filter which now takes arguments in the right order and returns the right value when WPML/ST are not active

**2.0.11**

* **Fix**
	* Removed PHP Warnings during image uploading

**2.0.10**

* **Improvements**
	* Speed improvements in functions responsible for downloading and scanning .mo files.
	* Added support for _n() strings
* **Fix**
	* Fixed fatal error when bulk updating plugins
	* Removed infinite loop in Appearance > Menu on secondary language when updating menus
	* Fixed: when user was editing translated post, admin language changed to this language when he saved. 

**2.0.9**

* **Fix**
	* the previously fixed dependency bug still didn't cover the case of String Translation being activate by users before WPML and was still causing an issue, making the plugin not visible. This should be now fixed.

**2.0.8**

* **Fix**
	* Fixed dependency bug: plugin should avoid any functionality when WPML is not active

**2.0.7**

* **Improvements**
	* New way to translate strings from plugins and themes: being on plugin/theme configuration screen, switch language using switcher in admin bar and provide translation.
* **Compatibility**
	* "woocommerce_email_from_name" and "woocommerce_email_from_address" are translatable now
* **Fix**
	* Removed PHP notices


**2.0.6**

* **Improvements**
	* New way to define plugin url is now tolerant for different server settings
* **Fix**
	* Minor syntax fixes
	* Fixed possible SQL injections
	* If string data was stored as serialized array, indexed by numbers, position 0 was not displayed on front-end. 
	* Fixed issues with caching values in icl_translate()
	* WordPress sometimes displayed wrong blog name when configured in Multisite mode. It is also fixed. 


**2.0.5**

* **Fix**
	* Fixed Slug translation issues leading to 404 in some circumstances
	* Support for gettext strings with ampersand in context name
	* Updated links to wpml.org
	* Updated issues about WPDB class, now we do not call mysql_* functions directly and we use WPDB::prepare() in correct way
	* Handled case where ICL_PLUGIN_PATH constant is not defined (i.e. when plugin is activated before WPML core)
	* Removed closing php tags + line breaks, causing PHP notices, in some cases and during plugin activation
	* Fixed typos when calling in some places _() instead of __()
	* Fixed Korean locale in .mo file name

**2.0.4**

* **Fix**
	* Fixed issue translating strings when default site language is not "English"
	* Fixed locale for Vietnamese (from "vn" to "vi")
	* Updated translations
	* Removed attempts to show warning when in the login page
	* Replace hardcoded references of 'wpml-string-translation' with WPML_ST_FOLDER

**2.0.3**

* **Fix**
	* Handled dependency from SitePress::get_setting()

**2.0.2**

* **Performances**
	* Reduced the number of calls to *$sitepress->get_current_language()*, *$this->get_active_languages()* and *$this->get_default_language()*, to avoid running the same queries more times than needed
	* No more queries when translating strings from default String Translation language, when calling l18n functions (e.g. __(), _x(), etc.)
* **Feature**
	* Added WPML capabilities (see online documentation)
* **Fix**
	* Fixed bug in slug translation when the slug is empty
	* Removed html escaping before sending strings to professional translation
