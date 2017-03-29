{extends file='checkout/checkout.tpl'}
{block name="content"}
{capture name=path}{l s='Checkout' mod='ps_hyggligpayment'}{/capture}
<style>
	.delivery-options-list label{
		text-align:left;
	}
</style>
{if isset($hygglig_error)}
<div class="alert alert-warning">
    {if $hygglig_error=='empty_cart'}
    {l s='Your cart is empty' mod='ps_hyggligpayment'}
    {else}
    {$hygglig_error|escape:'html':'UTF-8'}
    {/if}
</div>
{else}
<script type="text/javascript">
    var hcourl = "{$hcourl}";
	var hygglig_token = "{$hygglig_token|escape:'html':'UTF-8'}";    
</script>
<section id="content">
	<div class="row">
		<div class="col-md-6">
			<div class="card" style="padding: 10px;">
			{$hygglig_checkout nofilter}
			</div>
		</div>
		<div class="col-md-6">
			{if isset($delivery_option_list)}
			<div class="card" id="hco_carriers" style="padding: 10px;">
				<h1 class="step-title h3" style="margin-bottom: 15px;">
				Leveranssätt
				</h1>
				<div class="content">
					<div id="hook-display-before-carrier">
						{$hookDisplayBeforeCarrier nofilter}
					</div>
					{foreach $delivery_option_list as $id_address => $option_list}
					<div class="delivery-options-list">
						<form class="clearfix" id="js-delivery" data-url-update="/order?ajax=1&amp;action=selectDeliveryOption" method="post">
						<input hidden name="confirmDeliveryOption" value="1"></input>
						{foreach $option_list as $key => $option}
						<div class="form-fields">				
							<div class="delivery-option">
								<div class="col-sm-1">
									<span class="custom-radio pull-xs-left">
									<input type="radio" name="delivery_option[{$id_address|intval}]" id="delivery_option_{$key|escape:'htmlall':'UTF-8'}" value="{$key|escape:'htmlall':'UTF-8'}" {if isset($delivery_option[$id_address|intval]) && $delivery_option[$id_address|intval] == $key}checked="checked"{/if}>
									<span></span>
									</span>
								</div>
								<label for="delivery_option_delivery_option[{$id_address|intval}]" class="col-sm-11 delivery_option_{$key|escape:'htmlall':'UTF-8'}">
								<div class="row">
									<div class="col-xs-12">
										<div class="row">
											<div class="col-xs-12">
												<span class="h6 carrier-name">
												{if $option.unique_carrier}
													{foreach $option.carrier_list as $carrier}
														{$carrier.instance->name|escape:'html':'UTF-8'}
													{/foreach}
												{/if}
												</span>
											</div>
										</div>
									</div>
									<div class="col-xs-12">
										<span class="carrier-delay">
											{if $option.unique_carrier}
												{if isset($carrier.instance->delay[$cookie->id_lang|intval])}
													{$carrier.instance->delay[$cookie->id_lang|intval]}&nbsp;  
												{/if}
											{/if}
										</span>
									</div>
									<div class="col-xs-12">
										<span class="carrier-price">
											{if $option.total_price_with_tax && !$free_shipping}
												{$option.total_price_with_tax} kr inkl. moms.
											{else}
												{l s='Free!' mod='ps_hyggligpayment'}
											{/if}										
										</span>
									</div>
								</div>
								</label>
								<div class="col-md-12 carrier-extra-content">

								</div>
								<div class="clearfix"></div>							
							</div>
							 <div id="hook-display-after-carrier">
								{$hookDisplayAfterCarrier nofilter}
							  </div>
							<div id="extra_carrier"></div>
						</div>
						{/foreach}
						</form>
					</div>
					{/foreach}
				</div>
            {/if}			
			</div>					
			{include file='checkout/_partials/cart-summary.tpl' cart = $cart}
			{if isset($HCO_SHOWLINK) && $HCO_SHOWLINK}
				<a href="{$link->getPageLink('order', true, NULL, 'step=1')|escape:'html':'UTF-8'}"
					class="button btn btn-default button-medium card"
					title="Andra betalsätt">
					<span>Andra betalsätt</span>
				</a>
			{/if}
			{if isset($left_to_get_free_shipping) && $left_to_get_free_shipping>0}
			   <div class="card" style="text-align: center;padding: 15px;">
					<p style="margin-bottom: 0rem;">Handla för <strong>{$left_to_get_free_shipping} {$currencySign|escape:'html':'UTF-8'}</strong> mer för att få fri frakt!</p>
				</div>
			{/if}
			{hook h='displayReassurance'}
		</div>
	</div>
</section>
{/if}
{/block}



  

 
