require([
    'jquery',
    'prototype',
], function(jQuery){
    jQuery('#imgsync-get-import-list').click(function (e) {
        jQuery('body').loader('show');
        setTimeout(function(){
            window.location.reload(1);
        }, 5000);
    });
    jQuery('#imgsync-start-image-import').click(function (e) {
        jQuery('body').loader('show');
        setTimeout(function(){
            window.location.reload(1);
        }, 5000);
    });
});
