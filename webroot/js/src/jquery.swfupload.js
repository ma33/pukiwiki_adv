/*
 * SWFUpload jQuery Plugin v1.0.0
 *
 * Copyright (c) 2009 Adam Royle
 * Licensed under the MIT license.
 *
 */

(function(b){var a=["swfupload_loaded_handler","file_queued_handler","file_queue_error_handler","file_dialog_start_handler","file_dialog_complete_handler","upload_start_handler","upload_progress_handler","upload_error_handler","upload_success_handler","upload_complete_handler","queue_complete_handler"];var c=[];b.fn.swfupload=function(){var d=b.makeArray(arguments);return this.each(function(){var g;if(d.length==1&&typeof(d[0])=="object"){g=b(this).data("__swfu");if(!g){var i=d[0];var h=b(this);var f=[];b.merge(f,a);b.merge(f,c);b.each(f,function(l,k){var j=k.replace(/_handler$/,"").replace(/_([a-z])/g,function(){return arguments[1].toUpperCase()});i[k]=function(){var m=b.Event(j);h.trigger(m,b.makeArray(arguments));return !m.isDefaultPrevented()}});b(this).data("__swfu",new SWFUpload(i))}}else{if(d.length>0&&typeof(d[0])=="string"){var e=d.shift();g=b(this).data("__swfu");if(g&&g[e]){g[e].apply(g,d)}}}})};b.swfupload={additionalHandlers:function(){if(arguments.length===0){return c.slice()}else{b(arguments).each(function(e,d){b.merge(c,b.makeArray(d))})}},defaultHandlers:function(){return a.slice()},getInstance:function(d){return b(d).data("__swfu")}}})(jQuery);