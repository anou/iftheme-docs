(function() {
    // Creates a new plugin class
    tinymce.create('tinymce.plugins.WYSIJA_subscribers', {
        init : function(ed, url) {
            // Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('wysijaSubscribers');
            ed.addCommand('wysijaSubscribers', function() {
                ed.windowManager.open({
                     file : ajaxurl+"?action=wysija_ajax&wysilog=1&controller=tmce&task=subscribersAdd",
                     width : 300,
                     height : 150 ,
                     inline : 1
                 }, {
                     plugin_url : url
                 });
            });

            // Register wysija_subscribers button
            ed.addButton('wysija_subscribers', {
                title : 'Add total of MailPoet subscribers',
                image : url+'/wysija_register.png',
                cmd : 'wysijaSubscribers'
            });

            // Add a node change handler, selects the button in the UI when a image is selected
            ed.onNodeChange.add(function(ed, cm, n) {
                cm.setActive('wysija_subscribers', n.nodeName == 'IMG');
            });
        }
    });

    // Register plugin
    tinymce.PluginManager.add('wysija_subscribers', tinymce.plugins.WYSIJA_subscribers);    
})();