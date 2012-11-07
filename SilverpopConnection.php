<?php

/**
 * @file
 * Contains definition of the SilverpopConnection class.
 */

/**
 * Class definition for Silverpop API connection.
 */
class SilverpopConnection {
  // Silverpop API endpoint URL.
  protected $endpoint;

  // Silverpop API username.
  protected $username;

  // Silverpop API password.
  protected $password;

  // Silverpop API session id.
  protected $sessionID;

  // A log of all calls made with this instance.
  public $sessionLog = array();

  // A log of all <Fault> messages returned through this instance.
  public $faultLog = array();

  // A boolean flag for whether or not to perform transaction logging.
  public $logTransactions = FALSE;

  // A boolean flag for whether or not to perform Silverpop <Fault> logging.
  public $logFaults = FALSE;

  /**
   * Constructor for SilverpopConnection class.
   *
   * @param string $endpoint
   *   the URL to connect to when calling API
   * @param string $username
   *   the username to authenticate with when logging in
   * @param string $password
   *   the password to authenticate with when logging in
   *
   * @return object
   *   a new SilverpopConnection object instance
   */
  public function __construct($endpoint, $username, $password) {
    $this->endpoint = $endpoint;
    $this->username = $username;
    $this->password = $password;
    $this->login();

    return $this;
  }

  /**
   * Public wrapper for making an API call to the Silverpop XMLAPI endpoint.
   *
   * @param string $xml
   *   the name of the API function to execute
   *
   * @return mixed
   *   string: XML response from the Silverpop endpoint
   *   FALSE: failure to connect
   */
  public function call($xml) {
    // Add session id to API calls that require it.
    $url = ($this->sessionID) ? $this->endpoint . ';jsessionid=' . $this->sessionID : $this->endpoint;

    return $this->getXMLResponse($url, $xml);
  }

  /**
   * Send processed XML via curl to Silverpop XMLAPI.
   */
  protected function getXMLResponse($url, $xml) {
    // Initialize curl call.
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: text/xml;charset=UTF-8',
      'Content-Length: ' . strlen($xml),
    ));

    // Perform curl call.
    $call_time = microtime(TRUE);
    $response = curl_exec($ch);
    $response_time = microtime(TRUE);

    // Log all API calls.
    if ($this->logTransactions) {
      $this->sessionLog[] = array(
        'call_time' => date('Y/m/d [g:i:sa]', $call_time),
        'executed_in' => ($response_time - $call_time) . ' seconds',
        'call_xml' => $xml,
        'response_xml' => $response,
      );
    }

    // Log all <Fault> tags.
    if ($this->logFaults) {
      $xml = new DOMDocument();
      $xml->loadXML($response);
      $fault_tags = $xml->getElementsByTagName('FaultString');

      for ($i = 0; $i < $fault_tags->length; $i++) {
        $this->faultLog[] = $fault_tags->item($i)->nodeValue;
      }
    }

    if ($response) {
      curl_close($ch);
    }

    return $response;
  }

  /**
   * Create a login request and retrieve a session id.
   *
   * ID is stored in $this->sessionID for use by all subsequent requests.
   */
  protected function login() {
    $login_response = $this->call('
      <Envelope>
        <Body>
          <Login>
            <USERNAME>' . $this->username . '</USERNAME>
            <PASSWORD>' . $this->password . '</PASSWORD>
          </Login>
        </Body>
      </Envelope>
    ');

    if (!$login_response) {
      // Connection failure (curl returned FALSE).
      throw new SilverpopConnectionException('Could not find the Silverpop XMLAPI at the address "' . $this->endpoint . '".');
    }

    $xml = new DOMDocument();
    $xml->loadXML($login_response);
    $fault_tags = $xml->getElementsByTagName('FaultString');
    $fault_codes = $xml->getElementsByTagName('errorid');
    $id_tag = $xml->getElementsByTagName('SESSIONID');

    // Received a fault response from Silverpop.
    if ($fault_tags->length > 0) {
      $message = $fault_tags->item(0)->nodeValue;
      $code = $fault_codes->item(0)->nodeValue;
      throw new SilverpopConnectionException('Could not connect to the Silverpop XMLAPI. Silverpop says: "' . $message . '".', $code);
    }

    // No fault response and no session ID -- bad endpoint.
    elseif ($id_tag->length == 0) {
      throw new SilverpopConnectionException('Could not find the Silverpop XMLAPI at the address "' . $this->endpoint . '".');
    }

    // Successfully retrieved session ID.
    else {
      $this->sessionID = $id_tag->item(0)->nodeValue;
    }
  }

  /**
   * Send a logout request to Silverpop API.
   */
  protected function logout() {
    $logout_response = $this->call('<Envelope><Body></Logout></Body></Envelope>');
  }
}
