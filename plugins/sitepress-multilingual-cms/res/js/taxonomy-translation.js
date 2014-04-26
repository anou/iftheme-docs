var WPML_Translate_taxonomy = {
    
    init: function(){
        
        jQuery(document).delegate('#icl_tt_tax_switch', 'change', WPML_Translate_taxonomy.switch_taxonomy);
        
        
        jQuery(document).delegate('#wpml_tt_filters', 'submit', WPML_Translate_taxonomy.filter_taxonomies);
        /*jQuery(document).delegate('#wpml_tt_search', 'submit', WPML_Translate_taxonomy.search_taxonomies);*/
        jQuery(document).delegate('#wpml_tt_clear_search', 'click', WPML_Translate_taxonomy.clear_search_taxonomies);
        
        
        jQuery(document).delegate('.icl_tt_form', 'submit', WPML_Translate_taxonomy.save_term_translation);        
        
        jQuery(document).delegate('.icl_tt_form .cancel', 'click', WPML_Translate_taxonomy.hide_form);
        jQuery(document).delegate('.icl_tt_labels_form .cancel', 'click', WPML_Translate_taxonomy.hide_labels_form);
        
        jQuery(document).delegate('#wpml_tt_taxonomy_translation_wrap .tablenav-pages a.first-page', 'click', WPML_Translate_taxonomy.navigate_first);
        jQuery(document).delegate('#wpml_tt_taxonomy_translation_wrap .tablenav-pages a.prev-page', 'click', WPML_Translate_taxonomy.navigate_prev);
        jQuery(document).delegate('#wpml_tt_taxonomy_translation_wrap .tablenav-pages a.next-page', 'click', WPML_Translate_taxonomy.navigate_next);
        jQuery(document).delegate('#wpml_tt_taxonomy_translation_wrap .tablenav-pages a.last-page', 'click', WPML_Translate_taxonomy.navigate_last);
        jQuery(document).delegate('#wpml_tt_taxonomy_translation_wrap .tablenav-pages input.current-page', 'change', WPML_Translate_taxonomy.navigate_to);
        
        jQuery(document).delegate('.icl_tt_labels_form', 'submit', WPML_Translate_taxonomy.save_labels_translation);
        
        jQuery(document).delegate('#icl_tt_sync_assignment', 'click', WPML_Translate_taxonomy.sync_taxonomies_in_content);
        
        jQuery(document).delegate('form.icl_tt_do_sync a.submit', 'click', WPML_Translate_taxonomy.sync_taxonomies_do_sync);
        
        
    },
    
    switch_taxonomy: function(){

        jQuery('#wpml_tt_filters').find('input[name=taxonomy]').val(jQuery(this).val());
        
        WPML_Translate_taxonomy.filter_taxonomies();
        
    },
    
    show_terms: function(parameters){
        
        WPML_Translate_taxonomy.working_start();
        
        jQuery.ajax({
            type:       "POST", 
            url:        ajaxurl, 
            data:       'action=wpml_tt_show_terms&' + parameters,
            success:    
                function(ret){                
                    jQuery('#wpml_tt_taxonomy_translation_wrap').html(ret);
                }   
                
            });    
        
        return false;
        
        
    },
    
    filter_taxonomies: function(){
        
        var parameters = jQuery('#wpml_tt_filters').serialize();
        
        WPML_Translate_taxonomy.show_terms(parameters);
        
        return false;
        
    },
    
    /*
    search_taxonomies: function(){
        
        var parameters = jQuery('#wpml_tt_search').serialize();
        
        WPML_Translate_taxonomy.show_terms(parameters);

        return false;
        
    },
    */
    
    clear_search_taxonomies: function(){
        jQuery('#wpml_tt_filters').find("input[name=search]").val('');
        WPML_Translate_taxonomy.show_terms(jQuery('#wpml_tt_filters').serialize());
        return false;    
    },
    
    working_start: function(){
        jQuery('.icl_tt_tools .wpml_tt_spinner').fadeIn();
        jQuery('#wpml_tt_taxonomy_translation_wrap input, #wpml_tt_taxonomy_translation_wrap select, #wpml_tt_taxonomy_translation_wrap textarea').attr('disabled', 'disabled');
    },
    
    working_end: function(){
        jQuery('.icl_tt_tools .wpml_tt_spinner').fadeOut();
        jQuery('#wpml_tt_taxonomy_translation_wrap input, #wpml_tt_taxonomy_translation_wrap select, #wpml_tt_taxonomy_translation_wrap textarea').removeAttr('disabled');
    },
    
    show_form: function(tt_id, language){
        
        jQuery('.icl_tt_form').hide();
        jQuery('.icl_tt_form').prev().show();
        
        var form = jQuery('#icl_tt_form_' + tt_id+'_'+language);
        
        if(!form.is(':visible')){
            form.prev().hide();
            form.show();                
        }else{
            WPML_Translate_taxonomy.hide_form(form);    
        }
        
        return false;
        
    },
    
    hide_form: function(form){
        
        if(!form || form.type == 'click'){
            var form = jQuery(this).closest('.icl_tt_form');
        }
        
        form.hide(100, function(){
            form.find('.errors').html('');
        });
        form.prev().show();
        
    },
    
    show_labels_form: function(taxonomy, language){
        
        jQuery('.icl_tt_labels_form').hide();
        
        var form = jQuery('#icl_tt_labels_form_' + taxonomy+'_'+language);
        
        if(!form.is(':visible')){
            form.prev().hide();
            form.show();                
        }else{
            WPML_Translate_taxonomy.hide_labels_form(form);    
        }
        
        
        return false;
        
    },
    
    hide_labels_form: function(form){
        
        if(!form || form.type == 'click'){
            var form = jQuery(this).closest('.icl_tt_labels_form');
        }
                
        form.hide(100, function(){
            form.find('.errors').html('');
        });        
        form.prev().show();
        
    },
    
    callbacks: jQuery.Callbacks(),
    
    save_term_translation: function(){
    
        this_form = jQuery(this);
        var parameters = jQuery(this).serialize();
        
        this_form.find('.errors').html('');        
        this_form.find('.wpml_tt_spinner').fadeIn();
        this_form.find('textarea,input').attr('disabled', 'disabled');
        
        jQuery.ajax({
            type:       "POST", 
            dataType:   'json',
            url:        ajaxurl, 
            data:       'action=wpml_tt_save_term_translation&' + parameters,
            success:    
                function(ret){                
                    this_form.find('.wpml_tt_spinner').fadeOut();
                    this_form.find('textarea,input').removeAttr('disabled');                                            
                    if(ret.errors){
                        
                        this_form.find('.errors').html(ret.errors);    
                        
                    }else{                        
                        this_form.find('input[name=slug]').val(ret.slug);                        
                        WPML_Translate_taxonomy.hide_form(this_form);
//                        this_form.prev().html(this_form.find('input[name=name]').val()).removeClass('lowlight');
						this_form.prev().html(this_form.find('input[name=term_leveled]').val() + this_form.find('input[name=name]').val()).removeClass('lowlight');

                        WPML_Translate_taxonomy.callbacks.fire('wpml_tt_save_term_translation', this_form.find('input[name=taxonomy]').val());
                        
                    }
                }   
                
            });    
        
        return false;
        
    },
    
    navigate: function(page){

        var parameters = jQuery('#wpml_tt_filters').serialize() + '&' + jQuery('#wpml_tt_search').serialize() + '&page=' + page;
        
        WPML_Translate_taxonomy.show_terms(parameters);

        return false;
        
        
    },
    
    navigate_first: function(){
        if(!jQuery(this).hasClass('disabled')){
            WPML_Translate_taxonomy.navigate(1);    
        }        
        return false;
    },
    
    navigate_prev: function(){
        if(!jQuery(this).hasClass('disabled')){
            var current_page = jQuery('#wpml_tt_taxonomy_translation_wrap .tablenav-pages input.current-page').val();
            WPML_Translate_taxonomy.navigate(current_page - 1);    
        }        
        return false;
    },
    
    navigate_next: function(){        
        if(!jQuery(this).hasClass('disabled')){
            var current_page = jQuery('#wpml_tt_taxonomy_translation_wrap .tablenav-pages input.current-page').val();
            WPML_Translate_taxonomy.navigate(parseInt(current_page) + 1);    
        }        
        return false;
    },
    
    navigate_last: function(){
        if(!jQuery(this).hasClass('disabled')){
            var total_pages = jQuery('#wpml_tt_taxonomy_translation_wrap .tablenav-pages .total-pages').html();
            WPML_Translate_taxonomy.navigate(total_pages);    
        }       
        return false; 
    },
    
    navigate_to: function(){        
        var total_pages = jQuery('#wpml_tt_taxonomy_translation_wrap .tablenav-pages .total-pages').html();
        
        if(jQuery(this).val() > total_pages){
            jQuery(this).val(total_pages);
        }
        
        WPML_Translate_taxonomy.navigate(jQuery(this).val());                   
        return false; 
    },
    
    save_labels_translation: function(){
    
        var this_form = jQuery(this);
        var parameters = jQuery(this).serialize();
        
        this_form.find('.errors').html('');
        
        this_form.find('.wpml_tt_spinner').fadeIn();
        this_form.find('textarea,input').attr('disabled', 'disabled');
        
        jQuery.ajax({
            type:       "POST", 
            dataType:   'json',
            url:        ajaxurl, 
            data:       'action=wpml_tt_save_labels_translation&' + parameters,
            success:    
                function(ret){                
                    
                    this_form.find('.wpml_tt_spinner').fadeOut();
                    this_form.find('textarea,input').removeAttr('disabled');                                            
                    
                    if(ret.errors){
                        this_form.find('.errors').html(ret.errors);    
                    }else{
                        WPML_Translate_taxonomy.hide_form(this_form);
                        jQuery('.icl_tt_labels_' + this_form.find('input[name=taxonomy]').val() + '_' + 
                            this_form.find('input[name=language]').val()).html(this_form.find('input[name=singular]').val() + 
                            ' / ' + this_form.find('input[name=general]').val()).removeClass('lowlight');
                    }
                    
                }   
                
            });    
        
        return false;
        
    },
        
    sync_taxonomies_in_content: function(){
        
        var this_form = jQuery(this);
        var parameters = jQuery(this).serialize();
        
        this_form.find('.wpml_tt_spinner').fadeIn();
        this_form.find('input').attr('disabled', 'disabled');
        
        jQuery('.icl_tt_sync_row').remove();
        
        jQuery.ajax({
            type:       "POST", 
            dataType:   'json',
            url:        ajaxurl, 
            data:       'action=wpml_tt_sync_taxonomies_in_content_preview&' + parameters,
            success:    
                function(ret){                
                    
                    this_form.find('.wpml_tt_spinner').fadeOut();
                    this_form.find('input').removeAttr('disabled');                                            
                    
                    if(ret.errors){
                        this_form.find('.errors').html(ret.errors);    
                    }else{
                        jQuery('#icl_tt_sync_preview').html(ret.html);    
                    }
                    
                }   
                
            });         
                
        return false;
        
    },
    
    sync_taxonomies_do_sync: function (){
        var this_form = jQuery(this).closest('form');
        
        var parameters = this_form.serialize();
        
        this_form.find('.wpml_tt_spinner').fadeIn();
        this_form.find('input').attr('disabled', 'disabled');
        
        jQuery.ajax({
            type:       "POST", 
            dataType:   'json',
            url:        ajaxurl, 
            data:       'action=wpml_tt_sync_taxonomies_in_content&' + parameters,
            success:    
                function(ret){                
                    
                    this_form.find('.wpml_tt_spinner').fadeOut();
                    this_form.find('input').removeAttr('disabled');                                            
                    
                    if(ret.errors){
                        this_form.find('.errors').html(ret.errors);    
                    }else{                        
                        this_form.closest('.icl_tt_sync_row').html(ret.html);    
                    }
                    
                }   
                
            });         
                
        return false;
        
        
        
    }
    
    
    
    
    
    
}

jQuery(document).ready(WPML_Translate_taxonomy.init);