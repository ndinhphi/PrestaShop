<?php
/*
* 2007-2010 PrestaShop 
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
*  @copyright  2007-2010 Prestashop SA
*  @version  Release: $Revision: 1.4 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registred Trademark & Property of PrestaShop SA
*/

include_once(PS_ADMIN_DIR.'/tabs/AdminPreferences.php');

class AdminThemes extends AdminPreferences
{
	/** This value is used in isThemeCompatible method. only version node with an 
	  * higher version number will be used in [theme]/config.xml
		*	@since 1.4.0.11, check theme compatibility 1.4
		* @static
	  */
	static public $check_features_version='1.4';
	/** $check_features is a multidimensional array used to check [theme]/config.xml values, 
	 * and also checks prestashop current configuration if not match.
	 * @static
	 */
	static public $check_features=array(
		'ccc'=>array( // feature key name
			'attributes'=>array(
				'available'=>array( 
					'value'=>'true', // accepted attribute value
					// if value doesnt match, 
					// prestashop configuration value must have thoses values
					'check_if_not_valid'=>array( 
						'PS_CSS_THEME_CACHE'=>0,
						'PS_JS_THEME_CACHE'=>0,
						'PS_HTML_THEME_COMPRESSION'=>0,
						'PS_JS_HTML_THEME_COMPRESSION'=>0,
						'PS_HIGH_HMTL_THEME_COMPRESSION'=>0,
					),
				),
			),
			'error'=>'This theme may not correctly use "combine, compress and cache"',
			'tab' => 'AdminPerformance',
		),
		'guest_checkout'=>array(
			'attributes'=>array(
				'available'=>array(
				'value'=>'true',
				'check_if_not_valid'=>array('PS_GUEST_CHECKOUT_ENABLED'=>0)
				),
			), 
			'error'=>'This theme may not correctly use "guest checkout"',
			'tab' => 'AdminPreferences',
		),
		'one_page_checkout'=>array(
			'attributes'=>array(
				'available'=>array(
					'value'=>'true',
					'check_if_not_valid'=>array('PS_ORDER_PROCESS_TYPE'=>0),
				),
			),
			'error'=>'This theme may not correctly use "one page checkout"',
			'tab' => 'AdminPreferences',
		),
		'store_locator'=>array(
			'attributes'=>array(
				'available'=>array(
				'value'=>'true',
				'check_if_not_valid'=>array('PS_STORES_SIMPLIFIED'=>0,'PS_STORES_DISPLAY_FOOTER'=>0),
				)
			),
			'error'=>'This theme may not correctly use "display store location"',
			'tab' => 'AdminStores',
		)
	);

	public function __construct()
	{
		$this->className = 'Configuration';
		$this->table = 'configuration';

 		$this->_fieldsAppearance = array(
			'PS_LOGO' => array('title' => $this->l('Header logo:'), 'desc' => $this->l('Will appear on main page'), 'type' => 'file', 'thumb' => array('file' => _PS_IMG_.'logo.jpg?date='.time(), 'pos' => 'before')),
			'PS_LOGO_MAIL' => array('title' => $this->l('Mail logo:'), 'desc' => $this->l('Will appear on e-mail headers, if undefined the Header logo will be used'), 'type' => 'file', 'thumb' => array('file' => ((file_exists(dirname(__FILE__).'/../../..'._PS_IMG_.'logo_mail.jpg')) ? _PS_IMG_.'logo_mail.jpg?date='.time() : _PS_IMG_.'logo.jpg?date='.time()), 'pos' => 'before')),
			'PS_LOGO_INVOICE' => array('title' => $this->l('Invoice logo:'), 'desc' => $this->l('Will appear on invoices headers, if undefined the Header logo will be used'), 'type' => 'file', 'thumb' => array('file' => (file_exists(dirname(__FILE__).'/../../..'._PS_IMG_.'logo_invoice.jpg') ? _PS_IMG_.'logo_invoice.jpg?date='.time() : _PS_IMG_.'logo.jpg?date='.time()), 'pos' => 'before')),
			'PS_FAVICON' => array('title' => $this->l('Favicon:'), 'desc' => $this->l('Will appear in the address bar of your web browser'), 'type' => 'file', 'thumb' => array('file' => _PS_IMG_.'favicon.ico?date='.time(), 'pos' => 'after')),
			'PS_STORES_ICON' => array('title' => $this->l('Store icon:'), 'desc' => $this->l('Will appear on the store locator (inside Google Maps)').'<br />'.$this->l('Suggested size: 30x30, Transparent GIF'), 'type' => 'file', 'thumb' => array('file' => _PS_IMG_.'logo_stores.gif?date='.time(), 'pos' => 'before')),
			'PS_NAVIGATION_PIPE' => array('title' => $this->l('Navigation pipe:'), 'desc' => $this->l('Used for navigation path inside categories/product'), 'cast' => 'strval', 'type' => 'text', 'size' => 20),
		);
		$this->_fieldsTheme = array(
			'PS_THEME' => array('title' => $this->l('Theme'), 'validation' => 'isGenericName', 'type' => 'image', 'list' => $this->_getThemesList(), 'max' => 3)
		);
		parent::__construct();
	}

	public function display()
	{
		global $currentIndex;
		
		if (file_exists(_PS_IMG_DIR_.'logo.jpg'))
		{
			list($width, $height, $type, $attr) = getimagesize(_PS_IMG_DIR_.'logo.jpg');
			Configuration::updateValue('SHOP_LOGO_WIDTH', (int)round($width));
			Configuration::updateValue('SHOP_LOGO_HEIGHT', (int)round($height));
		}
		// No cache for auto-refresh uploaded logo
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		$this->_displayForm('appearance', $this->_fieldsAppearance, $this->l('Appearance'), 'width3', 'appearance');
		echo '<br /><br />';
		$this->_displayForm('themes', $this->_fieldsTheme, $this->l('Themes'), 'width3', 'themes');
		echo '<br /><br />';
		if (@ini_get('allow_url_fopen') AND @fsockopen('addons.prestashop.com', 80, $errno, $errst, 3))
			echo '<script type="text/javascript">
				$.post("'.dirname($currentIndex).'/ajax.php",{page:"themes"},function(a){getE("prestastore-content").innerHTML="<legend><img src=\"../img/admin/prestastore.gif\" class=\"middle\" /> '.$this->l('Live from PrestaShop Addons!').'</legend>"+a;});
			</script>
			<fieldset id="prestastore-content" class="width3"></fieldset>';			
		else
			echo '<a href="http://addons.prestashop.com/3-prestashop-themes">'.$this->l('Find new themes on PrestaShop Addons!').'</a>';
	}
	
	/**
	  * Return an array with themes and thumbnails
	  *
	  * @return array
	  */
	private function _getThemesList()
	{
		$dir = opendir(_PS_ALL_THEMES_DIR_);
		while ($folder = readdir($dir))
			if ($folder != '.' AND $folder != '..' AND file_exists(_PS_ALL_THEMES_DIR_.'/'.$folder.'/preview.jpg'))
				$themes[$folder]['name'] = $folder;
		closedir($dir);	
		return isset($themes) ? $themes : array();
	}

	/** This function checks if the theme designer has thunk to make his theme compatible 1.4, 
	* and noticed it on the $theme_dir/config.xml file. If not, some new functionnalities has
	* to be desactivated
	*
	* @since 1.4
	* @param string $theme_dir theme directory
	* @param array $errors reference to controller->_errors
	* @return boolean Validity is ok or not
	*/
	private function isThemeCompatible($theme_dir)
	{
		global $cookie;
		$errors='';
		$all_errors='';
		$return=true;
		$to_check=AdminThemes::$check_features;
		$check_version=AdminThemes::$check_features_version;

		if (!is_file(_PS_ALL_THEMES_DIR_.$theme_dir.'/config.xml'))
		{
			$this->_errors[] .= Tools::displayError('config.xml is missing in your theme path.');
			return false;
		}

		$xml=@simplexml_load_file(_PS_ALL_THEMES_DIR_.$theme_dir.'/config.xml');
		if (!$xml)
		{
			$this->_errors[] .= Tools::displayError('config.xml in your theme path is not a valid xml file').'.';
			return false;
		}
		// will be set to false if any version node in xml is correct
		$xml_version_too_old=true;
		foreach($xml as $version)
		{
			if (isset($version['value']) AND version_compare($version['value']->__toString() , $check_version) >0)
			{
				// if xml file is too old, don't use it
				$this->_errors[] .= Tools::displayError('config.xml theme file has not been created for this version of prestashop.').'.';
			}
			else
			{
				// foreach version in xml file, 
				// node means feature, attributes has to match 
				// the corresponding value in AdminThemes::$check_features[feature] array
				foreach($version as $feature=>$xmlAttributes){
					$attribute=$xmlAttributes->__toString();
					$feature_c=$to_check[$feature];
					if (isset($feature_c))
					{
						// is there at least one attribute (like available) to check ?
						foreach($feature_c['attributes'] as $attribute=>$attr_config)
						{
							if ($xmlAttributes[$attribute]->__toString() != $attr_config['value'])
							{
								// check if current configuration is ok
								if (isset($attr_config['check_if_not_valid']) AND !empty($attr_config['check_if_not_valid']))
									foreach($attr_config['check_if_not_valid'] as $config_key=>$config_val)
									{
										$config_get=Configuration::get($config_key);
										if (Configuration::get($config_key)!=="$config_val")
										{
											$all_errors .= Tools::displayError($feature_c['error']).'.'.(!empty($feature_c['tab'])?' <a href="?tab='.$feature_c['tab'].'&amp;token='.Tools::getAdminTokenLite($feature_c['tab']).'" >'.Tools::displayError('You can disable this function by clicking here').'</a>':'').'<br/>' ;
											$return=false;
											break; // display only one time the same error message.
										}
									}
							}
						}
					} // else, it's not a feature to check (config.xml has extra info ?)
				}
				$xml_version_too_old=false;
			}
		}
		$this->_errors[] = $all_errors;
		return $return;
	}

	/** this functions make checks about AdminThemes configuration edition only.
		* 
		* @since 1.4
		*/
	public function postProcess()
	{
		// new check compatibility theme feature (1.4) :
		$val = Tools::getValue('PS_THEME');
		if (!empty($val) AND !$this->isThemeCompatible($val))
		{
			$this->_errors[] = Tools::displayError('theme may not be fully compatible with this Prestashop version, according to the config.xml theme file.');
			unset($_POST['submitThemes'.$this->table]);
		}
		parent::postProcess();
	}
}

?>
