**3.1.5**

* **Improvements**
	* check_settings_integrity() won't run SQL queries on front-end and in the back-end it will run only once and only in specific circumstances
	* We added ability to add language information to duplicated content, when WPML_COMPATIBILITY_TEST_MODE is defined
	* Option to create database dump was removed as it was not working correctly. Please use additional plugins to do this (eg https://wordpress.org/plugins/adminer/ )
* **Usability**
	* We added links to String Translation if there are labels or urls that needs to be translated, when running menu synchronization
* **Compatibility** 
	* is_ajax() function is now deprecated and replaced by wpml_is_ajax() - **plugins and themes developers**: make sure you're updating your code!
	* Compatibility with WordPress 3.9 - WPML plugins were adjusted to use WPDB class in correct way, no direct usages of mysql_* functions
* **Fixes** 
	* Parent pages can be now changed or removed
	* Fixed issue when a showing paginated subqueries in home page (in non default language)
	* In some circumstance translated posts statuses doesn't get synchronized after publishing a post: fixed now
	* The "Connect translation" feature now works also when the WYSIWYG is not shown
	* We make use of debug_backtrace in some places: wherever it is possible (and depending on used PHP version), we've reduced the impact of this call by limiting the number of retrieved frames, or the complexity of the retrieved data
	* In some server configuration we were getting either a 404 error or a redirect loop
	* Static front page is not loosing now custom page template on translations when paged
	* Corrupted settings when no language in array - fixing this now doesn't end with pages missing
	* Custom Post Types when was set not to be translated was also not displayed, now it's fixed
	* Root page can work now with parameters
	* Fixed compatibility with CRED/Views (404, rewrite_rules_filter)
	* Fixed potential bug caused by redeclaration of icl_js_escape() function
	* Gallery was not displayed on root home page, this is fixed now
	* Filtered language doesn't remain as 'current value' on the wpml taxonomy page - this is also fixed
	* Information about hidden languages was displayed duplicated and doubled after every page refresh
	* Fixed filtering wp_query with tax_query element 
	* Ajax now "knows" language of page which made a ajax call
	* Removed PHP Notice on secondary language front page
	* Updated links to wpml.org
	* Many fixes in caching data what results in better site performance
	* Taxonomy @lang suffixes wasn't hidden always when it was necessary, now this is also fixed
	* Removed conflict between front page which ID was same as ID of actually displayed taxonomy archive page. 
	* Fixed saving setting for custom fields translation
	* Removed warning in inc/absolute-links/absolute-links.php
	* Removed duplicated entries in hidden languages list
	* Fixed Notice message when duplicating posts using Translation Management
	* Update posts using element_id instead of translation_id
	* Fixed PHP Fatal error: Cannot use object of type WP_Error as array
	* Option to translate custom posts slugs is now hidden when it is no set to translate them
	* Fixed monthly archive page, now it shows language switcher with correct urls
	* You can restore trashed translation when you tried to edit this
	* When you try to delete an item from untranslated menu, you saw PHP errors, now this is also fixed
	* Fixed compatibility issue with PHP versions < 5.3.6: constants DEBUG_BACKTRACE_PROVIDE_OBJECT and DEBUG_BACKTRACE_IGNORE_ARGS does not exist before this version, causing a PHP notice message.
	* Fixed wrong links to attachments in image galleries
	* Fixed not hidden spinner after re-install languages
	* Handled timeout error message when fixing languages table.
	* Made SitePress::slug_template only work for taxonomies
	* Fixed problems with missing taxonomies configuration from wpml-config.xml file
	* MENU (Automatically add new top-level pages to this menu) option was not synchronised
	* WP 3.9 compatibility issue: new version of WordPress doesn't automatically load dialog JS
	* Fixed WP 3.9 compatibility issues related to language switcher widget
	* Category hierarchy and pages hierarchy are now synchronised during translation
	* Fixed problem with redirecting to wrong page with the same slug in different language after upgrade to WP3.9
	* Fixed problem with Sticky Links and Custom Taxonomies
	* Home url not converted in current language when using different domains per language and WP in a folder
	* Fixed typos when calling in some places _() instead of __()
	* Fixed Korean locale in .mo file name

**3.1.4**

* **Fixes** 
	* The default menu in other language has gone
	* Menu stuck on default language
	* Infinite loop in auto-adjust-ids
	* Translations lose association
	* The "This is a translation of" drop down wasn't filled for non-original content
	* Removed language breakdown for Spam comments
	* Pages with custom queries won't show 404 errors
	* Languages in directories produces 404 for all pages when HTTP is using non standard 80 port
	* Fixed icl_migrate_2_0_0() logic
	* When a database upgrade is required, it won't fail with invalid nonce
	* Error in all posts page, where there are no posts
	* php notice when adding a custom taxonomy to a custom post
	* The default uncategorized category doesn't appear in the default language
	* Fixed locale for Vietnamese (from "vn" to "vi")
	* Fixed languages.csv file for Portuguese (Portugal) and Portuguese (Brazilian) mixed up --this requires a manual fix in languages editor for existing sites using one or both languages--
	* Pre-existing untranslatable custom post types disappears once set as translatable
	* Languages settings -> Language per domain: once selected and page is reloaded, is the option is not properly rendered
	* Languages settings -> Language per domain: custom languages generate a notice
	* Updated translations
	* Custom Fields set to be copied won't get lost anymore
	* Scheduled posts won't lose their translation relationship
	* Excluded/included posts in paginated custom queries won't cause any notice message
	* Replace hardcoded references of 'sitepress-multilingual-cms' with ICL_PLUGIN_FOLDER
	* Replace hardcoded references of 'wpml-string-translation' with WPML_ST_FOLDER
	* Replace hardcoded references of 'wpml-translation-management' with WPML_TM_FOLDER
* **Improvements** 
	* Generated keys of cached data should use the smallest possible amount of memory
	* The feature that allows to set orphan posts as source of other posts has been improved in order to also allow to set the orphan post as translation of an existing one
	* Added support to users with corrupted settings
	* Improved language detection from urls when using different domains
	* Added admin notices for custom post types set as translatable and with translatable slugs when translated slugs are missing

**3.1.3**

* **Fixes** 
	* In SitePress_Setup::languages_table_is_complete -> comparison between number of existing languages and number of built in languages changed from != to <
	* In SitePress_Setup::fill_languages -> added "$lang_locales = icl_get_languages_locales();" needed for repopulating language tables
	* Added cache clearing to icl_fix_languages logic on the troubleshooting page
	* Wording changes for the fix languages section on the troubleshooting page
	* Logic changes for the fix languages section on the troubleshooting page -> added checkbox and the button is enabled only when the checkbox is on
	* Added WPML capabilities to all roles with cap 'manage_options' when activate
	* Not remove WPML caps from super admin when deactivate

**3.1.2**

* **Fixes** 
	* Fixed a potential issue when element source language is set to an empty string rather than null: when reading element translations, either NULL or '' will be handled as NULL.

**3.1.1**
* **Fixes** 
	* Fixed an issue that occurs with some configurations, when reading WPML settings

**3.1**

* **Performances** 
	* Reduced number of queries to one per request when retrieving Admin language
	* Reduced the number of calls to *$sitepress->get_current_language()*, *$this->get_active_languages()* and *$this->get_default_language()*, to avoid running the same queries more times than needed
	* Dramatically reduced the amount of queries ran when checking if content is properly translated in several back-end pages
	* A lot of data is now cached, further reducing queries
* **Improvements** 
	* Improved javascript code style
	* Orphan content is now checked when (re)activating the plugin, rather than in each request on back-end side
	* If languages tables are incomplete, it will be possible to restore them
* **Feature** 
	* When setting a value for "This is a translation of", and the current content already has translations in other languages, each translation gets properly synchronized, as long as there are no conflicts. In case of conflicts, translation won't be synchronized, while the current content will be considered as not linked to an original (in line with the old behavior)
	* Categories, tags and taxonomies templates files don't need to be translated anymore (though you can still create a translated file). Taxonomy templates will follow this hierarchy: '{taxonomy}-{lang}-{term_slug}-{lang}.php', '{taxonomy}-{term_slug}-{lang}.php', '{taxonomy}-{lang}-{term_slug}-2.php', '{taxonomy}-{term_slug}-2.php', '{taxonomy}-{lang}.php', '{taxonomy}.php'
	* Administrators can now edit content that have been already sent to translators
	* Ability to set, in the post edit page, an orphan post as source of translated post
	* Added WPML capabilities (see online documentation)
	* Add support to users with corrupted settings
* **Security** 
	* Improved security by using *$wpdb->prepare()* wherever is possible
	* Database dump in troubleshooting page is now available to *admin* and *super admin* users only
* **Fixes** 
	* Admin Strings configured with wpml-config.xml files are properly shown and registered in String Translation
	* Removed max length issue in translation editor: is now possible to send content of any length
	* Taxonomy Translation doesn't hang anymore on custom hierarchical taxonomies
	* Is now possible to translate content when displaying "All languages", without facing PHP errors
	* Fixed issues on moderated and spam comments that exceed 999 items
	* Changed "Parsi" to "Farsi" (as it's more commonly used) and fixed some language translations in Portuguese
	* Deleting attachment from post that are duplicated now deleted the duplicated image as well (if "When deleting a post, delete translations as well" is flagged)
	* Translated static front-page with pagination won't loose the template anymore when clicking on pages
	* Reactivating WPML after having added content, will properly set the default language to the orphan content
	* SSL support is now properly handled in WPML->Languages and when setting a domain per language
	* Empty categories archives does not redirect to the home page anymore
	* Menu and Footer language switcher now follow all settings in WPML->Languages
	* Post metas are now properly synchronized among duplicated content
	* Fixed a compatibility issue with SlideDeck2 that wasn't retrieving images
	* Compatibility with WP-Types repeated fields not being properly copied among translations
	* Compatibility issue with bbPress
	* Removed warnings and unneeded HTML elements when String Translation is not installed/active
	* Duplicated content retains the proper status
	* Browser redirect for 2 letters language codes now works as expected
	* Menu synchronization now properly fetches translated items
	* Menu synchronization copy custom items if String Translation is not active, or WPML default languages is different than String Translation language
	* When deleting the original post, the source language of translated content is set to null or to the first available language
	* Updated localized strings
	* Posts losing they relationship with their translations
	* Checks if string is already registered before register string for translation. Fixed because it wasn't possible to translate plural and singular taxonomy names in Woocommerce Multilingual
	* Fixed error when with hierarchical taxonomies and taxonomies with same names of terms.
