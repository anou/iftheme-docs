(function($){
  $.fn.validationEngineLanguage = function(){
  };  
  $.validationEngineLanguage = {
    newLang: function(){
      $.validationEngineLanguage.allRules = {
        "required": { // Add your regex rules here, you can take telephone as an example
          "regex": "none",
          "alertText": "* חובה למלא שדה זה",
          "alertTextCheckboxMultiple": "* אנא בחר אחד מהאפשרויות",
          "alertTextCheckboxe": "* חובה לסמן תיבה זו",
          "alertTextDateRange": "* חובה לבחור את שתי טווחי התאריכים"
        },
        "regex": {
          "func": function(field){
            var pattern = new RegExp(field.attr("pattern"));            
            return (pattern.test(field.val()));
          },
          "alertText": "* "     
        },
        "dateRange": {
          "regex": "none",
          "alertText": "* טווח תאריך",
          "alertText2": " לא תקין "
        },
        "dateTimeRange": {
          "regex": "none",
          "alertText": "* טווח תאריך וזמן",
          "alertText2": " לא תקינים ",                    
        },
        "minSize": {
          "regex": "none",
          "alertText": "* מלא לפחות ",
          "alertText2": " תווים"
        },
        "maxSize": {
          "regex": "none",
          "alertText": "* מקסימום ",
          "alertText2": " תווים מותרים"
        },
        "groupRequired": {
          "regex": "none",
          "alertText": "* אתה חייב למלא שדה אחד מתוך השדות הבאים"
        },
        "min": {
          "regex": "none",
          "alertText": "* ערך מינימלי הוא "
        },
        "max": {
          "regex": "none",
          "alertText": "* ערך מקסימלי הוא "
        },
        "past": {
          "regex": "none",
          "alertText": "* התאריך חייב להיות לפני "
        },
        "future": {
          "regex": "none",
          "alertText": "* התאריך חייב להיות אחרי "
        },  
        "maxCheckbox": {
          "regex": "none",
          "alertText": "* מקסימום ",
          "alertText2": " אפשרויות שניתן לבחור"
        },
        "minCheckbox": {
          "regex": "none",
          "alertText": "* אנא בחר ",
          "alertText2": " אפשרויות"
        },
        "equals": {
          "regex": "none",
          "alertText": "* השדות אינם תואמים"
        },
        "creditCard": {
          "regex": "none",
          "alertText": "* מספר כרטיס אשראי איננו תקין"
        },
        "phone": {
          // credit: jquery.h5validate.js / orefalo
          "regex": /^([\+][0-9]{1,3}[\ \.\-])?([\(]{1}[0-9]{2,6}[\)])?([0-9\ \.\-\/]{3,20})((x|ext|extension)[\ ]?[0-9]{1,4})?$/,
          "alertText": "* מספר טלפון לא תקין"
        },
        "email": {
          // HTML5 compatible email regex ( http://www.whatwg.org/specs/web-apps/current-work/multipage/states-of-the-type-attribute.html#    e-mail-state-%28type=email%29 )
          // allows or empty string or email valid.
          "regex": /^$|(([\w-]+\.)+[\w-]+|([a-zA-Z]{1}|[\w-]{2,}))@([0-9a-zA-Z]+[\w-]+\.)+[a-zA-Z]{2,4}$/,
          "alertText": "* כתובת דואר אלקטרוני לא תקינה"
        },
        "integer": {
          "regex": /^[\-\+]?\d+$/,
          "alertText": "* מספר שלם לא תקין"
        },
        "number": {
          // Number, including positive, negative, and floating decimal. credit: orefalo
          "regex": /^[\-\+]?(([0-9]{1,3})([\.]([0-9]{3}))*([,]([0-9]+))?|([0-9]+)?([,]([0-9]+))?)$/,
          "alertText": "* מספר לא תקין"
        },
        "date": {
           // Check if date is valid by leap year. made by maxim.
          "func": function (field) {
            var pattern = new RegExp(/^(0?[1-9]|[12][0-9]|3[01])[\/\-\.](0?[1-9]|1[012])[\/\-\.](\d{4})$/);
            var match = pattern.exec(field.val());
            if (match == null)
              return false;

            var day   = match[1]*1;
            var month = match[2]*1;
            var year  = match[3];
            var date = new Date(year, month - 1, day); // because months starts from 0.

            return (date.getFullYear() == year && date.getMonth() == (month - 1) && date.getDate() == day);
          },
          "alertText": "* תאריך לא תקין, חייב להיות בפורמט DD-MM-YYYY"
        },
        "ipv4": {
          "regex": /^((([01]?[0-9]{1,2})|(2[0-4][0-9])|(25[0-5]))[.]){3}(([0-1]?[0-9]{1,2})|(2[0-4][0-9])|(25[0-5]))$/,
          "alertText": "* כתובת IP לא תקינה"
        },
        "url": {
          "regex": /^(https?|ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(\#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i,
          "alertText": "* כתובת אתר לא תקינה"
        },
        "onlyNumberSp": {
          "regex": /^[0-9\ ]+$/,
          "alertText": "* מספרים בלבד"
        },
        "onlyLetterSp": {
          "regex": /^[a-zA-Z\ \']+$/,
          "alertText": "* אותיות בלבד"
        },
        "onlyLetterNumber": {
          "regex": /^[0-9a-zA-Z]+$/,
          "alertText": "* לא ניתן להזין תווים מיוחדים"
        },
        // --- CUSTOM RULES -- Those are specific to the demos, they can be removed or changed to your likings
        "ajaxUserCall": {
          "url": "ajaxValidateFieldUser",
          // you may want to pass extra data on the ajax call
          "extraData": "name=eric",
          "alertText": "* שם המשתמש כבר תפוס",
          "alertTextLoad": "* מאמת, אנא המתן"
        },
        "ajaxUserCallPhp": {
          "url": "phpajax/ajaxValidateFieldUser.php",
          // you may want to pass extra data on the ajax call
          "extraData": "name=eric",
          // if you provide an "alertTextOk", it will show as a green prompt when the field validates
          "alertTextOk": "* שם המשתמש פנוי",
          "alertText": "* שם המשתמש כבר תפוס",
          "alertTextLoad": "* מאמת, אנא המתן"
        },
        "ajaxNameCall": {
          // remote json service location
          "url": "ajaxValidateFieldName",
          // error
          "alertText": "* שם המשתמש כבר תפוס",
          // if you provide an "alertTextOk", it will show as a green prompt when the field validates
          "alertTextOk": "* שם המשתמש פנוי",
          // speaks by itself
          "alertTextLoad": "* מאמת, אנא המתן"
        },
         "ajaxNameCallPhp": {
            // remote json service location
            "url": "phpajax/ajaxValidateFieldName.php",
            // error
            "alertText": "* שם המשתמש כבר תפוס",
            // speaks by itself
            "alertTextLoad": "* מאמת, אנא המתן"
          },
        "validate2fields": {
          "alertText": "* Please input HELLO"
        },
        //tls warning:homegrown not fielded 
        "dateFormat":{
          "regex": /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])$|^(?:(?:(?:0?[13578]|1[02])(\/|-)31)|(?:(?:0?[1,3-9]|1[0-2])(\/|-)(?:29|30)))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^(?:(?:0?[1-9]|1[0-2])(\/|-)(?:0?[1-9]|1\d|2[0-8]))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^(0?2(\/|-)29)(\/|-)(?:(?:0[48]00|[13579][26]00|[2468][048]00)|(?:\d\d)?(?:0[48]|[2468][048]|[13579][26]))$/,
          "alertText": "* תאריך לא תקין"
        },
        //tls warning:homegrown not fielded 
        "dateTimeFormat": {
          "regex": /^\d{4}[\/\-](0?[1-9]|1[012])[\/\-](0?[1-9]|[12][0-9]|3[01])\s+(1[012]|0?[1-9]){1}:(0?[1-5]|[0-6][0-9]){1}:(0?[0-6]|[0-6][0-9]){1}\s+(am|pm|AM|PM){1}$|^(?:(?:(?:0?[13578]|1[02])(\/|-)31)|(?:(?:0?[1,3-9]|1[0-2])(\/|-)(?:29|30)))(\/|-)(?:[1-9]\d\d\d|\d[1-9]\d\d|\d\d[1-9]\d|\d\d\d[1-9])$|^((1[012]|0?[1-9]){1}\/(0?[1-9]|[12][0-9]|3[01]){1}\/\d{2,4}\s+(1[012]|0?[1-9]){1}:(0?[1-5]|[0-6][0-9]){1}:(0?[0-6]|[0-6][0-9]){1}\s+(am|pm|AM|PM){1})$/,
          "alertText": "* תאריך או תבנית תאריך לא תקינים",
          "alertText2": "תבנית תאריך תקינה: ",
          "alertText3": "mm/dd/yyyy hh:mm:ss AM|PM or ", 
          "alertText4": "yyyy-mm-dd hh:mm:ss AM|PM"
        }
      };
      
    }
  };

  $.validationEngineLanguage.newLang();
  
})(jQuery);
