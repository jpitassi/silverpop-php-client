<?php

/**
 * @file
 * Definition for SilverpopAPI class and related functions.
 */

// Make sure connection class exists.
require_once 'SilverpopConnection.php';

// Define an exception class for invalid connections.
class SilverpopConnectionException extends Exception {}
// Define an exception class for invalid XML data.
class SilverpopDataException extends Exception {}

/**
 * Class definition for Silverpop XML API.
 */
class SilverpopAPI {
  // An array of XML API calls to perfom on execute()
  protected $callStack;

  // The SilverpopConnection instance to connect with.
  protected $connection;

  /**
   * Constructor for SilverpopAPI objects.
   */
  public function __construct($endpoint, $username, $password) {
    $this->connection = new SilverpopConnection($endpoint, $username, $password);

    return $this;
  }

  /**
   * Build API data to pass as XML in request.
   *
   * @param string $function
   *   the name of the XMLAPI function to call
   * @param mixed $data
   *   string: a string of raw XML data
   *   array: an associative, multidimensional array of API data
   * @param boolean $rebuild
   *   TRUE: build a new query from scratch
   *   FALSE: append API call to current envelope
   *
   * @return SilverpopAPI
   *   this SilverpopAPI instance
   */
  public function build($function, $data, $rebuild = FALSE) {
    if ($rebuild === TRUE) {
      $this->rebuild();
    }

    $this->callStack[] = array(
      'function' => $function,
      'data' => $data,
    );

    return $this;
  }

  /**
   * Flush stored API calls without executing them.
   */
  public function rebuild() {
    $this->callStack = array();
  }

  /**
   * Perform API call(s) and return response.
   *
   * @return mixed
   *   string: a string of XML response
   *   FALSE: API call failed
   */
  public function execute() {
    $response = $this->connection->call($this->buildEnvelope());
    $this->rebuild();
    return $response;
  }

  /**
   * Disables logging of API calls.
   */
  public function enableLogging() {
    $this->connection->logTransactions = TRUE;
    $this->connection->logFaults = TRUE;
  }

  /**
   * Return the session log from the connection.
   */
  public function getSessionLog() {
    return $this->connection->sessionLog;
  }

  /**
   * Return the fault log from the connection.
   */
  public function getFaultLog() {
    return $this->connection->faultLog;
  }

  /**
   * Build the XML for a Silverpop API call.
   *
   * @return string
   *   XML data packet, ready to be sent to endpoint
   */
  public function buildEnvelope() {
    // Build DOM elements.
    $xml = new DOMDocument();
    $envelope = $xml->createElement('Envelope');
    $body = $xml->createElement('Body');
    $envelope->appendChild($body);
    $xml->appendChild($envelope);

    // Wrap all API calls in envelope.
    foreach ($this->callStack as $call) {
      switch (gettype($call['data'])) {
        // PHP Array.
        case 'array':
          // No need to build a wrapper for this -- arrayToXML will add one.
          SilverpopAPI::arrayToXML($xml, $body, $call['function'], $call['data']);
          break;

        // Raw XML string.
        case 'string':
          // Need a wrapper with function name for each call.
          $function_wrapper = $xml->createElement($call['function']);
          $function_body = $xml->createDocumentFragment();
          $function_body->appendXML($call['data']);
          $function_wrapper->appendChild($function_body);
          $body->appendChild($function_wrapper);
          break;

        default:
          throw new SilverpopDataException('SilverpopAPI::buildEnvelope(): Cannot convert type "' . gettype($call['data']) . '" to XML.');
          break;
      }
    }

    // Return envelope as a string of XML.
    return $xml->saveXML();
  }

  /**
   * Recursively process an array into a XML tree.
   *
   * @param DOMDocument &$tree
   *   a reference to the DOMDocument being constructed
   * @param DOMElement &$parent
   *   a reference to the current DOM node being processed
   * @param mixed $branch
   *   string: a <TAGNAME> for the current DOM node being processed
   *   integer: if an integer, the function will skip to the next array level
   * @param mixed $child
   *   array: the child nodes of the current element
   *   string: a string value to output between <TAGNAME></TAGNAME>
   *   object: a complex tag with possible attributes/values/children
   */
  public static function arrayToXML(&$tree, &$parent, $branch, $child) {
    // Throw exceptions on invalid data tree/parent types.
    if (!is_object($tree) || get_class($tree) != 'DOMDocument') {
      throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Tree is not a valid DOMDocument.');
    }
    if (!is_object($parent) || get_class($parent) != 'DOMElement') {
      throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Parent is not a valid DOMElement.');
    }

    // Branches can only be strings or integers.
    switch (gettype($branch)) {
      // String branches are normal <TAGNAME> elements.
      case 'string':
        $element = $tree->createElement($branch);
        break;

      // Integer branches skip a level.
      case 'integer':
        if (!is_array($child)) {
          throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Could not convert child type "' . gettype($child) . '" to XML.');
        }
        else {
          foreach ($child as $new_branch => $new_child) {
            return SilverpopAPI::arrayToXML($tree, $parent, $new_branch, $new_child);
          }
        }
        break;

      default:
        throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Could not convert branch type "' . gettype($branch) . '" to XML.');
        break;
    }

    switch (gettype($child)) {
      case 'array':
        foreach ($child as $tagname => $node) {
          SilverpopAPI::arrayToXML($tree, $element, $tagname, $node);
        }
        break;

      // An object passed here can have XML attributes & children.
      case 'object':
        // Element has attribute key=>values.
        if (isset($child->attributes)) {
          if (!is_array($child->attributes)) {
            throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Cannot convert attributes type "' . gettype($child->attributes) . '" to array.');
          }
          foreach ($child->attributes as $attribute_key => $attribute_value) {
            $element->setAttribute($attribute_key, $attribute_value);
          }
        }

        // Element has a text value.
        if (isset($child->value)) {
          // Caste values to strings.
          $child->value = (string) $child->value;
          $value = $tree->createTextNode($child->value);
          $element->appendChild($value);
        }

        // Element has children.
        if (isset($child->children)) {
          if (!is_array($child->children)) {
            throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Cannot convert type "' . gettype($child->children) . '" to array.');
          }
          foreach ($child->children as $field => $value) {
            SilverpopAPI::arrayToXML($tree, $element, $field, $value);
          }
        }
        break;

      // Convert any other data type to string.
      default:
        $child = (string) $child;
        $value = $tree->createTextNode($child);
        $element->appendChild($value);
        break;
    }

    $parent->appendChild($element);
  }
}
