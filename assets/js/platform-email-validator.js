!function(e){function a(a,r,t){if(t.platformValidatorRequest&&(t.platformValidatorRequest.abort(),t.platformValidatorRequest=null),a)if(r&&r.in_progress&&r.in_progress(r.e),t.platformValidatorLastSuccessReturn&&a===t.platformValidatorLastSuccessReturn.address)r&&r.success&&r.success(t.platformValidatorLastSuccessReturn,r.e);else{var o=!1;if(a.length>512?o="Email address exceeds maximum allowable length of 512 characters.":1!=a.split("@").length-1&&(o="Email address must contain only one @."),o)r&&r.error&&r.error(o,r.e);else{var s=setTimeout(function(){o="Error occurred, unable to validate address.",t.platformValidatorRequest&&(t.platformValidatorRequest.abort(),t.platformValidatorRequest=null),r&&r.error&&r.error(o,r.e)},3e4);t.platformValidatorRequest=e.ajax({type:"GET",url:"https://platformcrm.com/api/v3/email/validate",data:{email:a},dataType:"jsonp",crossDomain:!0,success:function(e){clearTimeout(s),t.platformValidatorLastSuccessReturn=e,r&&r.success&&r.success(e,r.e)},error:function(){clearTimeout(s),o="Error occurred, unable to validate address.",r&&r.error&&r.error(o,r.e)}})}}}e.fn.platform_email_validator=function(r){return this.each(function(){var t,o,s=e(this);s.focusout(function(t){t.timeStamp=Date.now();var l=s.val();l=e.trim(l),s.val(l),l!==o&&(o=l,r.e=t,a(l,r,s))}),s.keyup(function(){clearTimeout(t),s.val&&(t=setTimeout(function(){s.trigger("focusout")},300))})})}}(jQuery);