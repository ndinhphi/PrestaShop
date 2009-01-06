<?php

$filePrefix = 'PREFIX_';

if (function_exists('date_default_timezone_set'))
	date_default_timezone_set('Europe/Paris');

/* Autoload (very usefull on the case where upgrade file call a static object method) */
function __autoload($className)
{
	include_once(INSTALL_PATH.'/../classes/'.$className.'.php');
}

define('_PS_MODULE_DIR_', realpath(INSTALL_PATH).'/../modules/');
define('_PS_INSTALLER_PHP_UPGRADE_DIR_', 'php/');

// utf-8 conversion if needed (before v0.9.8.1 utf-8 was badly supported)
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'utf8.php');
// Configuration cleaner in order to get unique configuration names
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'confcleaner.php');
// Order number in goal to add a number to each old orders
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'invoicenumber.php');
// Order number in goal to add a number to each old orders
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'deliverynumber.php');
// Set paiement module for each currency/country
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'setpaymentmodule.php');
// Set paiement module for each group
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'setpaymentmodulegroup.php');
// Set discount for all categories
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'setdiscountcategory.php');
// Module installation tools
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'module_tools.php');
// Customizations
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'customizations.php');
// Block newsletter
require_once(_PS_INSTALLER_PHP_UPGRADE_DIR_.'blocknewsletter.php');

//old version detection
$oldversion = false;
if (file_exists(SETTINGS_FILE))
{
	include_once(INSTALL_PATH.'/../config/settings.inc.php');
	$oldversion = _PS_VERSION_;
}
else
	die('<action result="fail" error="30" />'."\n");
$versionCompare =  version_compare(INSTALL_VERSION, _PS_VERSION_);
if ($versionCompare == '-1')
	die('<action result="fail" error="27" />'."\n");
elseif ($versionCompare == 0)
	die('<action result="fail" error="28" />'."\n");
elseif ($versionCompare === false)
	die('<action result="fail" error="29" />'."\n");

//check DB access
include(INSTALL_PATH.'/classes/ToolsInstall.php');
$resultDB = ToolsInstall::checkDB(_DB_SERVER_, _DB_USER_, _DB_PASSWD_, _DB_NAME_, false);
if ($resultDB !== true)
	die("<action result='fail' error='".$resultDB."'/>\n");

//custom sql file creation
$upgradeFiles = array();
if ($handle = opendir(INSTALL_PATH.'/sql/upgrade'))
{
    while (false !== ($file = readdir($handle)))
        if ($file != '.' AND $file != '..')
            $upgradeFiles[] = str_replace(".sql", "", $file);
    closedir($handle);
}
if (empty($upgradeFiles))
	die('<action result="fail" error="31" />'."\n");
asort($upgradeFiles);
$neededUpgradeFiles = array();
foreach ($upgradeFiles AS $version)
	if (version_compare($version, _PS_VERSION_) == 1 AND version_compare(INSTALL_VERSION, $version) != -1)
		$neededUpgradeFiles[] = $version;
if (empty($neededUpgradeFiles))
	die('<action result="fail" error="32" />'."\n");

$sqlContent = "";
foreach($neededUpgradeFiles AS $version)
{
	$file = INSTALL_PATH.'/sql/upgrade/'.$version.'.sql';
	if (!file_exists($file))
		die('<action result="fail" error="33" />'."\n");
	if (!$sqlContent .= file_get_contents($file))
		die('<action result="fail" error="33" />'."\n");
	$sqlContent .= "\n";
}
$sqlContent = str_replace($filePrefix, _DB_PREFIX_, $sqlContent);
$sqlContent = preg_split("/;\s*[\r\n]+/",$sqlContent);

//refresh conf file
include(INSTALL_PATH.'/classes/AddConfToFile.php');
$oldLevel = error_reporting(E_ALL);
$datas = array(
	array('_DB_SERVER_', _DB_SERVER_),
	array('_DB_TYPE_', _DB_TYPE_),
	array('_DB_NAME_', _DB_NAME_),
	array('_DB_USER_', _DB_USER_),
	array('_DB_PASSWD_', _DB_PASSWD_),
	array('_DB_PREFIX_', _DB_PREFIX_),
	array('__PS_BASE_URI__', __PS_BASE_URI__),
	array('_THEME_NAME_', _THEME_NAME_),
	array('_COOKIE_KEY_', _COOKIE_KEY_),
	array('_COOKIE_IV_', _COOKIE_IV_),
	array('_PS_CREATION_DATE_', defined("_PS_CREATION_DATE_") ? _PS_CREATION_DATE_ : date('Y-m-d')),
	array('_PS_VERSION_', INSTALL_VERSION)
);
error_reporting($oldLevel);
$confFile = new AddConfToFile(SETTINGS_FILE, 'w');
if ($confFile->error)
	die('<action result="fail" error="'.$confFile->error.'" />'."\n");
	
foreach ($datas AS $data){
	$confFile->writeInFile($data[0], $data[1]);
}
$confFile->writeEndTagPhp();

if ($confFile->error != false)
	die('<action result="fail" error="'.$confFile->error.'" />'."\n");

//sql file execution
global $requests, $warningExist;
$requests = '';
$warningExist = false;

foreach($sqlContent as $query)
{
	$query = trim($query);
	if(!empty($query))
	{
		/* If php code have to be executed */
		if (strpos($query, '/* PHP:') !== false)
		{
			/* Parsing php code */
			$pos = strpos($query, '/* PHP:') + strlen('/* PHP:');
			$phpString = substr($query, $pos, strlen($query) - $pos - strlen(' */;'));
			$php = explode('::', $phpString);
			preg_match('/\((.*)\)/', $phpString, $pattern);
			$paramsString = trim($pattern[0], '()');
			preg_match_all('/([^,]+),? ?/', $paramsString, $parameters);
			if (isset($parameters[1]))
				$parameters = $parameters[1];
			else
				$parameters = array();
			if (is_array($parameters))
				foreach ($parameters AS &$parameter)
					$parameter = str_replace('\'', '', $parameter);

			/* Call a simple function */
			if (strpos($phpString, '::') === false)
				call_user_func_array(str_replace($pattern[0], '', $php[0]), $parameters);
			/* Or an object method */
			else
				call_user_func_array(array($php[0], str_replace($pattern[0], '', $php[1])), $parameters);
			$requests .=
'	<request result="ok">
		<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
	</request>'."\n";
		}
		elseif(!Db::getInstance()->Execute($query))
		{
			$warningExist = true;
			$requests .=
'	<request result="fail">
		<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
		<sqlMsgError><![CDATA['.htmlentities(Db::getInstance()->getMsgError()).']]></sqlMsgError>
		<sqlNumberError><![CDATA['.htmlentities(Db::getInstance()->getNumberError()).']]></sqlNumberError>
	</request>'."\n";
		}
		else
			$requests .=
'	<request result="ok">
		<sqlQuery><![CDATA['.htmlentities($query).']]></sqlQuery>
	</request>'."\n";
	}
}

// Flags copy
if(!$languagesId = Db::getInstance()->ExecuteS('SELECT `id_lang`, `iso_code` FROM `'._DB_PREFIX_.'lang`'))
	$error['infosLanguages'] = '11';

$result = $warningExist ? '<action result="fail" error="34">'."\n" : '<action result="ok" error="">'."\n";
$result .= $requests;
die($result.'</action>'."\n");

?>
