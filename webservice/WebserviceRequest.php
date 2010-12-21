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

class WebserviceRequest
{
	/** @var array Errors triggered at execution */
	private $_errors = array();
	
	/** @var string Status header sent at return */
	private $_status = 'HTTP/1.1 200 OK';
	
	/** @var boolean Set if return should display content or not */
	private $_outputEnabled = true;
	
	/** @var boolean Set if the management is specific or if it is classic (entity management) */
	private $_specificManagement = false;
	
	/** @var string Base PrestaShop webservice URL */
	private $_wsUrl;
	
	/** @var string PrestaShop Webservice Documentation URL */
	private $_docUrl = 'http://prestashop.com/docs/1.4/webservice';
	
	/** @var boolean Set if the authentication key was checked */
	private $_authenticated = false;
	
	/** @var string HTTP Method to support */
	private $_method;
	
	/** @var array The segment of the URL */
	private $_urlSegment = array();
	
	/** @var array The segment list of the URL after the "api" segment */
	private $_urlFragments = array();
	
	/** @var int The time in microseconds of the start of the execution of the web service request */
	private $_startTime = 0;
	
	/** @var array The list of each resources manageable via web service */
	private $_resourceList;
	
	/** @var array The configuration parameters of the current resource */
	private $_resourceConfiguration;
	
	/** @var array The permissions for the current key */
	private $_keyPermissions;
	
	/** @var string The XML string to display if web service call succeed */
	private $_xmlOutput = '';
	
	/** @var array The list of objects to display */
	private $_objects;
	
	/** @var ObjectModel The current object to support, it extends the PrestaShop ObjectModel */
	private $_object;
	
	/** @var string The schema to display. If null, no schema have to be displayed and normal management has to be performed */
	private $_schemaToDisplay;
	
	/** @var string The fields to display. These fields will be displayed when retrieving objects */
	private $_fieldsToDisplay = 'minimum';
	
	/** @var array The type of images (general, categories, manufacturers, suppliers, scenes, stores...) */
	private $_imageTypes = array(
		'general' => array(
			'header' => array(),
			'mail' => array(),
			'invoice' => array(),
			'store_icon' => array(),
		),
		/*'products' => array(),*/
		'categories' => array(),
		'manufacturers' => array(),
		'suppliers' => array(),
		'scenes' => array(),
		'stores' => array()
	);
	
	/** @var string The file path of the image to display. If not null, the image will be displayed, even if the XML output was not empty */
	private $_imgToDisplay;
	
	/** @var string The extension of the image to display */
	private $_imgExtension = 'jpg';
	
	/** @var int The maximum size supported when uploading images, in bytes */
	private $_imgMaxUploadSize = 3000000;
	
	/** @var array The list of supported mime types */
	private $_acceptedImgMimeTypes = array('image/gif', 'image/jpg', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png');
	
	/** @var boolean If the current image management has to manage a "default" image (i.e. "No product available") */
	private $_defaultImage = false;
	
	/** @var string If we are in PUT or POST case, we use this attribute to store the xml string value during process */
	private $_inputXml;
	
	/** @var WebserviceRequest Object instance for singleton */
	private static $_instance;
	
	/**
	 * Build a WebserviceRequest object
	 */
	private function __construct ()
	{
		// time logger
		$this->_startTime = microtime(true);
		
		// two global vars, for compatibility with the PS core...
		global $webservice_call, $display_errors;
		$webservice_call = true;
		$display_errors = strtolower(ini_get('display_errors')) != 'off';
		// error handler
		set_error_handler(array('WebserviceRequest', 'webserviceErrorHandler'));
		ini_set('html_errors', 'off');
		
		$this->_wsUrl = Tools::getHttpHost(true).__PS_BASE_URI__.'api/';
	}
	
	/**
	 * Get WebserviceRequest object instance (Singleton)
	 *
	 * @return object WebserviceRequest instance
	 */
	public static function getInstance()
	{
		if(!isset(self::$_instance))
			self::$_instance = new WebserviceRequest();
		return self::$_instance;
	}
	
	/**
	 * Start Webservice request
	 * 	Check webservice activation
	 * 	Check autentication
	 * 	Check resource
	 * 	Check HTTP Method
	 * 	Execute the action
	 * 	Display the result
	 *
	 * @return array Returns an array of results (headers, content, type of resource...)
	 */
	public function fetch($method, $url, $params, $inputXml = NULL)
	{
		// check webservice activation and request authentication
		if ($this->isActivated() && $this->authenticate())
		{
			//parse request url
			$this->_method = $method;
			$this->_urlSegment = explode('/', $url);
			$this->_urlFragments = $params;
			$this->_inputXml = $inputXml;
			
			// check method and resource
			if ($this->checkResource() && $this->checkHTTPMethod())
			{
				// if the resource is a core entity...
				if (!isset($this->_resourceList[$this->_urlSegment[0]]['specific_management']) || !$this->_resourceList[$this->_urlSegment[0]]['specific_management'])
				{
					// load resource configuration
					if ($this->_urlSegment[0] != '')
					{
						$object = new $this->_resourceList[$this->_urlSegment[0]]['class']();
						$this->_resourceConfiguration = $object->getWebserviceParameters();
					}
					
					// execute the action
					switch ($this->_method)
					{
						case 'GET':
						case 'HEAD':
							if ($this->executeEntityGetAndHead())
								$this->writeXmlAfterGet();
							break;
						case 'POST':
							if (array_key_exists(1, $this->_urlSegment))
								$this->setError(400, 'id is forbidden when adding a new resource');
							elseif ($this->executeEntityPost())
								$this->writeXmlAfterModification();
							break;
						case 'PUT':
							if ($this->executeEntityPut())
								$this->writeXmlAfterModification();
							break;
						case 'DELETE':
							$this->executeEntityDelete();
							break;
					}
				}
				// if the management is specific
				else
				{
					$this->_specificManagement = $this->_urlSegment[0];
					switch($this->_specificManagement)
					{
						case 'images':
							$this->manageImages();
							break;
					}
				}
			}
		}
		if ($this->_outputEnabled)
			return $this->returnOutput();
	}
	
	/**
	 * Set the return header status
	 *
	 * @param int $num
	 * @return void
	 */
	public function setStatus($num)
	{
		switch ($num)
		{
			case 200 :
				$this->_status = 'HTTP/1.1 200 OK';
				break;
			case 201 :
				$this->_status = 'HTTP/1.1 201 Created';
				break;
			case 204 :
				$this->_status = 'HTTP/1.1 204 No Content';
				break;
			case 400 :
				$this->_status = 'HTTP/1.1 400 Bad Request';
				break;
			case 401 :
				$this->_status = 'HTTP/1.0 401 Unauthorized';
				break;
			case 404 :
				$this->_status = 'HTTP/1.1 404 Not Found';
				break;
			case 405 :
				$this->_status = 'HTTP/1.1 405 Method Not Allowed';
				break;
			case 500 :
				$this->_status = 'HTTP/1.1 500 Internal Server Error';
				break;
		}
	}
	
	/**
	 * Set a webservice error
	 *
	 * @param int $num
	 * @param string $label
	 * @return void
	 */
	public function setError($num, $label)
	{
		global $display_errors;
		$this->setStatus($num);
		$this->_errors[] = $display_errors ? $label : 'Internal error';
	}
	
	/**
	 * Set a webservice error and propose a new value near from the available values
	 *
	 * @param int $num
	 * @param string $label
	 * @param array $values
	 * @return void
	 */
	public function setErrorDidYouMean($num, $label, $value, $values)
	{
		$this->setError($num, $label.'. Did you mean: "'.$this->getClosest($value, $values).'"?'.(count($values) > 1 ? ' The full list is: "'.implode('", "', $values).'"' : ''));
	}
	
	/**
	 * Return the nearest value picked in the values list
	 *
	 * @param string $input
	 * @param array $words
	 * @return string
	 */
	private function getClosest($input, $words)
	{
		$shortest = -1;
		foreach ($words as $word)
		{
			$lev = levenshtein($input, $word);
			if ($lev == 0)
			{
				$closest = $word;
				$shortest = 0;
				break;
			}
			if ($lev <= $shortest || $shortest < 0)
			{
				$closest = $word;
				$shortest = $lev;
			}
		}
		return $closest;
	}
	
	/**
	 * Used to replace the default PHP error handler, in order to display PHP errors in a XML format 
	 *
	 * @param string $errno contains the level of the error raised, as an integer
	 * @param array $errstr contains the error message, as a string
	 * @param array $errfile errfile, which contains the filename that the error was raised in, as a string
	 * @param array $errline errline, which contains the line number the error was raised at, as an integer
	 * @return boolean Always return true to avoid the default PHP error handler
	 */
	public function webserviceErrorHandler($errno, $errstr, $errfile, $errline)
	{
		if (!(error_reporting() & $errno))
			return;
		switch($errno)
		{
			case E_ERROR:
				WebserviceRequest::getInstance()->setError(500, '[PHP Error #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_WARNING:
				WebserviceRequest::getInstance()->setError(500, '[PHP Warning #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_PARSE:
				WebserviceRequest::getInstance()->setError(500, '[PHP Parse #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_NOTICE:
				WebserviceRequest::getInstance()->setError(500, '[PHP Notice #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_CORE_ERROR:
				WebserviceRequest::getInstance()->setError(500, '[PHP Core #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_CORE_WARNING:
				WebserviceRequest::getInstance()->setError(500, '[PHP Core warning #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_COMPILE_ERROR:
				WebserviceRequest::getInstance()->setError(500, '[PHP Compile #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_COMPILE_WARNING:
				WebserviceRequest::getInstance()->setError(500, '[PHP Compile warning #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_USER_ERROR:
				WebserviceRequest::getInstance()->setError(500, '[PHP Error #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_USER_WARNING:
				WebserviceRequest::getInstance()->setError(500, '[PHP User warning #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_USER_NOTICE:
				WebserviceRequest::getInstance()->setError(500, '[PHP User notice #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_STRICT:
				WebserviceRequest::getInstance()->setError(500, '[PHP Strict #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			case E_RECOVERABLE_ERROR:
				WebserviceRequest::getInstance()->setError(500, '[PHP Recoverable error #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
				break;
			default:
				WebserviceRequest::getInstance()->setError(500, '[PHP Unknown error #'.$errno.'] '.$errstr.' ('.$errfile.', line '.$errline.')');
		}
		return true;
	}
	
	/**
	 * Check if there is one or more error
	 *
	 * @return boolean
	 */
	private function hasErrors()
	{
		return (boolean)$this->_errors;
	}
	
	/**
	 * Check request authentication
	 *
	 * @return boolean
	 */
	private function authenticate()
	{
		if (!$this->hasErrors())
		{
			if (!isset($_SERVER['PHP_AUTH_USER']))
			{
				header('WWW-Authenticate: Basic realm="Welcome to PrestaShop Webservice, please enter the authentication key as the login. No password required."');
				$this->setError(401, 'Please enter the authentication key as the login. No password required');
				return false;
			}
			else
			{
				$auth_key = trim($_SERVER['PHP_AUTH_USER']);
				if (empty($auth_key))
				{
					$this->setError(401, 'Authentication key is empty');
					return false;
				}
				elseif (strlen($auth_key) != '32')
				{
					$this->setError(401, 'Invalid authentication key format');
					return false;
				}
				else
				{
					$keyValidation = Webservice::isKeyActive($auth_key);
					if (is_null($keyValidation))
					{
						$this->setError(401, 'Authentification key does not exist');
						return false;
					}
					elseif($keyValidation === true)
					{
						$this->_keyPermissions = Webservice::getPermissionForAccount($auth_key);
					}
					else
					{
						$this->setError(401, 'Authentification key is not active');
						return false;
					}
					
					if (!$this->_keyPermissions)
					{
						$this->setError(401, 'No permission for this authentication key');
						return false;
					}
				}
			}
			if ($this->hasErrors())
			{
				header('WWW-Authenticate: Basic realm="Welcome to PrestaShop Webservice, please enter the authentication key as the login. No password required."');
				$this->setStatus(401);
				return false;
			}
			else
			{
				// only now we can say the access is authenticated
				$this->_authenticated = true;
				return true;
			}
		}
	}
	
	/**
	 * Check webservice activation
	 *
	 * @return boolean
	 */
	private function isActivated()
	{
		if (!Configuration::get('PS_WEBSERVICE'))
		{
			$this->setError(404, 'The PrestaShop webservice is disabled. Please activate it in the PrestaShop Back Office');
			return false;
		}
		return true;
	}
	
	/**
	 * Check HTTP method
	 *
	 * @return boolean
	 */
	private function checkHTTPMethod()
	{
		if (!in_array($this->_method, array('GET', 'POST', 'PUT', 'DELETE', 'HEAD')))
			$this->setError(405, 'Method '.$this->_method.' is not valid');
		elseif (($this->_method == 'PUT' || $this->_method == 'DELETE') && !array_key_exists(1, $this->_urlSegment))
			$this->setError(401, 'Method '.$this->_method.' need you to specify an id');
		elseif ($this->_urlSegment[0] && !in_array($this->_method, $this->_keyPermissions[$this->_urlSegment[0]]))
			$this->setError(405, 'Method '.$this->_method.' is not allowed for the resource '.$this->_urlSegment[0].' with this authentication key');
		else
			return true;
		return false;
	}
	
	/**
	 * Check resource validity
	 *
	 * @return boolean
	 */
	private function checkResource()
	{
		$this->_resourceList = Webservice::getResources();
		$resourceNames = array_keys($this->_resourceList);
		if ($this->_urlSegment[0] == '')
			$this->_resourceConfiguration['objectsNodeName'] = 'resources';
		elseif (in_array($this->_urlSegment[0], $resourceNames))
		{
			if (!in_array($this->_urlSegment[0], array_keys($this->_keyPermissions)))
			{
				$this->setError(401, 'Resource of type "'.$this->_urlSegment[0].'" is not allowed with this authentication key');
				return false;
			}
		}
		else
		{
			$this->setErrorDidYouMean(400, 'Resource of type "'.$this->_urlSegment[0].'" does not exists', $this->_urlSegment[0], $resourceNames);
			return false;
		}
		return true;
	}
	
	/**
	 * Execute GET and HEAD requests
	 * 
	 * Build filter
	 * Build fields display
	 * Build sort
	 * Build limit
	 * 
	 * @return boolean
	 */
	private function executeEntityGetAndHead()
	{
		if ($this->_resourceConfiguration['objectsNodeName'] != 'resources')
		{
			//construct SQL filter
			$sql_filter = '';
			$sql_join = '';
			if ($this->_urlFragments)
			{
				// if we have to display the schema
				if (array_key_exists('schema', $this->_urlFragments))
				{
					if ($this->_urlFragments['schema'] == 'blank')
					{
						$this->_schemaToDisplay = 'blank';
						return true;
					}
					elseif ($this->_urlFragments['schema'] == 'synopsis')
					{
						$this->_schemaToDisplay = 'synopsis';
						return true;
					}
					else
					{
						$this->setError(400, 'Please select a schema of type \'synopsis\' to get the whole schema informations (which fields are required, which kind of content...) or \'blank\' to get an empty schema to fill before using POST request');
						return false;
					}
				}
				else
				{
					// if there are filters
					if (isset($this->_urlFragments['filter']))
						foreach ($this->_urlFragments['filter'] as $field => $url_param)
						{
							$available_filters = array_keys($this->_resourceConfiguration['fields']);
							if ($field != 'sort' && $field != 'limit')
								if (!in_array($field, $available_filters))
								{
									// if there are linked tables
									if (isset($this->_resourceConfiguration['linked_tables']) && isset($this->_resourceConfiguration['linked_tables'][$field]))
									{
										// contruct SQL join for linked tables
										$sql_join .= 'LEFT JOIN `'._DB_PREFIX_.pSQL($this->_resourceConfiguration['linked_tables'][$field]['table']).'` '.pSQL($field).' ON (main.`'.pSQL($this->_resourceConfiguration['fields']['id']['sqlId']).'` = '.pSQL($field).'.`'.pSQL($this->_resourceConfiguration['fields']['id']['sqlId']).'`)'."\n";
						
										// construct SQL filter for linked tables
										foreach ($url_param as $field2 => $value)
										{
											if (isset($this->_resourceConfiguration['linked_tables'][$field]['fields'][$field2]))
											{
												$linked_field = $this->_resourceConfiguration['linked_tables'][$field]['fields'][$field2];
												$sql_filter .= $this->getSQLRetrieveFilter($linked_field['sqlId'], $value, $field.'.');
											}
											else
											{
												$list = array_keys($this->_resourceConfiguration['linked_tables'][$field]['fields']);
												$this->setErrorDidYouMean(400, 'This filter does not exist for this linked table', $field2, $list);$this->setErrorDidYouMean(400, 'This declination does not exist', $this->_urlSegment[4], $normalImageSizeNames);
												return false;
											}
										}
									}
									// if there are filters on linked tables but there are no linked table
									elseif (is_array($url_param))
									{
										if (isset($this->_resourceConfiguration['linked_tables']))
											$this->setErrorDidYouMean(400, 'This linked table does not exist', $field, array_keys($this->_resourceConfiguration['linked_tables']));
										else
											$this->setError(400, 'There is no existing linked table for this resource');
										return false;
									}
									else
									{
										$this->setErrorDidYouMean(400, 'This filter does not exist', $field, $available_filters);
										return false;
									}
								}
								elseif ($url_param == '')
								{
									$this->setError(400, 'The filter "'.$field.'" is malformed.');
									return false;
								}
								else
								{
									if (isset($this->_resourceConfiguration['fields'][$field]['getter']))
									{
										$this->setError(400, 'The field "'.$field.'" is dynamic. It is not possible to filter GET query with this field.');
										return false;
									}
									else
									{
										if (isset($this->_resourceConfiguration['retrieveData']['tableAlias']))
											$sql_filter .= $this->getSQLRetrieveFilter($this->_resourceConfiguration['fields'][$field]['sqlId'], $url_param, $this->_resourceConfiguration['retrieveData']['tableAlias'].'.');
										else
											$sql_filter .= $this->getSQLRetrieveFilter($this->_resourceConfiguration['fields'][$field]['sqlId'], $url_param);
									}
								}
						}
				}
			}
	
			// set the fields to display in the list : "full", "minimum", "field_1", "field_1,field_2,field_3" //TODO manage linked_tables too
			if (isset($this->_urlFragments['display']))
			{
				$this->_fieldsToDisplay = $this->_urlFragments['display'];
				if ($this->_fieldsToDisplay != 'full')
				{
					preg_match('#^\[(.*)\]$#Ui', $this->_fieldsToDisplay, $matches);
					if (count($matches))
					{
						$fieldsToTest = explode(',', $matches[1]);
						foreach ($fieldsToTest as $fieldToDisplay)
							if (!isset($this->_resourceConfiguration['fields'][$fieldToDisplay]))
							{
								$this->setError(400,'Unable to display this field. However, these are available: '.implode(', ', array_keys($this->_resourceConfiguration['fields'])));
								return false;
							}
							$this->_fieldsToDisplay = !$this->hasErrors() ? $fieldsToTest : 'minimal';
					}
					else
					{
						$this->setError(400, 'The \'display\' synthax is wrong. You can set \'full\' or \'[field_1,field_2,field_3,...]\'. These are available: '.implode(', ', array_keys($this->_resourceConfiguration['fields'])));
						return false;
					}
				}
			}
	
			// construct SQL Sort
			$sql_sort = '';
			$available_filters = array_keys($this->_resourceConfiguration['fields']);
			if (isset($this->_urlFragments['sort']))
			{
				preg_match('#^\[(.*)\]$#Ui', $this->_urlFragments['sort'], $matches);
				if (count($matches) > 1)
					$sorts = explode(',', $matches[1]);
				else
					$sorts = array($this->_urlFragments['sort']);
		
				$sql_sort .= ' ORDER BY ';
		
				foreach ($sorts as $sort)
				{
					$sortArgs = explode('_', $sort);
					if (count($sortArgs) != 2 || (strtoupper($sortArgs[1]) != 'ASC' && strtoupper($sortArgs[1]) != 'DESC'))
					{
						$this->setError(400, 'The "sort" value has to be formed as this example: "field_ASC" ("field" has to be an available field)');
						return false;
					}
					elseif (!in_array($sortArgs[0], $available_filters))
					{
						$this->setError(400, 'Unable to filter by this field. However, these are available: '.implode(', ', $available_filters));
						return false;
					}
					else
					{
						$sql_sort .= (isset($this->_resourceConfiguration['retrieveData']['tableAlias']) ? $this->_resourceConfiguration['retrieveData']['tableAlias'].'.' : '').'`'.pSQL($this->_resourceConfiguration['fields'][$sortArgs[0]]['sqlId']).'` '.strtoupper($sortArgs[1]).', ';// ORDER BY `field` ASC|DESC
					}
				}
				$sql_sort = rtrim($sql_sort, ', ')."\n";
			}

			//construct SQL Limit
			$sql_limit = '';
			if (isset($this->_urlFragments['limit']))
			{
				$limitArgs = explode(',', $this->_urlFragments['limit']);
				if (count($limitArgs) > 2)
				{
					$this->setError(400, 'The "limit" value has to be formed as this example: "5,25" or "10"');
					return false;
				}
				else
				{
					$sql_limit .= ' LIMIT '.(int)($limitArgs[0]).(isset($limitArgs[1]) ? ', '.(int)($limitArgs[1]) : '')."\n";// LIMIT X|X, Y
				}
			}


			$this->_objects = array();
			if (!isset($this->_urlSegment[1]) || !strlen($this->_urlSegment[1]))
			{
					$this->_resourceConfiguration['retrieveData']['params'][] = $sql_join;
					$this->_resourceConfiguration['retrieveData']['params'][] = $sql_filter;
					$this->_resourceConfiguration['retrieveData']['params'][] = $sql_sort;
					$this->_resourceConfiguration['retrieveData']['params'][] = $sql_limit;
				//list entities
				$tmp = new $this->_resourceConfiguration['retrieveData']['className']();
				$sqlObjects = call_user_func_array(array($tmp, $this->_resourceConfiguration['retrieveData']['retrieveMethod']), $this->_resourceConfiguration['retrieveData']['params']);
				if ($sqlObjects)
					foreach ($sqlObjects as $sqlObject)
					{
						$this->_objects[] = new $this->_resourceConfiguration['retrieveData']['className']($sqlObject[$this->_resourceConfiguration['fields']['id']['sqlId']]);
					}
			}
			else
			{
				//get entity details
				$object = new $this->_resourceConfiguration['retrieveData']['className']($this->_urlSegment[1]);
				if ($object->id)
					$this->_objects[] = $object;
				else
				{
					$this->setStatus(404);
					$this->_outputEnabled = false;
					return false;
				}
			}

		}
		return true;
	}
	
	/**
	 * Execute POST method on a PrestaShop entity
	 * 
	 * @return boolean
	 */
	private function executeEntityPost()
	{
		$this->_object = new $this->_resourceConfiguration['retrieveData']['className']();
		return $this->saveEntityFromXml($this->_inputXml, 201);
	}
	
	/**
	 * Execute PUT method on a PrestaShop entity
	 * 
	 * @return boolean
	 */
	private function executeEntityPut()
	{
		$this->_object = new $this->_resourceConfiguration['retrieveData']['className']($this->_urlSegment[1]);

		if ($this->_object->id)
		{
			return $this->saveEntityFromXml($this->_inputXml, 200);
		}
		else
		{
			$this->setStatus(404);
			$this->_outputEnabled = false;
			return false;
		}
	}
	
	/**
	 * Execute DELETE method on a PrestaShop entity
	 * 
	 * @return boolean
	 */
	private function executeEntityDelete()
	{
		$object = new $this->_resourceConfiguration['retrieveData']['className'](intval($this->_urlSegment[1]));
		if (!$object->id)
			$this->setStatus(404);
		elseif (!$object->delete())
			$this->setStatus(500);
		$output = false;
	}
	
	/**
	 * Write XML output after GET and HEAD action
	 * 
	 * @return void
	 */
	private function writeXmlAfterGet()
	{
		// list entities
		if (!isset($this->_urlSegment[1]) || !strlen($this->_urlSegment[1]))
		{
			if (($this->_resourceConfiguration['objectsNodeName'] != 'resources' && count($this->_objects)) || 
			($this->_resourceConfiguration['objectsNodeName'] == 'resources' && count($this->_resourceList)) ||
			($this->_schemaToDisplay != null))
			{
				if ($this->_resourceConfiguration['objectsNodeName'] != 'resources')
				{
					if (!is_null($this->_schemaToDisplay))
					{
						// display ready to use schema
						if ($this->_schemaToDisplay == 'blank')
						{
							$this->_fieldsToDisplay = 'full';
							$this->_xmlOutput .= $this->getXmlFromEntity();
						
						}
						// display ready to use schema
						else
						{
							$this->_fieldsToDisplay = 'full';
							$this->_xmlOutput .= $this->getXmlFromEntity();
						}
					}
					// display specific resources list
					else
					{
						$this->_xmlOutput .= '<'.$this->_resourceConfiguration['objectsNodeName'].'>'."\n";
						if ($this->_fieldsToDisplay == 'minimum')
							foreach ($this->_objects as $object)
								$this->_xmlOutput .= '<'.$this->_resourceConfiguration['objectNodeName'].(array_key_exists('id', $this->_resourceConfiguration['fields']) ? ' id="'.$object->id.'" xlink:href="'.$this->_wsUrl.$this->_resourceConfiguration['objectsNodeName'].'/'.$object->id.'"' : '').' />'."\n";
						else
							foreach ($this->_objects as $object)
								$this->_xmlOutput .= $this->getXmlFromEntity($object);
						$this->_xmlOutput .= '</'.$this->_resourceConfiguration['objectsNodeName'].'>'."\n";
					}
				}
				// display all resourceources list
				else
				{
					$this->_xmlOutput .= '<api shop_name="'.Configuration::get('PS_SHOP_NAME').'" get="true" put="false" post="false" delete="false" head="true">'."\n";
					foreach ($this->_resourceList as $resourceName => $resource)
						if (in_array($resourceName, array_keys($this->_keyPermissions)))
						{
							$this->_xmlOutput .= '<'.$resourceName.' xlink:href="'.$this->_wsUrl.$resourceName.'"
								get="'.(in_array('GET', $this->_keyPermissions[$resourceName]) ? 'true' : 'false').'"
								put="'.(in_array('PUT', $this->_keyPermissions[$resourceName]) ? 'true' : 'false').'"
								post="'.(in_array('POST', $this->_keyPermissions[$resourceName]) ? 'true' : 'false').'"
								delete="'.(in_array('DELETE', $this->_keyPermissions[$resourceName]) ? 'true' : 'false').'"
								head="'.(in_array('HEAD', $this->_keyPermissions[$resourceName]) ? 'true' : 'false').'"
							>
							<description>'.$resource['description'].'</description>';
							if (!isset($resource['specific_management']) || !$resource['specific_management'])
							$this->_xmlOutput .= '
							<schema type="blank" xlink:href="'.$this->_wsUrl.$resourceName.'?schema=blank" />
							<schema type="synopsis" xlink:href="'.$this->_wsUrl.$resourceName.'?schema=synopsis" />';
							$this->_xmlOutput .= '
							</'.$resourceName.'>'."\n";
						}
					$this->_xmlOutput .= '</api>'."\n";
				}
			
			}
			else
				$this->_xmlOutput .= '<'.$this->_resourceConfiguration['objectsNodeName'].' />'."\n";
		}
		//display one resource
		else
		{
			$this->_fieldsToDisplay = 'full';
			$this->_xmlOutput .= $this->getXmlFromEntity($this->_objects[0]);
		}
	}
	
	/**
	 * Write XML output after POST and PUT action
	 * 
	 * @return void
	 */
	private function writeXmlAfterModification()
	{
		$this->_fieldsToDisplay = 'full';
		$this->_xmlOutput .= $this->getXmlFromEntity($this->_object);
	}
	
	/**
	 * save Entity Object from XML
	 * 
	 * @param string $xmlString
	 * @param int $successReturnCode
	 * @return boolean
	 */
	private function saveEntityFromXml($xmlString, $successReturnCode)
	{
		$xml = new SimpleXMLElement($xmlString);
		$attributes = $xml->children()->{$this->_resourceConfiguration['objectNodeName']}->children();
		$i18n = false;
		// attributes
		foreach ($this->_resourceConfiguration['fields'] as $fieldName => $fieldProperties)
		{
			$sqlId = $fieldProperties['sqlId'];
			if (isset($attributes->$fieldName) && isset($fieldProperties['sqlId']) && (!isset($fieldProperties['i18n']) || !$fieldProperties['i18n']))
			{
				if (isset($fieldProperties['setter']))
				{
					// if we have to use a specific setter
					if (!$fieldProperties['setter'])
					{
						// if it's forbidden to set this field
						$this->setError(400, 'parameter "'.$fieldName.'" not writable. Please remove this attribute of this XML');
						return false;
					}
					else
						$this->_object->$fieldProperties['sqlId']((string)$attributes->$fieldName);
				}
				else
					$this->_object->$sqlId = (string)$attributes->$fieldName;
			}
			elseif (isset($fieldProperties['required']) && $fieldProperties['required'] && !$fieldProperties['i18n'])
			{
				$this->setError(400, 'parameter "'.$fieldName.'" required');
				return false;
			}
			elseif (!isset($fieldProperties['required']) || !$fieldProperties['required'])
				$this->_object->$sqlId = null;
		}
	
		if (isset($attributes->associations))
			foreach ($attributes->associations->children() as $association)
			{
				// translated attributes
				if ($association->getName() == 'i18n')
				{
					$i18n = true;
					$fields = $association->children();
					foreach ($fields as $field)
					{
						$fieldName = $field->getName();
						$langs = array();
						foreach ($field->children() as $translation)
						{
							$langs[(string)$translation['id']] = (string)$translation;
						}
						$this->_object->$fieldName = $langs;
					}
				}
			}
		if (!$this->hasErrors())
		{
			if ($i18n && ($retValidateFieldsLang = $this->_object->validateFieldsLang(false, true)) !== true)
			{
				$this->setError(400, 'Validation error: "'.$retValidateFieldsLang.'"');
				return false;
			}
			elseif (($retValidateFields = $this->_object->validateFields(false, true)) !== true)
			{
				$this->setError(400, 'Validation error: "'.$retValidateFields.'"');
				return false;
			}
			else
			{
				//d($this->_object);
				if($this->_object->save())
				{
					if (isset($attributes->associations))
						foreach ($attributes->associations->children() as $association)
						{
							// associations
							if (isset($this->_resourceConfiguration['associations'][$association->getName()]))
							{
								$assocItems = $association->children();
								foreach ($assocItems as $assocItem)
								{
									$fields = $assocItem->children();
									$values = array();
									foreach ($fields as $field)
										$values[] = (string)$field;
									$setter = $this->_resourceConfiguration['associations'][$association->getName()]['setter'];
									if (!$this->_object->$setter($values))
									{
										$this->setError(500, 'Error occurred while setting the '.$association->getName().' value');
										return false;
									}
								}
							}
							elseif ($association->getName() != 'i18n')
							{
								$this->setError(400, 'The association "'.$association->getName().'" does not exists');
								return false;
							}
						}
					if (!$this->hasErrors())
					{
						$this->setStatus($successReturnCode);
						return true;
					}
				}
				else
					$this->setError(500, 'Unable to save resource');
			}
		}
	}
	
	/**
	 * get SQL retrieve Filter
	 * 
	 * @param string $sqlId
	 * @param string $filterValue
	 * @param string $tableAlias = 'main.'
	 * @return string
	 */
	private function getSQLRetrieveFilter($sqlId, $filterValue, $tableAlias = 'main.')
	{
		$ret = '';
		preg_match('/^(.*)\[(.*)\](.*)$/', $filterValue, $matches);
		if (count($matches) > 1)
		{
			if ($matches[1] == '%' || $matches[3] == '%')
				$ret .= ' AND '.$tableAlias.'`'.pSQL($sqlId).'` LIKE "'.$matches[1].pSQL($matches[2]).$matches[3]."\"\n";// AND field LIKE %value%
			elseif ($matches[1] == '' && $matches[3] == '')
			{
				preg_match('/^(\d+)(\|(\d+))+$/', $matches[2], $matches2);
				preg_match('/^(\d+)$/', $matches[2], $matches4);
				if (count($matches2) > 0 || count($matches4) > 1)
				{
					$values = explode('|', $matches[2]);
					$ret .= ' AND (';
					$temp = '';
					foreach ($values as $value)
						$temp .= $tableAlias.'`'.pSQL($sqlId).'` = "'.pSQL($value).'" OR ';// AND (field = value3 OR field = value7 OR field = value9)
					$ret .= rtrim($temp, 'OR ').')'."\n";
				}
				else
				{
					preg_match('/^(\d+),(\d+)$/', $matches[2], $matches3);
					if (count($matches3) > 0)
					{
						$values = explode(',', $matches[2]);
						$ret .= ' AND '.$tableAlias.'`'.pSQL($sqlId).'` BETWEEN "'.$values[0].'" AND "'.$values[1]."\"\n";// AND field BETWEEN value3 AND value4
					}
				}
			}
			elseif ($matches[1] == '>')
				$ret .= ' AND '.$tableAlias.'`'.pSQL($sqlId).'` > "'.pSQL($matches[2])."\"\n";// AND field > value3
			elseif ($matches[1] == '<')
				$ret .= ' AND '.$tableAlias.'`'.pSQL($sqlId).'` > "'.pSQL($matches[2])."\"\n";// AND field < value3
			elseif ($matches[1] == '!')
				$ret .= ' AND '.$tableAlias.'`'.pSQL($sqlId).'` != "'.pSQL($matches[2])."\"\n";// AND field IS NOT value3
		}
		else
			$ret .= ' AND '.$tableAlias.'`'.pSQL($sqlId).'` = "'.pSQL($filterValue)."\"\n";
		return $ret;
	}
	
	/**
	 * get XML From Object Entity
	 * 
	 * @param ObjectModel $object = null
	 * @return string
	 */
	private function getXmlFromEntity($object = null)
	{
	
		// two modes are available : 'schema', or 'display entity'
	
		$ret = '<'.$this->_resourceConfiguration['objectNodeName'].'>'."\n";
		// display fields
		foreach ($this->_resourceConfiguration['fields'] as $key => $field)
		{
			if ($this->_fieldsToDisplay == 'full' || in_array($key, $this->_fieldsToDisplay))
			{
				if ($key != 'id')//TODO remove this condition
				{
					// get the field value with a specific getter
					if (isset($field['getter']) && $this->_schemaToDisplay != 'blank')
						$object->$key = $object->$field['getter']();
			
					// display i18n fields
					if (isset($field['i18n']) && $field['i18n'])
					{
						$ret .= '<'.$field['sqlId'];
						if ($this->_schemaToDisplay == 'synopsis')
						{
							if (array_key_exists('required', $field) && $field['required'])
								$ret .= ' required="true"';
							if (array_key_exists('maxSize', $field) && $field['maxSize'])
								$ret .= ' maxSize="'.$field['maxSize'].'"';
							if (array_key_exists('validateMethod', $field) && $field['validateMethod'])
								$ret .= ' format="'.implode(' ', $field['validateMethod']).'"';
						}
						$ret .= ">\n";
						if (!is_null($this->_schemaToDisplay))
							$ret .= '<language id="" '.($this->_schemaToDisplay == 'synopsis' ? 'format="isUnsignedId"' : '').'></language>'."\n";
						else
							foreach ($object->$key as $idLang => $value)
								$ret .= '<language id="'.$idLang.'" xlink:href="'.$this->_wsUrl.'languages/'.$idLang.'"><![CDATA['.$value.']]></language>'."\n";
						$ret .= '</'.$field['sqlId'].'>'."\n";
					}
					else
					{
						// display not i18n field value
						$ret .= '<'.$field['sqlId'];
						if (array_key_exists('xlink_resource', $field) && $this->_schemaToDisplay != 'blank')
							$ret .= ' xlink:href="'.$this->_wsUrl.$field['xlink_resource'].'/'.($this->_schemaToDisplay != 'synopsis' ? $object->$key : '').'"';
						if (isset($field['getter']) && $this->_schemaToDisplay != 'blank')
							$ret .= ' not_filterable="true"';
						if ($this->_schemaToDisplay == 'synopsis')
						{
							if (array_key_exists('required', $field) && $field['required'])
								$ret .= ' required="true"';
							if (array_key_exists('maxSize', $field) && $field['maxSize'])
								$ret .= ' maxSize="'.$field['maxSize'].'"';
							if (array_key_exists('validateMethod', $field) && $field['validateMethod'])
								$ret .= ' format="'.implode(' ', $field['validateMethod']).'"';
						}
						$ret .= '>';
						if (is_null($this->_schemaToDisplay))
							$ret .= '<![CDATA['.$object->$key.']]>';
						$ret .= '</'.$field['sqlId'].'>'."\n";
					}
				}
				else
						// display id
						if (is_null($this->_schemaToDisplay))
						$ret .= '<id><![CDATA['.$object->id.']]></id>'."\n";
			}
		}
	
		// display associations
		if (isset($this->_resourceConfiguration['associations']))
		{
			$ret .= '<associations>'."\n";
			foreach ($this->_resourceConfiguration['associations'] as $assocName => $association)
			{
				$ret .= '<'.$assocName.'>'."\n";
				$getter = $this->_resourceConfiguration['associations'][$assocName]['getter'];
				if (method_exists($object, $getter))
				{
					$associationResources = $object->$getter();
					if (is_array($associationResources))
						foreach ($associationResources as $associationResource)
						{
							$ret .= '<'.$this->_resourceConfiguration['associations'][$assocName]['resource'].' xlink:href="'.$this->_wsUrl.$assocName.'/'.$associationResource['id'].'">'."\n";
							foreach ($associationResource as $fieldName => $fieldValue)
							{
								if ($fieldName == 'id')
									$ret .= '<'.$fieldName.'><![CDATA['.$fieldValue.']]></'.$fieldName.'>'."\n";
							}
							$ret .= '</'.$this->_resourceConfiguration['associations'][$assocName]['resource'].'>'."\n";
						}
				}
				$ret .= '</'.$assocName.'>'."\n";
			}
			$ret .= '</associations>'."\n";
		}
		$ret .= '</'.$this->_resourceConfiguration['objectNodeName'].'>'."\n";
		return $ret;
	}
	
	/**
	 *  Display XML and die the script
	 *  
	 * @return void
	 */
	private function returnOutput()
	{
		$return = array();
		
		// write headers
		
		$return['status'] = $this->_status;
		
		$return['x_powered_by'] = 'X-Powered-By: PrestaShop Webservice';
		// write this header only now (avoid hackers happiness...)
		if ($this->_authenticated)
			$return['ps_ws_version'] = 'PSWS-Version: '._PS_VERSION_;
		$return['execution_time'] =  'Execution-Time: '.round(microtime(true) - $this->_startTime,3);
		
		// display image content if needed
		if ($this->_imgToDisplay)
		{
			switch ($this->_imgExtension)
			{
				case 'jpg':
					$im = @imagecreatefromjpeg($this->_imgToDisplay);
					break;
				case 'gif':
					$im = @imagecreatefromgif($this->_imgToDisplay);
					break;
			}
			if(!$im)
				$this->setError(500, 'Unable to load the image "'.str_replace(_PS_ROOT_DIR_, '[SHOP_ROOT_DIR]', $this->_imgToDisplay).'"');
			else
			{
				switch ($this->_imgExtension)
				{
					case 'jpg':
						return array(
							'type' => 'image',
							'content_type' => 'Content-Type: image/jpeg',
							'resource' => $im
						);
						break;
					case 'gif':
						return array(
							'type' => 'image',
							'content_type' => 'Content-Type: image/gif',
							'resource' => $im
						);
						break;
				}
				imagedestroy($im);
			}
		}
		
		// if errors appends when creating return xml, we replace the usual xml content by the nice error handler content
		if ($this->hasErrors())
		{
			$this->_xmlOutput = '<errors>'."\n";
			foreach ($this->_errors as $error)
				$this->_xmlOutput .= '<error><![CDATA['.$error.']]></error>'."\n";
			$this->_xmlOutput .= '</errors>'."\n";
			restore_error_handler();
		}
		
		// display xml content if needed
		if (strlen($this->_xmlOutput) > 0)
		{
			$xml_start = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
			$xml_start .= '<prestashop xmlns="'.$this->_docUrl.'" xmlns:xlink="http://www.w3.org/1999/xlink">'."\n";
			$xml_end = '</prestashop>'."\n";
			
			$return['type'] = 'xml';
			$return['content_type'] = 'Content-Type: text/xml';
			$return['content_sha1'] = 'Content-Sha1: '.sha1($this->_xmlOutput);
			$return['content'] = $xml_start.$this->_xmlOutput.$xml_end;
			return $return;
		}
	}
	
	/**
	 * Management of images URL segment
	 * 
	 * @return boolean
	 */
	private function manageImages()
	{
		/*
		 * available cases api/... :
		 *   
		 *   images ("types_list") (N-1)
		 *   	GET    (xml)
		 *   images/general ("general_list") (N-2)
		 *   	GET    (xml)
		 *   images/general/[header,+] ("general") (N-3)
		 *   	GET    (bin)
		 *   	PUT    (bin)
		 *   
		 *   
		 *   images/[categories,+] ("normal_list") (N-2)
		 *   	GET    (xml)
		 *   images/[categories,+]/[1,+] ("normal") (N-3)
		 *   	GET    (bin)
		 *   	PUT    (bin)
		 *   	DELETE
		 *   	POST   (bin) (if image does not exists)
		 *   images/[categories,+]/[1,+]/[small,+] ("normal_resized") (N-4)
		 *   	GET    (bin)
		 *   images/[categories,+]/default ("display_list_of_langs") (N-3)
		 *   	GET    (xml)
		 *   images/[categories,+]/default/[en,+] ("normal_default_i18n")  (N-4)
		 *   	GET    (bin)
		 *   	POST   (bin) (if image does not exists)
		 *      PUT    (bin)
		 *      DELETE    (bin)
		 *   images/[categories,+]/default/[en,+]/[small,+] ("normal_default_i18n_resized")  (N-5)
		 *   
		 *   
		 *   	GET    (bin)
		 *   images/product ("product_list")  (N-2)
		 *   	GET    (xml) (list of image)
		 *   images/product/[1,+] ("product_description")  (N-3)
		 *   	GET    (xml) (legend, declinations, xlink to images/product/[1,+]/bin)
		 *   images/product/[1,+]/bin ("product_bin")  (N-4)
		 *   	GET    (bin)
		 *      POST   (bin) (if image does not exists)
		 *   images/product/[1,+]/[1,+] ("product_declination")  (N-4)
		 *   	GET    (bin)
		 *   	POST   (xml) (legend)
		 *   	PUT    (xml) (legend)
		 *      DELETE
		 *   images/product/[1,+]/[1,+]/bin ("product_declination_bin") (N-5)
		 *   	POST   (bin) (if image does not exists)
		 *   	GET    (bin)
		 *   	PUT    (bin)
		 *   images/product/[1,+]/[1,+]/[small,+] ("product_declination_resized") (N-5)
		 *   	GET    (bin)
		 *   images/product/default ("product_default") (N-3)
		 *   	GET    (bin)
		 *   images/product/default/[en,+] ("product_default_i18n") (N-4)
		 *   	GET    (bin)
		 *      POST   (bin)
		 *      PUT   (bin)
		 *      DELETE
		 *   images/product/default/[en,+]/[small,+] ("product_default_i18n_resized") (N-5)
		 * 		GET    (bin)
		 * 
		 * */
		
		// Pre configuration...
		if (count($this->_urlSegment) == 1)
			$this->_urlSegment[1] = '';
		if (count($this->_urlSegment) == 2)
			$this->_urlSegment[2] = '';
		if (count($this->_urlSegment) == 3)
			$this->_urlSegment[3] = '';
		if (count($this->_urlSegment) == 4)
			$this->_urlSegment[4] = '';
		if (count($this->_urlSegment) == 5)
			$this->_urlSegment[5] = '';
		
		switch ($this->_urlSegment[1])
		{
			// general images management : like header's logo, invoice logo, etc...
			case 'general':
				return $this->manageGeneralImages();
				break;
			// normal images management : like the most entity images (categories, manufacturers..)...
			case 'categories':
			case 'manufacturers':
			case 'suppliers':
			case 'stores':
				switch ($this->_urlSegment[1])
				{
					case 'categories':
						$directory = _PS_CAT_IMG_DIR_;
						break;
					case 'manufacturers':
						$directory = _PS_MANU_IMG_DIR_;
						break;
					case 'suppliers':
						$directory = _PS_SUPP_IMG_DIR_;
						break;
					case 'stores':
						$directory = _PS_STORE_IMG_DIR_;
						break;
				}
				return $this->manageNormalImages($directory);
				break;
			
			// product image management : many image for one entity (product)
			case 'products':
				$this->setError(500, 'Management product images is currently not implemented at this version');
				return false;
				break;
			
			// images root node management : many image for one entity (product)
			case '':
				$this->_xmlOutput .= '<image_types>'."\n";
				foreach ($this->_imageTypes as $imageTypeName => $imageType)
					$this->_xmlOutput .= '<'.$imageTypeName.' xlink:href="'.$this->_wsUrl.$this->_urlSegment[0].'/'.$imageTypeName.'" get="true" put="false" post="false" delete="false" head="true" upload_allowed_mimetypes="'.implode(', ', $this->_acceptedImgMimeTypes).'" />'."\n";
				$this->_xmlOutput .= '</image_types>'."\n";
				return true;
				break;
			
			default:
				$this->setErrorDidYouMean(400, 'Image of type "'.$this->_urlSegment[1].'" does not exists', $this->_urlSegment[1], array_keys($this->_imageTypes));
				return false;
		}
	}
	
	/**
	 * Management of general images
	 * 
	 * @return boolean
	 */
	private function manageGeneralImages()
	{
		$path = '';
		$alternative_path = '';
		switch ($this->_urlSegment[2])
		{
			// Set the image path on display in relation to the header image
			case 'header':
				if (in_array($this->_method, array('GET','HEAD','PUT')))
					$path = _PS_IMG_DIR_.'logo.jpg';
				else
				{
					$this->setError(405, 'This method is not allowed with general image resources.');
					return false;
				}
				break;
			
			// Set the image path on display in relation to the mail image
			case 'mail':
				if (in_array($this->_method, array('GET','HEAD','PUT')))
				{
					$path = _PS_IMG_DIR_.'logo_mail.jpg';
					$alternative_path = _PS_IMG_DIR_.'logo.jpg';
				}
				else
				{
					$this->setError(405, 'This method is not allowed with general image resources.');
					return false;
				}
				break;
			
			// Set the image path on display in relation to the invoice image
			case 'invoice':
				if (in_array($this->_method, array('GET','HEAD','PUT')))
				{
					$path = _PS_IMG_DIR_.'logo_invoice.jpg';
					$alternative_path = _PS_IMG_DIR_.'logo.jpg';
				}
				else
				{
					$this->setError(405, 'This method is not allowed with general image resources.');
					return false;
				}
				break;
			
			// Set the image path on display in relation to the icon store image
			case 'store_icon':
				if (in_array($this->_method, array('GET','HEAD','PUT')))
				{
					$path = _PS_IMG_DIR_.'logo_stores.gif';
					$this->_imgExtension = 'gif';
				}
				else
				{
					$this->setError(405, 'This method is not allowed with general image resources.');
					return false;
				}
				break;
			
			// List the general image types
			case '':
				$this->_xmlOutput .= '<general_image_types>'."\n";
				foreach ($this->_imageTypes['general'] as $generalImageTypeName => $generalImageType)
					$this->_xmlOutput .= '<'.$generalImageTypeName.' xlink:href="'.$this->_wsUrl.$this->_urlSegment[0].'/'.$this->_urlSegment[1].'/'.$generalImageTypeName.'" get="true" put="true" post="false" delete="false" head="true" upload_allowed_mimetypes="'.implode(', ', $this->_acceptedImgMimeTypes).'" />'."\n";
				$this->_xmlOutput .= '</general_image_types>'."\n";
				return true;
				break;
			
			// If the image type does not exist...
			default:
				$this->setErrorDidYouMean(400, 'General image of type "'.$this->_urlSegment[2].'" does not exists', $this->_urlSegment[2], array_keys($this->_imageTypes['general']));
				return false;
		}
		// The general image type is valid, now we try to do action in relation to the method
		switch($this->_method)
		{
			case 'GET':
			case 'HEAD':
				$this->_imgToDisplay = ($alternative_path != '' && file_exists($alternative_path)) ? $alternative_path : $path;
				return true;
				break;
			case 'PUT':
				if ($this->writePostedImageOnDisk($path, NULL, NULL))
				{
					$this->_imgToDisplay = $path;
					return true;
				}
				else
				{
					$this->setError(400, 'Error while copying image to the directory');
					return false;
				}
				break;
		}
	}
	
	/**
	 * Management of normal images (as categories, suppliers, manufacturers and stores)
	 * 
	 * @param string $directory the file path of the root of the images folder type
	 * @return boolean
	 */
	private function manageNormalImages($directory)
	{
		
		// Get available image sizes for the current image type
		$normalImageSizes = ImageType::getImagesTypes($this->_urlSegment[1]);
		$normalImageSizeNames = array();
		foreach ($normalImageSizes as $normalImageSize)
			$normalImageSizeNames[] = $normalImageSize['name'];
		switch ($this->_urlSegment[2])
		{
			// Match the default images
			case 'default':
				$this->_defaultImage = true;
				// Get the language iso code list
				$langList = Language::getIsoIds(true);
				$langs = array();
				$defaultLang = Configuration::get('PS_LANG_DEFAULT');
				foreach ($langList as $lang)
				{
					if ($lang['id_lang'] == $defaultLang)
						$defaultLang = $lang['iso_code'];
					$langs[] = $lang['iso_code'];
				}
				
				
				// Display list of languages
				if($this->_urlSegment[3] == '' && $this->_method == 'GET')
				{
					$this->_xmlOutput .= '<languages>'."\n";
					foreach ($langList as $lang)
						$this->_xmlOutput .= '<language iso="'.$lang['iso_code'].'" xlink:href="'.$this->_wsUrl.$this->_urlSegment[0].'/'.$this->_urlSegment[1].'/'.$this->_urlSegment[2].'/'.$lang['iso_code'].'" get="true" put="true" post="true" delete="true" head="true" upload_allowed_mimetypes="'.implode(', ', $this->_acceptedImgMimeTypes).'" />'."\n";
					$this->_xmlOutput .= '</languages>'."\n";
					return true;
				}
				else
				{
					if ($this->_urlSegment[4] != '')
						$filename = $directory.$this->_urlSegment[3].'-'.$this->_urlSegment[2].'-'.$this->_urlSegment[4].'.jpg';
					else
						$filename = $directory.$this->_urlSegment[3].'.jpg';
					$filename_exists = file_exists($filename);
					return $this->manageNormalImagesCRUD($filename_exists, $filename, $normalImageSizes, $directory);//TODO
				}
				break;
			
			// Display the list of images
			case '':
				// Check if method is allowed
				if ($this->_method != 'GET')
				{
					$this->setError(405, 'This method is not allowed for listing category images.');
					return false;
				}
				$this->_xmlOutput .= '<image_types>'."\n";
				foreach ($normalImageSizes as $imageSize)
					$this->_xmlOutput .= '<image_type id="'.$imageSize['id_image_type'].'" name="'.$imageSize['name'].'" xlink:href="'.$this->_wsUrl.'image_types/'.$imageSize['id_image_type'].'" />'."\n";
				$this->_xmlOutput .= '</image_types>'."\n";
				$this->_xmlOutput .= '<images>'."\n";
				$nodes = scandir($directory);
				foreach ($nodes as $node)
					// avoid too much preg_match...
					if ($node != '.' && $node != '..' && $node != '.svn')
					{
						preg_match('/^(\d)\.jpg*$/Ui', $node, $matches);
						if (isset($matches[1]))
						{
							$id = $matches[1];
							$this->_xmlOutput .= '<image id="'.$id.'" xlink:href="'.$this->_wsUrl.$this->_urlSegment[0].'/'.$this->_urlSegment[1].'/'.$id.'" />'."\n";
						}
					}
				$this->_xmlOutput .= '</images>'."\n";
				return true;
				break;
			
			default:
				// If id is detected
				if (Validate::isUnsignedId($this->_urlSegment[2]))
				{
					$orig_filename = $directory.$this->_urlSegment[2].'.jpg';
					$orig_filename_exists = file_exists($directory.$this->_urlSegment[2].'.jpg');
					
					// If a size was given try to display it
					if ($this->_urlSegment[3] != '')
					{
						// Check the given size
						if (!in_array($this->_urlSegment[3], $normalImageSizeNames))
						{
							$this->setErrorDidYouMean(400, 'This image type does not exist', $this->_urlSegment[3], $normalImageSizeNames);
							return false;
						}
						$filename = $directory.$this->_urlSegment[2].'-'.$this->_urlSegment[3].'.jpg';
						
						// Display the resized specific image
						if (file_exists($filename))
						{
							$this->_imgToDisplay = $filename;
							return true;
						}
						else
						{
							$this->setError(500, 'This image does not exist on disk');
							return false;
						}
					}
					// Management of the original image (GET, PUT, POST, DELETE)
					else
					{
						return $this->manageNormalImagesCRUD($orig_filename_exists, $orig_filename, $normalImageSizes, $directory);
					}
				}
				else
				{
					$this->setError(400, 'The image id is invalid. Please set a valid id or the "default" value');
					return false;
				}
		}
	}
	
	/**
	 * Management of normal images CRUD
	 * 
	 * @param boolean $filename_exists if the filename exists
	 * @param string $filename the image path
	 * @param array $imageSizes The
	 * @param string $directory
	 * @return boolean
	 */
	private function manageNormalImagesCRUD($filename_exists, $filename, $imageSizes, $directory)
	{
		switch ($this->_method)
		{
			// Display the image
			case 'GET':
			case 'HEAD':
				if ($filename_exists)
					$this->_imgToDisplay = $filename;
				else
				{
					$this->setError(500, 'This image does not exist on disk');
					return false;
				}
				break;
			// Modify the image
			case 'PUT':
				if ($filename_exists)
					if ($this->writePostedImageOnDisk($filename, NULL, NULL, $imageSizes, $directory))
					{
						$this->_imgToDisplay = $filename;
						return true;
					}
					else
					{
						$this->setError(500, 'Unable to save this image.');
						return false;
					}
				else
				{
					$this->setError(500, 'This image does not exist on disk');
					return false;
				}
				break;
			// Delete the image
			case 'DELETE':
				if ($filename_exists)
					return $this->deleteImageOnDisk($filename, $imageSizes, $directory);
				else
				{
					$this->setError(500, 'This image does not exist on disk');
					return false;
				}
				break;
			// Add the image
			case 'POST':
				if ($filename_exists)
				{
					$this->setError(400, 'This image already exists. To modify it, please use the PUT method');
					return false;
				}
				else
				{
					if ($this->writePostedImageOnDisk($filename, NULL, NULL, $imageSizes, $directory))
					{
						$this->_imgToDisplay = $filename;
						return true;
					}
					else
					{
						$this->setError(500, 'Unable to save this image.');
						return false;
					}
				}
				break;
			default : 
				$this->setError(405, 'This method is not allowed.');
				return false;
		}
	}
	
	/**
	 * 	Delete the image on disk
	 * 
	 * @param string $filePath the image file path
	 * @param array $imageTypes The differents sizes
	 * @param string $parentPath The parent path
	 * @return boolean
	 */
	private function deleteImageOnDisk($filePath, $imageTypes = NULL, $parentPath = NULL)
	{
		$this->_outputEnabled = false;
		if (file_exists($filePath))
		{
			// delete image on disk
			@unlink($filePath);
			
			// Delete declinated image if needed
			if ($imageTypes)
			{
				foreach ($imageTypes as $imageType)
				{
					if ($this->_defaultImage)
						$declination_path = $parentPath.$this->_urlSegment[3].'-default-'.$imageType['name'].'.jpg';
					else
					$declination_path = $parentPath.$this->_urlSegment[2].'-'.$imageType['name'].'.jpg';
					if (!@unlink($declination_path))
					{
						$this->setError(204);
						return false;
					}
				}
			}
			return true;
		}
		else
		{
			$this->setStatus(204);
			return false;
		}
	}
	
	/**
	 * Write the image on disk
	 * 
	 * @param string $basePath
	 * @param string $newPath
	 * @param int $destWidth
	 * @param int $destHeight
	 * @param array $imageTypes
	 * @param string $parentPath
	 * @return string
	 */
	private function writeImageOnDisk($basePath, $newPath, $destWidth = NULL, $destHeight = NULL, $imageTypes = NULL, $parentPath = NULL)
	{
		list($sourceWidth, $sourceHeight, $type, $attr) = getimagesize($basePath);
		if (!$sourceWidth)
		{
			$this->setError(400, 'Image width was null');
			return false;
		}
		if ($destWidth == NULL) $destWidth = $sourceWidth;
		if ($destHeight == NULL) $destHeight = $sourceHeight;
		switch ($type)
		{
			case 1:
				$sourceImage = imagecreatefromgif($basePath);
				break;
			case 3:
				$sourceImage = imagecreatefrompng($basePath);
				break;
			case 2:
			default:
				$sourceImage = imagecreatefromjpeg($basePath);
				break;
		}
	
		$widthDiff = $destWidth / $sourceWidth;
		$heightDiff = $destHeight / $sourceHeight;
		
		if ($widthDiff > 1 AND $heightDiff > 1)
		{
			$nextWidth = $sourceWidth;
			$nextHeight = $sourceHeight;
		}
		else
		{
			if ((int)(Configuration::get('PS_IMAGE_GENERATION_METHOD')) == 2 OR ((int)(Configuration::get('PS_IMAGE_GENERATION_METHOD')) == 0 AND $widthDiff > $heightDiff))
			{
				$nextHeight = $destHeight;
				$nextWidth = (int)(($sourceWidth * $nextHeight) / $sourceHeight);
				$destWidth = ((int)(Configuration::get('PS_IMAGE_GENERATION_METHOD')) == 0 ? $destWidth : $nextWidth);
			}
			else
			{
				$nextWidth = $destWidth;
				$nextHeight = (int)($sourceHeight * $destWidth / $sourceWidth);
				$destHeight = ((int)(Configuration::get('PS_IMAGE_GENERATION_METHOD')) == 0 ? $destHeight : $nextHeight);
			}
		}
		
		$borderWidth = (int)(($destWidth - $nextWidth) / 2);
		$borderHeight = (int)(($destHeight - $nextHeight) / 2);
		
		$destImage = imagecreatetruecolor($destWidth, $destHeight);
	
		$white = imagecolorallocate($destImage, 255, 255, 255);
		imagefill($destImage, 0, 0, $white);
	
		imagecopyresampled($destImage, $sourceImage, $borderWidth, $borderHeight, 0, 0, $nextWidth, $nextHeight, $sourceWidth, $sourceHeight);
		imagecolortransparent($destImage, $white);
		$flag = false;
		switch ($this->_imgExtension)
		{
			case 'gif':
				$flag = imagegif($destImage, $newPath);
				break;
			case 'png':
				$flag = imagepng($destImage, $newPath, 7);
				break;
			case 'jpeg':
			default:
				$flag = imagejpeg($destImage, $newPath, 90);
				break;
		}
		imagedestroy($destImage);
		if (!$flag)
			return false;
		
		// Write image declinations if needed
		if ($imageTypes)
		{
			foreach ($imageTypes as $imageType)
			{
				if ($this->_defaultImage)
					$declination_path = $parentPath.$this->_urlSegment[3].'-default-'.$imageType['name'].'.jpg';
				else
					$declination_path = $parentPath.$this->_urlSegment[2].'-'.$imageType['name'].'.jpg';
				if (!$this->writeImageOnDisk($basePath, $declination_path, $imageType['width'], $imageType['height']))
				{
					$this->setError(500, 'Unable to save the declination "'.$imageType['name'].'" of this image.');
					return false;
				}
			}
		}
		
		return !$this->hasErrors() ? $newPath : false;
	}
	
	/**
	 * Write the posted image on disk
	 * 
	 * @param string $sreceptionPath
	 * @param int $destWidth
	 * @param int $destHeight
	 * @param array $imageTypes
	 * @param string $parentPath
	 * @return boolean
	 */
	private function writePostedImageOnDisk($receptionPath, $destWidth = NULL, $destHeight = NULL, $imageTypes = NULL, $parentPath = NULL)
	{
		if ($this->_method == 'PUT')
		{
			if (isset($_FILES['image']['tmp_name']) AND $_FILES['image']['tmp_name'])
			{
				$file = $_FILES['image'];
				if ($file['size'] > $this->_imgMaxUploadSize)
				{
					$this->setError(400, 'The image size is too large (maximum allowed is '.($this->_imgMaxUploadSize/1000).' KB)');
					return false;
				}
				// Get mime content type
				$mime_type = false;
				if (Tools::isCallable('finfo_open'))
				{
					$const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;
					$finfo = finfo_open($const);
					$mime_type = finfo_file($finfo, $file['tmp_name']);
					finfo_close($finfo);
				}
				elseif (Tools::isCallable('mime_content_type'))
					$mime_type = mime_content_type($file['tmp_name']);
				elseif (Tools::isCallable('exec'))
					$mime_type = trim(exec('file -b --mime-type '.escapeshellarg($file['tmp_name'])));
				if (empty($mime_type) || $mime_type == 'regular file')
					$mime_type = $file['type'];
				if (($pos = strpos($mime_type, ';')) !== false)
					$mime_type = substr($mime_type, 0, $pos);
				
				// Check mime content type
				if(!$mime_type || !in_array($mime_type, $this->_acceptedImgMimeTypes))
				{
					$this->setError(400, 'This type of image format not recognized, allowed formats are: '.implode('", "', $this->_acceptedImgMimeTypes));
					return false;
				}
				// Check error while uploading
				elseif ($file['error'])
				{
					$this->setError(400, 'Error while uploading image. Please change your server\'s settings');
					return false;
				}
				
				// Try to copy image file to a temporary file
				if (!$tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS') OR !move_uploaded_file($_FILES['image']['tmp_name'], $tmpName))
				{
					$this->setError(400, 'Error while copying image to the temporary directory');
					return false;
				}
				// Try to copy image file to the image directory
				else
				{
					return $this->writeImageOnDisk($tmpName, $receptionPath, $destWidth, $destHeight, $imageTypes, $parentPath);
				}
				unlink($tmpName);
			}
			else
			{
				$this->setError(400, 'Please set an "image" parameter with image data for value');
				return false;
			}
		}
		else
		{
			$this->setError(405, 'Method '.$this->_method.' is not allowed for an image resource');
			return false;
		}
	}
}
