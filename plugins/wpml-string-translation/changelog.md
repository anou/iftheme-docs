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
