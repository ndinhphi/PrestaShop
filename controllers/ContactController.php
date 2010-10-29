<?php

class ContactControllerCore extends FrontController
{
	public function __construct()
	{
		$this->ssl = true;
	
		parent::__construct();
	}
	public function preProcess()
	{
		parent::preProcess();

		if ($this->cookie->isLogged())
		{
			$this->smarty->assign('isLogged', 1);
			$customer = new Customer(intval($this->cookie->id_customer));
			if (!Validate::isLoadedObject($customer))
				die(Tools::displayError('customer not found'));
			$products = array();
			$orders = array();
			$getOrders = Db::getInstance()->ExecuteS('
				SELECT id_order 
				FROM '._DB_PREFIX_.'orders 
				WHERE id_customer = '.(int)$customer->id.' ORDER BY date_add');
			foreach ($getOrders as $row)
			{
				$order = new Order($row['id_order']);
				$date = explode(' ', $order->date_add);
				$orders[$row['id_order']] = Tools::displayDate($date[0], $this->cookie->id_lang);
				$tmp = $order->getProducts();
				foreach ($tmp as $key => $val)
					$products[$val['product_id']] = $val['product_name'];
			}
			
			$orderList = '';
			foreach ($orders as $key => $val)
				$orderList .= '<option value="'.$key.'" '.(intval(Tools::getValue('id_order')) == $key ? 'selected' : '').' >'.$key.' -- '.$val.'</option>';
			$orderedProductList = '';
			
			foreach ($products as $key => $val)
				$orderedProductList .= '<option value="'.$key.'" '.(intval(Tools::getValue('id_product')) == $key ? 'selected' : '').' >'.$val.'</option>';
			$this->smarty->assign('orderList', $orderList);
			$this->smarty->assign('orderedProductList', $orderedProductList);
		}

		if (Tools::isSubmit('submitMessage'))
		{
			$fileAttachment = NULL;
			if (isset($_FILES['fileUpload']['name']) AND !empty($_FILES['fileUpload']['name']) AND !empty($_FILES['fileUpload']['tmp_name']))
			{
				$extension = array('.txt', '.rtf', '.doc', '.docx', '.pdf', '.zip', '.png', '.jpeg', '.gif', '.jpg');
				$filename = uniqid().substr($_FILES['fileUpload']['name'], -5);
				$fileAttachment['content'] = file_get_contents($_FILES['fileUpload']['tmp_name']);
				$fileAttachment['name'] = $_FILES['fileUpload']['name'];
				$fileAttachment['mime'] = $_FILES['fileUpload']['type'];
			}
			$message = Tools::htmlentitiesUTF8(Tools::getValue('message'));
			if (!($from = trim(Tools::getValue('from'))) OR !Validate::isEmail($from))
				$this->errors[] = Tools::displayError('invalid e-mail address');
			elseif (!($message = nl2br2($message)))
				$this->errors[] = Tools::displayError('message cannot be blank');
			elseif (!Validate::isMessage($message))
				$this->errors[] = Tools::displayError('invalid message');
			elseif (!($id_contact = intval(Tools::getValue('id_contact'))) OR !(Validate::isLoadedObject($contact = new Contact(intval($id_contact), intval($this->cookie->id_lang)))))
				$this->errors[] = Tools::displayError('please select a subject in the list');
			elseif (!empty($_FILES['fileUpload']['name']) AND $_FILES['fileUpload']['error'] != 0)
				$this->errors[] = Tools::displayError('An error occurred during the file upload');
			elseif (!empty($_FILES['fileUpload']['name']) AND !in_array(substr($_FILES['fileUpload']['name'], -4), $extension) AND !in_array(substr($_FILES['fileUpload']['name'], -5), $extension))
				$this->errors[] = Tools::displayError('Bad file extension');
			else
			{
				if (intval($this->cookie->id_customer))
					$customer = new Customer(intval($this->cookie->id_customer));
				else
				{
					$customer = new Customer();
					$customer->getByEmail($from);
				}
				
				$contact = new Contact($id_contact, $this->cookie->id_lang);

				if (!((
						$id_customer_thread = (int)Tools::getValue('id_customer_thread')
						AND (int)Db::getInstance()->getValue('
						SELECT cm.id_customer_thread FROM '._DB_PREFIX_.'customer_thread cm
						WHERE cm.id_customer_thread = '.(int)$id_customer_thread.' AND token = \''.pSQL(Tools::getValue('token')).'\'')
					) OR (
						$id_customer_thread = (int)Db::getInstance()->getValue('
						SELECT cm.id_customer_thread FROM '._DB_PREFIX_.'customer_thread cm
						WHERE cm.email = \''.pSQL($from).'\' AND cm.id_order = '.intval(Tools::getValue('id_order')).'')
					)))
				{
					$fields = Db::getInstance()->ExecuteS('
					SELECT cm.id_customer_thread, cm.id_contact, cm.id_customer, cm.id_order, cm.id_product, cm.email
					FROM '._DB_PREFIX_.'customer_thread cm
					WHERE email = \''.pSQL($from).'\' AND ('.
						($customer->id ? 'id_customer = '.intval($customer->id).' OR ' : '').'
						id_order = '.intval(Tools::getValue('id_order')).')');
					$score = 0;
					foreach ($fields as $key => $row)
					{
						$tmp = 0;
						if ((int)$row['id_customer'] AND $row['id_customer'] != $customer->id AND $row['email'] != $from)
							continue;
						if ($row['id_order'] != 0 AND Tools::getValue('id_order') != $row['id_order'])
							continue;
						if ($row['email'] == $from)
							$tmp += 4;
						if ($row['id_contact'] == $id_contact)
							$tmp++;
						if (Tools::getValue('id_product') != 0 AND $row['id_product'] ==  Tools::getValue('id_product'))
							$tmp += 2;
						if ($tmp >= 5 AND $tmp >= $score)
						{
							$score = $tmp;
							$id_customer_thread = $row['id_customer_thread'];
						}
					}
				}
				$old_message = Db::getInstance()->getValue('
					SELECT cm.message FROM '._DB_PREFIX_.'customer_message cm
					WHERE cm.id_customer_thread = '.intval($id_customer_thread).'
					ORDER BY date_add DESC');
				if ($old_message == htmlentities($message, ENT_COMPAT, 'UTF-8'))
				{
					$this->smarty->assign('alreadySent', 1);
					$contact->email = '';
					$contact->customer_service = 0;
				}
				if (!empty($contact->email))
				{
					if (Mail::Send(intval($this->cookie->id_lang), 'contact', Mail::l('Message from contact form'), array('{email}' => $from, '{message}' => stripslashes($message)), $contact->email, $contact->name, $from, (intval($this->cookie->id_customer) ? $customer->firstname.' '.$customer->lastname : $from), $fileAttachment)
						AND Mail::Send(intval($this->cookie->id_lang), 'contact_form', Mail::l('Your message has been correctly sent'), array('{message}' => stripslashes($message)), $from))
						$this->smarty->assign('confirmation', 1);
					else
						$this->errors[] = Tools::displayError('an error occurred while sending message');
				}
				
				if ($contact->customer_service)
				{
					if ((int)$id_customer_thread)
					{
						$ct = new CustomerThread($id_customer_thread);
						$ct->status = 'open';
						$ct->id_lang = (int)$this->cookie->id_lang;
						$ct->id_contact = intval($id_contact);
						if ($id_order = (int)Tools::getValue('id_order'))
							$ct->id_order = $id_order;
						if ($id_product = (int)Tools::getValue('id_product'))
							$ct->id_product = $id_product;
						$ct->update();
					}
					else
					{
						$ct = new CustomerThread();
						if (isset($customer->id))
							$ct->id_customer = intval($customer->id);
						if ($id_order = (int)Tools::getValue('id_order'))
							$ct->id_order = $id_order;
						if ($id_product = (int)Tools::getValue('id_product'))
							$ct->id_product = $id_product;
						$ct->id_contact = intval($id_contact);
						$ct->id_lang = (int)$this->cookie->id_lang;
						$ct->email = $from;
						$ct->status = 'open';
						$ct->token = Tools::passwdGen(12);
						$ct->add();
					}
					
					if ($ct->id)
					{
						$cm = new CustomerMessage();
						$cm->id_customer_thread = $ct->id;
						$cm->message = htmlentities($message, ENT_COMPAT, 'UTF-8');
						if (isset($filename) AND rename($_FILES['fileUpload']['tmp_name'], _PS_MODULE_DIR_.'../upload/'.$filename))
							$cm->file_name = $filename;
						$cm->ip_address = ip2long($_SERVER['REMOTE_ADDR']);
						$cm->user_agent = $_SERVER['HTTP_USER_AGENT'];
						if ($cm->add())
						{
							if (empty($contact->email))
								Mail::Send(intval($this->cookie->id_lang), 'contact_form', Mail::l('Your message has been correctly sent'), array('{message}' => stripslashes($message)), $from);
							$this->smarty->assign('confirmation', 1);
						}
						else
							$this->errors[] = Tools::displayError('an error occurred while sending message');
					}
					else
						$this->errors[] = Tools::displayError('an error occurred while sending message');
				}
				if (count($this->errors) > 1)
					array_unique($this->errors);
			}
		}
	}
	
	public function setMedia()
	{
		parent::setMedia();
		Tools::addCSS(_THEME_CSS_DIR_.'contact-form.css');
	}
	
	public function process()
	{
		parent::process();

		$email = Tools::safeOutput(Tools::getValue('from', ((isset($this->cookie) AND isset($this->cookie->email) AND Validate::isEmail($this->cookie->email)) ? $this->cookie->email : '')));
		$this->smarty->assign(array(
			'errors' => $this->errors,
			'email' => $email,
			'fileupload' => Configuration::get('PS_CUSTOMER_SERVICE_FILE_UPLOAD')
		));


		if ($id_customer_thread = (int)Tools::getValue('id_customer_thread') AND $token = Tools::getValue('token'))
		{
			$customerThread = Db::getInstance()->getRow('
			SELECT cm.* FROM '._DB_PREFIX_.'customer_thread cm
			WHERE cm.id_customer_thread = '.(int)$id_customer_thread.' AND token = \''.pSQL($token).'\'');
			$this->smarty->assign('customerThread', $customerThread);
		}
		
		$this->smarty->assign('contacts', Contact::getContacts(intval($this->cookie->id_lang)));
	}
	
	public function displayContent()
	{
		$_POST = array_merge($_POST, $_GET);
		parent::displayContent();
		$this->smarty->display(_PS_THEME_DIR_.'contact-form.tpl');
	}
}