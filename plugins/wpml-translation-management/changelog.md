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
