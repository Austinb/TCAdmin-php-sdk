<?php
/**
 * TCAdmin API Interface
 *
 * For use with TCAdmin - http://www.tcadmin.com/
 *
 * @author Austin Bischoff <austin(dot)bischoff(at)gmail(dot)com>
 */
class TCAdmin {

	static public $connect_string = false;

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

	const CMD_GET_GAMESERVERS = 'GetSupportedGames';
	const CMD_GET_VOICESERVERS = 'GetSupportedVoiceServers';
	const CMD_ADD_SETUP = 'AddPendingSetup';

	const CMD_UPDATE_SETTINGS = 'UpdateSettings';
	const CMD_UPDATE_PASSWORD = 'ChangePassword';

	/*
	 * Status settings
	 */
	const SETUP_PENDING = 1;
	const SETUP_COMPLETE = 2;
	const SETUP_ERRORED = 3;

	/*
	 * RDP Settings
	 */
	protected $rdp_url = false;
	protected $rdp_username = false;
	protected $rdp_password = false;

	protected $error_no = self::ERROR_NONE;
	protected $error_msg = '';

	/**
	 * The response type sent by the tcadmin server you are querying.
	 *
	 * @var string
	 */
	protected $rdp_response_type = self::RESPONSE_TYPE_XML;

	// Create new class with static call.
	public static function factory()
	{
		// Make new instance and return
		return new self(self::$connect_string);
	}

	public function __construct($connect_string=null)
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
		$this->rdp_url =  $connect['scheme'] . '://' . $connect['host'] . $connect['path'] . ((isset($connect['query']))?$connect['query']:'');

		// Set the RDP username
		$this->rdp_username = $connect['user'];

		// Set the RDP password
		$this->rdp_password = $connect['pass'];

		return $this;
	}

	protected function _remoteCall($data=array())
	{
		// Add the proper data
		$data[self::FIELD_USERNAME] = $this->rdp_username;
		$data[self::FIELD_PASSWORD] = $this->rdp_password;
		$data[self::FIELD_RESPONSETYPE] = $this->rdp_response_type;

		// Init cURL
		$ch = curl_init();

		// Setup the options
		curl_setopt_array($ch, array(
			CURLOPT_URL => $this->rdp_url,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_MAXREDIRS => 4,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $data,
		));

		// Now run the command
		if(!$result = curl_exec($ch))
		{
			throw new TCAdminException('Unable to complete cURL request.  Error: '.curl_error($ch), self::ERROR_CURLCMDFAILED);
			return false;
		}

		curl_close($ch);

		if(($xml = simplexml_load_string($result)) === false)
		{
			throw new TCAdminException('Unable to parse return as XML.', self::ERROR_CURLRESPONSEINVALID);
			return false;
		}

		return $xml;

		/*// Now lets try to load up the return as xml.
		$dom = new DOMDocument();

		$dom->preserveWhiteSpace = false;

		if(!$dom->loadXML($result, LIBXML_NOBLANKS))
		{
			throw new TCAdminException('Unable to parse return as XML.', self::ERROR_CURLRESPONSEINVALID);
			return false;
		}

		$xpath = new DOMXPath($dom);

		echo $dom->saveXML();*/
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
	public function addServer($data=array())
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
			$this->error_no = $res->errorcode;
			$this->error_msg = $res->errortext;

			return false;
		}
	}
}

/**
 * Thrown when TCAdmin returns an exception.
 */
class TCAdminException extends Exception
{
	public function __construct($message, $code=TCAdmin::ERROR_UNKNOWN)
	{
		return parent::__construct('TCAdmin: '. $message, $code);
	}
}