jQuery(document).ready(function(){  
    if(jQuery('form input[name="action"]').attr('value')=='add-tag'){
        jQuery('.form-wrap p[class="submit"]').before(jQuery('#icl_tax_menu').html());    
    }else{
        jQuery('#edittag table[class="form-table"]:first').append(jQuery('#edittag table[class="form-table"] tr:last').clone());    
        jQuery('#edittag table[class="form-table"]:first tr:last th:first').html('&nbsp;');
        jQuery('#edittag table[class="form-table"]:first tr:last td:last').html(jQuery('#icl_tax_menu').html());  
    }    
    jQuery('#icl_tax_menu').remove();

    var addTagForm = jQuery('#addtag');

    addTagForm.off('submit', 'preventSubmit');

    var formBlocked = false;
    addTagForm.on('blur', function(){
        formBlocked = false;
    });

    jQuery(document).on('keydown', function(e){
        if(formBlocked){
            e.preventDefault();
        }
        if(e.keyCode == 13 && addTagForm.find('input:focus').length !== 0){
            formBlocked = true;
            addTagForm.ajaxComplete( function(){formBlocked = false;}  )
        }
    });

    jQuery('select[name="icl_tag_language"]').change(function(){
        var icl_subsubsub_save = jQuery('#icl_subsubsub').html();
        var lang = jQuery(this).val();
        var ajx = location.href.replace(/#(.*)$/,'');
        ajx = ajx.replace(/pagenum=([0-9]+)/,'');
        if(-1 == location.href.indexOf('?')){
            url_glue='?';
        }else{
            url_glue='&';
        }   

        if(icl_this_lang != lang){
            jQuery('#icl_translate_options').fadeOut();
        }else{
            jQuery('#icl_translate_options').fadeIn();
        }
        

        jQuery('#posts-filter').parent().load(ajx+url_glue+'lang='+lang + ' #posts-filter', {}, function(resp){
            strt = resp.indexOf('<span id="icl_subsubsub">');
            endd = resp.indexOf('</span>\'', strt);
            lsubsub = resp.substr(strt,endd-strt+7);
            jQuery('table.widefat').before(lsubsub);            
                                                                         
            tag_start = resp.indexOf('<div class="tagcloud">');
            tag_end  = resp.indexOf('</div>', tag_start);            
            tag_cloud = resp.substr(tag_start+22,tag_end-tag_start-22);
            jQuery('.tagcloud').html(tag_cloud);
        });
        
   });

  /* This section reads the hidden div containg the JSON encoded array of categories for which no checkbox is to be displayed.
   * This is done to ensure that they cannot be deleted
   */
  var defaultCategoryJSON, defaultCategoryJSONDiv, defaultCategoryIDs, key, id;

  defaultCategoryJSONDiv = jQuery('#icl-default-category-ids');

  if (defaultCategoryJSONDiv.length !== 0) {
    defaultCategoryJSON = defaultCategoryJSONDiv.html();
    defaultCategoryIDs = jQuery.parseJSON(defaultCategoryJSON);

    for (key in defaultCategoryIDs) {
      if (defaultCategoryIDs.hasOwnProperty(key)) {
        id = defaultCategoryIDs[key];
        removeDefaultCatCheckBox(id);
      }
    }
  }



});

/**
 * Removes the checkbox for a given category from the DOM.
 * @param catID
 */
function removeDefaultCatCheckBox(catID) {
  var defaultCatCheckBox;

  defaultCatCheckBox = jQuery('#cb-select-' + catID);

  if (defaultCatCheckBox.length !== 0) {
    defaultCatCheckBox.remove();
  }
}