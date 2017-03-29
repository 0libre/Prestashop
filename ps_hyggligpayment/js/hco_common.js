$(document).ready(function() {
    changeRef();
    if(typeof hco_checkout_tnx != 'undefined'){
      if(window.location.origin + window.location.pathname == hco_checkout_tnx){
    		$('.blockcart').remove();
    		$('#_mobile_cart').remove();
    	}
    }
});
function changeRef() {
    $('[href="' + prestashop.urls.pages.order + '"]').each(function() {
        $(this).attr("href", hco_checkout_url);
    });
}
prestashop.on('updateCart', function() {
    $.post(prestashop.urls.pages.order).then(function(resp) {
        $('#js-checkout-summary').replaceWith($('#js-checkout-summary', resp));
        changeRef();
        if (window.location == hcourl) {
            updateHCO();
        }
    });
});
$(document).on('click',"#hco_carriers input:radio",function(event){
	storeShipping();
});
function storeShipping(){
	$.ajax({
        type: 'POST',
        url: $('#js-delivery').attr('data-url-update'),
        async: false,
        cache: false,
        data: $( "#js-delivery" ).serialize(),
        success: function(jsonData) {
			$('#js-checkout-summary').replaceWith(jsonData.preview);
			updateHCO();
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            alert(errorThrown);
        }
    });
}
function updateHCO() {
    $.ajax({
        type: 'POST',
        url: hcourl,
        async: false,
        cache: false,
        data: 'do_update_call=1&token=' + hygglig_token,
        success: function(jsonData) {
            _hyggligCheckout.updateHygglig();
        },
        error: function(XMLHttpRequest, textStatus, errorThrown) {
            alert(errorThrown);
        }
    });
}
