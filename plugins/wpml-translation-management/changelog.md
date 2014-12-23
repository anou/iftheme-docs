**1.9.8**

* **Fix**
	* Fixed a style issue with the "View Original" link of Translation Jobs table
	
**1.9.7**

* **Improvements**
	* Support for string translation packages 
	* Removed PHP warning when in Translation Dashboard and only one language is defined. Replaced with an admin notice.
* **Fix**
	* Fixed issue with in proper notices in Translation Editor when user tries to translate document which was assigned to another user before
	* Fixed issue with "Copy from" in Translation Editor 
	* Fixed multiple issues with translation of hierarchical taxonomies

**1.9.6**

* **Improvements**
	* Compatibilty with WPML Core

**1.9.5**

* **Improvements**
    * New way to define plugin url is now tolerant for different server settings
	* Support for different formats of new lines in XLIFF files
* **Fix**
    * Fixed possible SQL injections
    * When you preselect posts with status "Translation Complete" on WPML > Translation Management dashboard, it show wrong results. This is fixed now. 

**1.9.4**

* **Improvements**
	* Defining global variables to improve code inspection
* **Fixes**
	* Removed notice after "abort translation"
	* Updated links to wpml.org
	* Fixed Translation Editor notices in wp_editor()
	* Handled case where ICL_PLUGIN_PATH constant is not defined (i.e. when plugin is activated before WPML core)
	* Fixed Translation Editor - Notice: wp_editor() and not working editors in WP3.9 (changes for additional fields)
	* Fixed not working "Copy from..." links for Gravity forms fields.
	* Fixed Korean locale in .mo file name

**1.9.3**

* **Fixes**
	* Handled dependency from SitePress::get_setting()
	* Changed vn to vi in locale files
	* Updated translations
	* Replace hardcoded references of 'wpml-translation-management' with WPML_TM_FOLDER

**1.9.2**

* **Performances**
	* Reduced the number of calls to *$sitepress->get_current_language()*, *$this->get_active_languages()* and *$this->get_default_language()*, to avoid running the same queries more times than needed
* **Features**
	* Added WPML capabilities (see online documentation)
* **Fixes**
	* Improved SSL support for CSS and JavaScript files
