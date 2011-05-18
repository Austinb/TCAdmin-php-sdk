<?php
/**
 * TCAdmin API Interface
 *
 * Provides a cURL based interface with TCAdmin (http://www.tcadmin.com/) to allow
 * some control over servers as well as installing and deleting servers.
 *
 * @author Austin Bischoff <austin@codebeard.com>
 *
 * This file is part of TCAdmin-PHP-SDK.
 *
 * TCAdmin-PHP-SDK is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TCAdmin-PHP-SDK is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with TaskDaemon.  If not, see <http://www.gnu.org/licenses/>.
 */
class TCAdmin {

	/*
	 * Error Constants
	 */
	const ERROR_NONE = 0;
	const ERROR_MISSINGCURL = 1;
	const ERROR_CONNECTSTRING = 2;
	const ERROR_CURLCMDFAILED = 3;
	const ERROR_CURLRESPONSEINVALID = 4;

	/*
	 * Class Constants
	 */
	const RESPONSE_TYPE_XML = 'xml';
	const RESPONSE_TYPE_TEXT = 'text';

	const FIELD_USERNAME = 'tcadmin_username';
	const FIELD_PASSWORD = 'tcadmin_password';
	const FIELD_FUNCTION = 'function';
	const FIELD_RESPONSETYPE = 'response_type';

	const FIELD_CLIENT_PACKAGE_ID = 'client_package_id';
	const FIELD_CLIENT_ID = 'client_id';
	const FIELD_SKIP_PAGE = 'skip_page';

	const FIELD_USER_EMAIL = 'user_email';
	const FIELD_USER_NAME = 'user_name';
	const FIELD_USER_FNAME = 'user_fname';
	const FIELD_USER_LNAME = 'user_lname';
	const FIELD_USER_PASSWORD = 'user_password';

	// Game settings
	const FIELD_GAME_SLOTS = 'game_slots';
	const FIELD_GAME_BRANDED = 'game_branded';
	const FIELD_GAME_PRIVATE = 'game_private';

	// Voice settings
	const FIELD_VOICE_SLOTS = 'voice_slots';
	const FIELD_VOICE_BRANDED = 'voice_branded';
	const FIELD_VOICE_PRIVATE = 'voice_private';

	const CMD_GET_GAMESERVERS = 'GetSupportedGames';
	const CMD_GET_VOICESERVERS = 'GetSupportedVoiceServers';
	const CMD_ADD_SETUP = 'AddPendingSetup';

	const CMD_SUSPEND_SERVICES = 'SuspendGameAndVoiceByBillingID';
	const CMD_UNSUSPEND_SERVICES = 'UnSuspendGameAndVoiceByBillingID';
	const CMD_DELETE_SERVICES = 'DeleteGameAndVoiceByBillingID';

	const CMD_UPDATE_SETTINGS = 'UpdateSettings';
	const CMD_UPDATE_PASSWORD = 'ChangePassword';

	/*
	 * Constants for the defined GET params.
	 */
	const GET_SERVICEID = 'serviceid';
	const GET_SERVICE_DESCSHORT = 'svc_short_desc';
	const GET_RETURNTO = 'returnto';

	/*
	 * Constants used for creating links to services within the TCAdmin interface (control panel).
	 */
	const GET_MVSID = 'mvsid';
	const GET_VVSID = 'vvsid';
	const GET_VOICETYPE = 'voicetype';

	/*
	 * Status settings
	 */
	const SETUP_PENDING = 1;
	const SETUP_COMPLETE = 2;
	const SETUP_ERRORED = 3;

	/*
	 * Billing status settings
	 */
	const BILLING_STATUS_ACTIVE = 1;
	const BILLING_STATUS_SUSPENDED = 2;

	/**
	 * Connection string used for factory construct.
	 *
	 * @var string
	 */
	static public $api_connect_string = false;

	/*
	 * API Settings. Set after __construct
	 */
	protected $api_url = false;
	protected $api_username = false;
	protected $api_password = false;

	/**
	 * The response type sent by the tcadmin server you are querying.
	 *
	 * @var string
	 */
	protected $api_response_type = self::RESPONSE_TYPE_XML;

	/*
	 * Error stuff.  Only used when there is an error.
	 */
	protected $error_no = self::ERROR_NONE;
	protected $error_msg = '';

	protected $timeout = 300;

	/*
	 * Actual GUI settings
	 */
	static public $root_url = null;

	public $gui_root = null;
	public $path_login = 'login.aspx';
	public $path_userhome = 'user_home.aspx';
	public $path_services = 'services.aspx';
	public $path_servicehome = 'service_home.aspx';
	public $path_voiceservers = 'voiceservers.aspx';
	public $path_voiceservicehome = 'vvoiceserver_home.aspx';

	// Create new class with static call.
	public static function factory($timeout=300)
	{
		// Make new instance and return
		return new self(self::$api_connect_string, self::$root_url, $timeout);
	}

	public function __construct($connect_string=null, $root_url=null, $timeout=300)
	{
		if(!function_exists('curl_init'))
		{
			throw new TCAdminException('cURL is not installed.  See: http://www.php.net/manual/en/curl.installation.php.', self::ERROR_MISSINGCURL);
			return false;
		}

		// Parse the connect string to get the info we need.
		$connect = parse_url($connect_string);

		// Check for valid parts.
		if(!isset($connect['host']) || !isset($connect['pass']))
		{
			throw new TCAdminException('Connect string is not set or is incomplete.', self::ERROR_CONNECTSTRING);
			return false;
		}

		// Create the url we are actually connecting to.
		$this->api_url = $connect['scheme'] . '://' . $connect['host'] . $connect['path'] . ((isset($connect['query']))?'?'.$connect['query']:'');

		// Set the RDP username
		$this->api_username = $connect['user'];

		// Set the RDP password
		$this->api_password = $connect['pass'];

		// Set the GUI root
		$this->gui_root = $root_url;

		return $this;
	}

	protected function _remoteCall($data=array())
	{
		// Add the proper data
		$data[self::FIELD_USERNAME] = $this->api_username;
		$data[self::FIELD_PASSWORD] = $this->api_password;
		$data[self::FIELD_RESPONSETYPE] = $this->api_response_type;

		// Init cURL
		$ch = curl_init();

		// Setup the options
		curl_setopt_array($ch, array(
			CURLOPT_URL => $this->api_url,
			CURLOPT_CONNECTTIMEOUT => $this->timeout,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_MAXREDIRS => 4,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data,
		));

		// Now run the command
		if(!$result = curl_exec($ch))
		{
			throw new TCAdminException('Unable to complete cURL request.  Error: '.curl_error($ch), self::ERROR_CURLCMDFAILED);
			return false;
		}

		// Close cURL connection
		curl_close($ch);

		if(($xml = simplexml_load_string($result)) === false)
		{
			throw new TCAdminException('Unable to parse return as XML.', self::ERROR_CURLRESPONSEINVALID);
			return false;
		}

		// Unset some stuff to free some memory
		unset($result, $ch, $data);

		return $xml;
	}

	public function getErrorCode()
	{
		return $this->error_no;
	}

	public function getErrorMsg()
	{
		return $this->error_msg;
	}

	/**
	 * Get the list of supported games
	 */
	public function getSupportedGameServers()
	{
		$data = array(
			self::FIELD_FUNCTION => self::CMD_GET_GAMESERVERS,
		);

		return $this->_remoteCall($data);
	}

	/**
	 * Get the list of supported voice servers
	 */
	public function getSupportedVoiceServers()
	{
		$data = array(
			self::FIELD_FUNCTION => self::CMD_GET_VOICESERVERS,
		);

		return $this->_remoteCall($data);
	}

	/**
	 * Add a new server
	 *
	 * @param array $data
	 */
	public function addService($data=array())
	{
		$data[self::FIELD_FUNCTION] = self::CMD_ADD_SETUP;

		$res = $this->_remoteCall($data);

		// Went ok
		if($res->errorcode == 0)
		{
			return $res;
		}
		else // We hit an error
		{
			$this->error_no = abs($res->errorcode);;
			$this->error_msg = $res->errortext;

			return false;
		}
	}

	/**
	 * Suspend a service
	 *
	 * @param string $package_id
	 */
	public function suspendService($package_id)
	{
		$data = array(
			self::FIELD_FUNCTION => self::CMD_SUSPEND_SERVICES,
			self::FIELD_CLIENT_PACKAGE_ID => $package_id,
		);

		$res = $this->_remoteCall($data);

		// Went ok
		if($res->errorcode == 0)
		{
			return $res;
		}
		else // We hit an error
		{
			$this->error_no = abs($res->errorcode);;
			$this->error_msg = $res->errortext;

			return false;
		}
	}

	/**
	 * Unsuspend a service
	 *
	 * @param string $package_id
	 */
	public function unsuspendService($package_id)
	{
		$data = array(
			self::FIELD_FUNCTION => self::CMD_UNSUSPEND_SERVICES,
			self::FIELD_CLIENT_PACKAGE_ID => $package_id,
		);

		$res = $this->_remoteCall($data);

		// Went ok
		if($res->errorcode == 0)
		{
			return $res;
		}
		else // We hit an error
		{
			$this->error_no = abs($res->errorcode);;
			$this->error_msg = $res->errortext;

			return false;
		}
	}

	/**
	 * Delete a service
	 *
	 * @param string $package_id
	 */
	public function deleteService($package_id)
	{
		$data = array(
			self::FIELD_FUNCTION => self::CMD_DELETE_SERVICES,
			self::FIELD_CLIENT_PACKAGE_ID => $package_id,
		);

		$res = $this->_remoteCall($data);

		// Went ok
		if($res->errorcode == 0)
		{
			return $res;
		}
		else // We hit an error
		{
			$this->error_no = abs($res->errorcode);;
			$this->error_msg = $res->errortext;

			return false;
		}
	}

	/**
	 * Make changed to a service
	 *
	 * @param string $package_id
	 * @param array $data
	 */
	public function updateService($package_id, $data)
	{
		$data[self::FIELD_FUNCTION] = self::CMD_UPDATE_SETTINGS;
		$data[self::FIELD_CLIENT_PACKAGE_ID] = $package_id;

		$res = $this->_remoteCall($data);

		// Went ok
		if($res->errorcode == 0)
		{
			return $res;
		}
		else // We hit an error
		{
			$this->error_no = abs($res->errorcode);;
			$this->error_msg = $res->errortext;

			return false;
		}
	}

	/**
	 * Log a user in using simpletest as a "browser"
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $useragent
	 * @param string $cookie_name The name of the cookie as set by TCAdmin
	 */
	public function login($username, $password, $useragent, $cookie_name)
	{
		$return = false;

		ob_start();
		// Require the simpletest libs
		require_once('simpletest/browser.php');

		// Setup the useragent to use the passed one.
		$agent = 'User-Agent: '.$useragent;

    	$browser = new SimpleBrowser();
    	$browser->useCookies();
    	$browser->addHeader($agent);
		$browser->get($this->getUrlLogin());

		// Set the form stuff.
		$browser->setField('UserName', $username);
		$browser->setField('Password', $password);
		$browser->click('Login');

		/*//<div class="error_message"  >*/

		ob_end_clean();

		if(stristr($browser->getTitle(), 'User Main Menu') !== false)
		{
			$return = array(
				'cookie_value' => $browser->getCurrentCookieValue($cookie_name),
			);
		}


		return $return;
	}

	/**
	 * Return the root url for the GUI
	 */
	public function getUrlRoot()
	{
		return $this->gui_root;
	}

	/**
	 * Return the user home url.
	 */
	public function getUrlLogin()
	{
		return $this->gui_root . $this->path_login;
	}

	/**
	 * Return the user home url.
	 */
	public function getUrlUserHome()
	{
		return $this->gui_root . $this->path_userhome;
	}

	/**
	 * Get the service url for a specific service.
	 *
	 * @param array $args
	 */
	public function getUrlService(Array $args=null)
	{
		$query = '';

		// We have args so lets make the query.
		if(count($args) > 0)
		{
			$query = '?' . http_build_query($args, '', '&');
		}

		return $this->gui_root . $this->path_servicehome . $query;
	}

	/**
	 * Get the service url for a specific voice service.
	 *
	 * @param array $args
	 */
	public function getUrlServiceVoice(Array $args=null)
	{
		$query = '';

		// We have args so lets make the query.
		if(count($args) > 0)
		{
			$query = '?' . http_build_query($args, '', '&');
		}

		return $this->gui_root . $this->path_voiceservicehome . $query;
	}
}

/**
 * Thrown when TCAdmin returns an exception.
 */
class TCAdminException extends Exception
{
	public function __construct($message, $code=0)
	{
		return parent::__construct('TCAdmin: '. $message, $code);
	}
}