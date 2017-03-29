{if $isSaved}	
	<div class="alert alert-success">
		{l s='Settings updated' mod='ps_hyggligpayment'}
	</div>
{/if}
<link href="{$module_dir|escape:'htmlall':'UTF-8'}views/css/hyggligcheckout_admin.css" rel="stylesheet" type="text/css" media="all" />
<script type="text/javascript" src="{$module_dir|escape:'htmlall':'UTF-8'}views/js/admin.js"></script>

<div class="row">
	<div class="col-xs-12">
		<div class="panel">
			<div class="panel-heading"><i class="icon-info"></i> {l s='Compatibility information' mod='ps_hyggligpayment'}</div>
			<div class="row">
				<p>{l s='This core module for Hygglig API was developed for and is compatible with:' mod='ps_hyggligpayment'}
				<span class="label label-success">PrestaShop 1.7.x</span></p>
			</div>
		</div>
	</div>
</div>
<div class="row">
	<div class="col-xs-12">
		{$commonform}
	</div>
</div>


