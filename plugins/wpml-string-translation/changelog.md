**2.0.2**

* **Performances**: Reduced the number of calls to *$sitepress->get_current_language()*, *$this->get_active_languages()* and *$this->get_default_language()*, to avoid running the same queries more times than needed
* **Performances**: No more queries when translating strings from default String Translation language, when calling l18n functions (e.g. __(), _x(), etc.)
* **Feature** Added WPML capabilities (see online documentation)
* **Fix**: Fixed bug in slug translation when the slug is empty
* **Fix** Removed html escaping before sending strings to professional translation
