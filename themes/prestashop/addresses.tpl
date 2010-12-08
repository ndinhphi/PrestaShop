{*
* Copyright (C) 2007-2010 PrestaShop 
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author Prestashop SA <contact@prestashop.com>
*  @copyright  Copyright (c) 2007-2010 Prestashop SA : 6 rue lacepede, 75005 PARIS
*  @version  Release: $Revision: 1.4 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registred Trademark & Property of PrestaShop SA
*}

<script type="text/javascript">
<!--
	var baseDir = '{$base_dir_ssl}';
-->
</script>

{capture name=path}<a href="{$link->getPageLink('my-account.php', true)}">{l s='My account'}</a><span class="navigation-pipe">{$navigationPipe}</span>{l s='My addresses'}{/capture}
{include file="$tpl_dir./breadcrumb.tpl"}

<h1>{l s='My addresses'}</h1>
<p>{l s='Configure your billing and delivery addresses that will be preselected by default when you make an order. You can also add additional addresses, which can be useful for sending gifts or receiving your order at the office.'}</p>

{if $addresses}
<div class="addresses">
	<h3>{l s='Your addresses are listed below.'}</h3>
	<p>{l s='Be sure to update them if they have changed.'}</p>

	{foreach from=$addresses item=address name=myLoop}
	<ul class="address {if $smarty.foreach.myLoop.last}last_item{elseif $smarty.foreach.myLoop.first}first_item{/if} {if $smarty.foreach.myLoop.index % 2}alternate_item{else}item{/if}">
		<li class="address_title">{$address.alias}</li>
		{if $address.company}<li class="address_company">{$address.company}</li>{/if}
		{if $address.vat_number}<li class="address_company">{$address.vat_number}</li>{/if}
		<li class="address_name">{$address.firstname} {$address.lastname}</li>
		<li class="address_address1">{$address.address1}</li>
		{if $address.address2}<li class="address_address2">{$address.address2}</li>{/if}
		<li class="address_city">{$address.postcode} {$address.city}</li>
		<li class="address_country">{$address.country}{if isset($address.state)} ({$address.state}){/if}</li>
		{if $address.phone}<li class="address_phone">{$address.phone}</li>{/if}
		{if $address.phone_mobile}<li class="address_phone_mobile">{$address.phone_mobile}</li>{/if}
		<li class="address_update"><a href="{$link->getPageLink('address.php', true)}?id_address={$address.id_address|intval}" title="{l s='Update'}">{l s='Update'}</a></li>
		<li class="address_delete"><a href="{$link->getPageLink('address.php', true)}?id_address={$address.id_address|intval}&amp;delete" onclick="return confirm('{l s='Are you sure?'}');" title="{l s='Delete'}">{l s='Delete'}</a></li>
	</ul>
	{/foreach}
	<p class="clear" />
</div>
{else}
	<p class="warning">{l s='No addresses available.'}&nbsp;<a href="{$link->getPageLink('address.php', true)}">{l s='add a new one!'}</a></p>
{/if}

<div class="clear address_add"><a href="{$link->getPageLink('address.php', true)}" title="{l s='Add an address'}" class="button_large">{l s='Add an address'}</a></div>

<ul class="footer_links">
	<li><a href="{$link->getPageLink('my-account.php', true)}"><img src="{$img_dir}icon/my-account.gif" alt="" class="icon" /></a><a href="{$link->getPageLink('my-account.php', true)}">{l s='Back to Your Account'}</a></li>
	<li><a href="{$base_dir}"><img src="{$img_dir}icon/home.gif" alt="" class="icon" /></a><a href="{$base_dir}">{l s='Home'}</a></li>
</ul>
