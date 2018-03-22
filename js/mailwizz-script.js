// JavaScript Document
jQuery(document).on("change keyup paste keydown","#mailwizz_api_key", function(e) {
		var val = jQuery(this).val();
		if( val !== "" )
			jQuery("#auth-mailwizz").removeAttr('disabled');
		else
			jQuery("#auth-mailwizz").attr('disabled','true');
});

jQuery(document).on( "click", "#auth-mailwizz", function(e){
	
	e.preventDefault();
	jQuery(".smile-absolute-loader").css('visibility','visible');

	var auth_token = jQuery("#mailwizz_api_key").val(),
		public_key = jQuery("#mailwizz_public_key").val(),
		private_key = jQuery("#mailwizz_private_key").val(),
		action = 'update_mailwizz_authentication',
		data = { action:action, authentication_token:auth_token, public_key:public_key, private_key:private_key };

	jQuery.ajax({
		url: ajaxurl,
		data: data,
		type: 'POST',
		dataType: 'JSON',
		success: function(result){
			if(result.status == "success" ){
				jQuery(".bsf-cnlist-mailer-help").hide();
				jQuery("#save-btn").removeAttr('disabled');
				jQuery("#mailwizz_api_key").closest('.bsf-cnlist-form-row').hide();
				jQuery("#mailwizz_public_key").closest('.bsf-cnlist-form-row').hide();
				jQuery("#mailwizz_private_key").closest('.bsf-cnlist-form-row').hide();
				jQuery("#auth-mailwizz").closest('.bsf-cnlist-form-row').hide();
				jQuery(".mailwizz-list").html(result.message);
			} else {
				jQuery(".mailwizz-list").html('<span class="bsf-mailer-error">'+result.message+'</span>');
			}
			jQuery(".smile-absolute-loader").css('visibility','hidden');
		}
	});
	e.preventDefault();
});

jQuery(document).on( "click", "#disconnect-mailwizz", function(){
															
	if(confirm("Are you sure? If you disconnect, your previous campaigns syncing with mailwizz will be disconnected as well.")) {
		var action = 'disconnect_mailwizz';
		var data = {action:action};
		jQuery(".smile-absolute-loader").css('visibility','visible');
		jQuery.ajax({
			url: ajaxurl,
			data: data,
			type: 'POST',
			dataType: 'JSON',
			success: function(result){

				jQuery("#save-btn").attr('disabled','true');
				if(result.message == "disconnected" ){

					jQuery("#mailwizz_api_key").val('');
					jQuery("#mailwizz_public_key").val('');
					jQuery("#mailwizz_private_key").val('');
					jQuery(".mailwizz-list").html('');
					jQuery(".mailwizz-list-empty").closest('.bsf-cnlist-form-row').remove();
					jQuery("#disconnect-mailwizz").replaceWith('<button id="auth-mailwizz" class="button button-secondary auth-button" disabled="true">Authenticate mailwizz</button><span class="spinner" style="float: none;"></span>');
					jQuery("#auth-mailwizz").attr('disabled','true');

				}

				jQuery('.bsf-cnlist-form-row').fadeIn('300');
				jQuery(".bsf-cnlist-mailer-help").show();
				jQuery(".smile-absolute-loader").css('visibility','hidden');
			}
		});
	}
	else {
		return false;
	}
});

