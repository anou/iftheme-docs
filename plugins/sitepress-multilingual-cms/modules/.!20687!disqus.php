<?php


    
class WPML_Disqus_Integration{
    
    function __construct(){
        add_action('init', array($this, 'init'));
    }
    
    function init(){
        add_action('disqus_language_filter', array($this, 'language'));
    }
    
    function language(){
        global $sitepress;
        
        /*
        LANGUAGES = [
            ('English', 'en'),
            ('Arabic', 'ar'),
            ('Afrikaans', 'af'),
            ('Albanian', 'sq'),
            ('Azerbaijani', 'az'),
            ('Basque', 'eu'),
            ('Bulgarian', 'bg'),
            ('Burmese', 'my'),
            ('Chinese (Simplified)', 'zh'),
            ('Chinese (Traditional)', 'zh_HANT'),
            ('Croatian', 'hr'),
            ('Czech', 'cs'),
            ('Danish', 'da'),
            ('Dutch', 'nl'),
            ('Esperanto', 'eo'),
            ('Estonian', 'et'),
            ('Finnish', 'fi'),
            ('French', 'fr'),
            ('Galician', 'gl'),
            ('German (formal)', 'de_formal'),
            ('German (informal)', 'de_inf'),
            ('Greek', 'el'),
            ('Greenlandic', 'kl'),
            ('Hebrew', 'he'),
            ('Hungarian', 'hu'),
            ('Italian', 'it'),
            ('Icelandic', 'is'),
            ('Indonesian', 'id'),
            ('Japanese', 'ja'),
            ('Khmer', 'km'),
            ('Korean', 'ko'),
            ('Laotian', 'lo'),
            ('Latin', 'la'),
            ('Latvian', 'lv'),
            ('Letzeburgesch', 'lb'),
            ('Lithuanian', 'lt'),
            ('Macedonian', 'mk'),
            ('Malay (Bahasa Melayu)', 'ms'),
