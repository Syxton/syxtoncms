(function($) {
    $.fn.hasScrollBar = function() {
        if(typeof this.get(0)  == 'undefined'){
            return "undefined";
        }
        return this.get(0).scrollHeight > this.height();
    }
})(jQuery);

(function($,sr){

  // debouncing function from John Hann
  // http://unscriptable.com/index.php/2009/03/20/debouncing-javascript-methods/
  var debounce = function (func, threshold, execAsap) {
      var timeout;

      return function debounced () {
          var obj = this, args = arguments;
          function delayed () {
              if (!execAsap)
                  func.apply(obj, args);
              timeout = null; 
          };

          if (timeout)
              clearTimeout(timeout);
          else if (execAsap)
              func.apply(obj, args);

          timeout = setTimeout(delayed, threshold || 100); 
      };
  }
    // smartresize 
    jQuery.fn[sr] = function(fn){  return fn ? this.bind('resize', debounce(fn)) : this.trigger(sr); };

})(jQuery,'smartresize');


// usage:
$(window).smartresize(function(){  
    // code that takes it easy...
    if(typeof $("#cboxLoadedContent").get(0) != 'undefined'){
        setTimeout(function(){ 
            //get widths and heights
            var width = $("#colorbox").width();
            if(typeof $('iframe[class=cboxIframe]') == 'undefined' || $('iframe[class=cboxIframe]').length == 0){
                var height = $("#colorbox").height();
                var obj = $("#cboxLoadedContent").get(0);
                
                if($(window).height() < height){ //window is smaller than modal
                    $.colorbox.resize({width:width,height: ($(window).height() * .95)});     
                }else{ //window is larger than modal
                    if($("#cboxLoadedContent").hasScrollBar()){ //and there are scrollbars
                        if(typeof obj != 'undefined' && $(window).height() > obj.scrollHeight){
                            setTimeout(function(){ $.colorbox.resize(); },500);  
                        }
                    }
                }         
                
            }else{ //iframe
                var height = $('iframe[class=cboxIframe]').contents().height();   
                var obj =  $('iframe[class=cboxIframe]');   
                if($(window).height() < height){ //window is smaller than modal
                    $.colorbox.resize({width:width,height: ($(window).height() * .95)});     
                }else{ //window is larger than modal
                    if(height > obj.height()){ //and there are scrollbars
                        if($(window).height() > height+70){
                            $.colorbox.resize({width:width,height: height+70});   
                        }
                    }
                } 
            }
        },500);
    }
});
