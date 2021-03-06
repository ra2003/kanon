<?php
/**
 * SVN: $Id$
 */
// header('X-XRDS-Location: http://'.$_SERVER['SERVER_NAME'].$this->rel('OpenIDXRDS'));
// echo '<meta http-equiv="X-XRDS-Location" content="http://'.$_SERVER['SERVER_NAME'].$this->rel('OpenIDXRDS').'" />';

class openidUserIdentity extends userIdentityPrototype {

	protected $_openidIdentity = '';
	protected $_attributes = array();

	public function __construct($openidIdentity) {
		$this->_openidIdentity = $openidIdentity;
	}

	protected function authenticateOpenId($openidIdentity) {
		// 3rd-party library: http://gitorious.org/lightopenid
		// Required: PHP 5, curl
		$openid = new LightOpenID;
		$openid->required = array(
			'namePerson/friendly', // nickname 
			'contact/email' // email
		);
		$openid->optional = array('namePerson/first');
		if (isset($_GET['openid_mode'])) {
			$result = $openid->validate();
			$this->_openidIdentity = $openid->identity;
			$this->_attributes = $openid->getAttributes();
			return $result;
		}
		$openid->identity = $openidIdentity;
		header('Location: ' . $openid->authUrl());
		exit;
	}

	/**
	 * @return boolean whether authentication succeeds
	 */
	public function authenticate() {
		$openid = $this->_openidIdentity;
		if (!$this->authenticateOpenId($openid)) {
			throw new authException('Invalid OpenID');
		}
		$openid = $this->_openidIdentity;
		$users = user::getCollection(); //modelCollection::getInstance('registeredUser');
		$openids = modelCollection::getInstance('userOpenid');
		$result = $users->select($openids, $openids->openid->is($this->_openidIdentity))->fetch();
		if (!$result) {
			// throw new authException('OpenID "'.$this->_openidIdentity.'" not registered', authException::ERROR_NOT_REGISTERED);
			// autocreate:
			$user = new registeredUser();
			//$user->save();
			$userOpenid = new userOpenid();
			$userOpenid->userId = $user->id;
			$userOpenid->openid = $this->_openidIdentity;
			//$userOpenid->save();
		} else {
			list($user, $userOpenid) = $result;
			$this->_isRegistered = true;
		}
		/* if (!$user->password->equals($this->_password)){
		  throw new authException('Invalid password', authException::ERROR_PASSWORD_INVALID);
		  } */
		$this->_user = $user;
		$this->_identityModels['openid'][$this->_openidIdentity] = $userOpenid;
		if (isset($this->_attributes['contact/email'])) {
			$email = $this->_attributes['contact/email'];
			if (!isset($this->_identityModels['email'][$email])) {
				$userEmail = new userEmail();
				$userEmail->email = $email;
				$userEmail->userId = $user->id;
				$this->_identityModels['email'][$email] = $userEmail;
			}
		}
		//var_dump($this->_attributes);
		return true;
	}

	/**
	 * @return mixed a value that uniquely represents the identity
	 */
	public function getId() {
		return $this->_openidIdentity;
	}

	/**
	 * @return string display name
	 */
	public function getName() {
		return $this->getId();
	}

	public function register() {
		$userOpenid = new userOpenid();
		$userOpenid->userId = $this->_user->id;
		$userOpenid->openid = $this->_openidIdentity;
		$userOpenid->save();
	}

}