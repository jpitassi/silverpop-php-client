<?php

/**
 * @file
 * Definition for SilverpopAPI class and related functions.
 */

// Make sure connection class exists.
require_once 'SilverpopConnection.inc';

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
  public function __construct() {
    $this->connection = new SilverpopConnection();

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
   * Return the session log from the connection.
   */
  public function getSessionLog() {
    return $this->connection->session_log;
  }

  /**
   * Return the fault log from the connection.
   */
  public function getFaultLog() {
    return $this->connection->fault_log;
  }

  /**
   * Build the XML for a Silverpop API call.
   *
   * @return string
   *   XML data packet, ready to be sent to endpoint
   */
  protected function buildEnvelope() {
    // Build DOM elements.
    $xml = new DOMDocument();
    $envelope = $xml->createElement('Envelope');
    $body = $xml->createElement('Body');
    $envelope->appendChild($body);
    $xml->appendChild($envelope);

    // Wrap all API calls in envelope.
    foreach ($this->callStack as $call) {
      $function_body = $xml->createDocumentFragment();
      switch (gettype($call['data'])) {
        // PHP Array.
        case 'array':
          // No need to build a wrapper for this -- arrayToXML will add one.
          $call_xml = $this->arrayToXML($xml, $body, $call['function'], $call['data']);
          break;

        // Raw XML string.
        case 'string':
          // Need a wrapper with function name for each call.
          $function_wrapper = $xml->createElement($call['function']);
          $function_wrapper->appendChild($function_body);
          $body->appendChild($function_wrapper);
          $call_xml = $call['data'];
          break;

        default:
          throw new SilverpopDataException('SilverpopAPI::buildEnvelope(): Cannot convert type "' . gettype($call['data']) . '" to XML.');
          break;
      }

      $function_body->appendXML($call_xml);
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
   * @param string $branch
   *   a <TAGNAME> for the current DOM node being processed
   * @param mixed $child
   *   the child nodes of the current element
   */
  protected function arrayToXML(&$tree, &$parent, $branch, $child) {
    // Integer indexes implies multiple tags of the same name.
    if (is_int($branch)) {
      foreach ($child as $field => $value) {
        return $this->arrayToXML($tree, $parent, $field, $value);
      }
    }
    // Create a new DOM element.
    $element = $tree->createElement($branch);

    // Deal with child nodes differently, based on their type.
    switch (gettype($child)) {
      // A string is just a normal text node.
      case 'string':
        $value = $tree->createCDATASection($child);
        $element->appendChild($value);
        break;

      // An array is an array of child nodes.
      case 'array':
        foreach ($child as $field => $value) {
          $this->arrayToXML($tree, $element, $field, $value);
        }
        break;

      // An object passed here can have XML attributes & children.
      case 'object':
        // Element has attribute key=>values.
        if (isset($child->attributes)) {
          if (!is_array($child->attributes)) {
            throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Cannot convert type "' . gettype($child->attributes) . '" to array.');
          }
          foreach ($child->attributes as $attribute_key => $attribute_value) {
            $element->setAttribute($attribute_key, $attribute_value);
          }
        }

        // Element has a text value.
        if (isset($child->value)) {
          if (!is_string($child->value)) {
            throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Cannot convert type "' . gettype($child->value) . '" to string.');
          }
          $value = $tree->createCDATASection($child->value);
          $element->appendChild($value);
        }

        // Element has children.
        if (isset($child->children)) {
          if (!is_array($child->children)) {
            throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Cannot convert type "' . gettype($child->children) . '" to array.');
          }
          foreach ($child->children as $field => $value) {
            $this->arrayToXML($tree, $element, $field, $value);
          }
        }
        break;

      default:
        // Any other data cannot be converted to XML.
        throw new SilverpopDataException('SilverpopAPI::arrayToXML(): Cannot convert type "' . gettype($child) . '" to XML.');
        break;
    }

    // Add element to tree.
    $parent->appendChild($element);
  }
}
