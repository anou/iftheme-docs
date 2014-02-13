**1.9.2**

* **Performances**: Reduced the number of calls to *$sitepress->get_current_language()*, *$this->get_active_languages()* and *$this->get_default_language()*, to avoid running the same queries more times than needed
* **Feature** Added WPML capabilities (see online documentation)
* **Fix** Improved SSL support for CSS and JavaScript files