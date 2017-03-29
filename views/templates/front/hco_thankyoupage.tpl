{extends file='page.tpl'}
{block name="page_content_container"}
{capture name=path}{l s='Checkout' mod='ps_hyggligpayment'}{/capture}
{if isset($hygglig_error)}
<div class="alert alert-warning">
    {if $hygglig_error=='empty_cart'}
    {l s='Your cart is empty' mod='ps_hyggligpayment'}
    {else}
    {$hygglig_error|escape:'html':'UTF-8'}
    {/if}
</div>
{else}
<section id="content">
	<div class="row">
		<div class="offset-md-3 col-md-6">
			<div class="card" style="padding:10px;">
			{$hygglig_html nofilter}
			</div>
		</div>
	</div>
</section>
  {hook h='displayOrderConfirmation1'}
  <section id="content-hook-order-confirmation-footer">
    {hook h='displayOrderConfirmation2'}
  </section>

{/if}
{/block}


