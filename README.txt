silverpop-php-client
====================

A PHP client for the Silverpop XML API. Provides the SilverpopAPI and
SilverpopConnection classes for handling the authentication and transaction of
XML API calls to the Silverpop endpoint.


I. Setup
========

  To use the API client, simply include the SilverpopAPI and SilverpopConnection
  classes in your PHP script. To connect to the Silverpop endpoint and start
  making XML calls, create an instance of the SilverpopAPI class.

  A common usage of the SilverpopAPI class would be:
  <?php

  define('SILVERPOP_ENDPOINT', 'http://api1.silverpop.com/XMLAPI');
  define('SILVERPOP_USERNAME', 'my_account@my_company.com');
  define('SILVERPOP_PASSWORD', 'mYp@$$w*rD');

  try {
    $silverpop_api = new SilverpopAPI(
      SILVERPOP_ENDPOINT,
      SILVERPOP_USERNAME,
      SILVERPOP_PASSWORD
    );

    // Make some API calls here...
  }
  catch (SilverpopConnectionException $e) {
    // Handle connection exceptions.
  }
  catch (SilverpopDataException $e) {
    // Handle malformed XML exceptions.
  }

  ?>


II. Usage
=========

  There are two ways to build a XML API call using the SilverpopAPI client. You
  can pass raw XML strings to the SilverpopAPI::build() function, or you can
  use arrays of key => value pairs and let the SilverpopAPI class generate
  XML output for you.

  II.A. Building XML Calls using a String
  =======================================

    The first and easiest way to build a API call is by just passing a string
    of XML to the method SilverpopAPI::build(), like so:

    <?php

    $xml = '<LIST_ID>85628</LIST_ID>
            <CREATED_FROM>1</CREATED_FROM>
            <COLUMN>
              <NAME>Customer Id</NAME>
              <VALUE>123-45-6789</VALUE>
            </COLUMN>
            <COLUMN>
              <NAME>EMAIL</NAME>
              <VALUE>somebody@domain.com</VALUE>
            </COLUMN>
            <COLUMN>
              <NAME>Fname</NAME>
              <VALUE>John</VALUE>
            </COLUMN>';

    $silverpop_api->build('AddRecipient', $xml);
    $response = $silverpop_api->execute();
    // Print XML response to screen.
    print $response;

    ?>

    SilverpopAPI::execute() returns a string of XML response data from
    Silverpop that can be subsequently used in other API calls, or can be logged
    or reported as appropriate. Note that SilverpopAPI::build() can be called
    multiple times to pack several API function invocations into one HTTP call.
    However, once SilverpopAPI::execute() is called, the storage of all data
    passed to build() is sent to Silverpop, and all the saved data is purged.
    Also notice that passing 'AddRecipient' as the first parameter of build()
    makes wrapping the API call in '<AddRecipient></AddRecipient>' redundant,
    and will cause your API call to fail.


  II.B. Building XML Calls using Key => Value Pairs
  =================================================

    With this client you also have the advantage of auto-generating well-formed
    XML output to send to Silverpop using only PHP arrays, objects, and strings.
    This method can save the user lots of time and space, especially when
    perfoming lengthy XML calls. This method is almost certainly better for
    larger data sets, as you can quickly convert the data from a PHP array to
    a format digestable by Silverpop::_arrayToXML(). However, complex XML
    hierarchies/attributes can be difficult to achieve.

    <?php

    $response = $silverpop_api->build('InsertUpdateRelationalTable', array(
      'TABLE_ID' => '86767',
      'ROWS' => array(
        0 => array('ROW' => array(
          0 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Record Id'),
            'value' => 'GHbjh73643hsdiy',
          )),
          1 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Purchase Date'),
            'value' => '01/09/1975',
          )),
          2 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Product Id'),
            'value' => '123454',
          )),
        )),
        1 => array('ROW' => array(
          0 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Record Id'),
            'value' => 'WStfh73643hsdgw',
          )),
          1 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Purchase Date'),
            'value' => '02/11/1980',
          )),
          2 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Product Id'),
            'value' => '45789',
          )),
        )),
      ),
    ))->execute();

    ?>

    Making sure the data array is perfectly formatted is crucial in getting the
    correct XML output. The SilverpopAPI class with throw a
    SilverpopDataException if your XML data input doesn't match the conventions
    established in SilverpopAPI::_arrayToXML(). Here are a couple short examples
    of how to achieve certain XML results using arrays and objects:

    <?php

    // If we want to generate XML that looks like this:
    // <Envelope>
    //   <Body>
    //     <Login>
    //       <USERNAME>some_username@somecompany.com</USERNAME>
    //       <PASSWORD>somepassword</PASSWORD>
    //     </Login>
    //   </Body>
    // </Envelope>
    //
    // ... We can just use standard key => value pairs.

    $result = $silverpop_api->build('Login', array(
      'USERNAME' => 'some_username@somecompany.com',
      'PASSWORD' => 'somepassword',
    ))->execute();

    // Having multiple tags of the same name requires using numeric keys.
    // <Envelope>
    //   <Body>
    //     <JoinTable>
    //       <MAP_FIELD>
    //         <LIST_FIELD>ItemID</LIST_FIELD>
    //         <TABLE_FIELD>ItemID</TABLE_FIELD>
    //       </MAP_FIELD>
    //       <MAP_FIELD>
    //         <LIST_FIELD>PurchPrice</LIST_FIELD>
    //         <TABLE_FIELD>PurchasePrice</TABLE_FIELD>
    //       </MAP_FIELD>
    //     </JoinTable>
    //   </Body>
    // </Envelope>
    //
    // ... Numeric keys are ignored by _arrayToXML(), so they can be used
    // to generate multiple tags with the same name (which wouldn't be possible
    // using just associative arrays in PHP).

    $result = $silverpop_api->build('JoinTable', array(
      0 => array(
        'MAP_FIELD' => array(
          'LIST_FIELD' => 'ItemId',
          'TABLE_FIELD' => 'ItemId',
        ),
      )),
      1 => array(
        'MAP_FIELD' => array(
          'LIST_FIELD' => 'PurchPrice',
          'TABLE_FIELD' => 'PurchasePrice',
        ),
      ))
    ))->execute();

    // If you want to use more complex XML tags, such as tags with attributes
    // as well as children, you will need to use a stdClass PHP object.
    // <Envelope>
    //   <Body>
    //     <InsertUpdateRelationalTable>
    //       <TABLE_ID>86767</TABLE_ID>
    //       <ROWS>
    //         <ROW>
    //           <COLUMN name="Record Id"><![CDATA[GHbjh73643hsdiy]]></COLUMN>
    //           <COLUMN name="Purchase Date"><![CDATA[01/09/1975]]></COLUMN>
    //           <COLUMN name="Product Id"><![CDATA[123454]]></COLUMN>
    //         </ROW>
    //         <ROW>
    //           <COLUMN name="Record Id"><![CDATA[WStfh73643hsdgw]]></COLUMN>
    //           <COLUMN name="Purchase Date"><![CDATA[02/11/1980]]></COLUMN>
    //           <COLUMN name="Product Id"><![CDATA[45789]]></COLUMN>
    //         </ROW>
    //       </ROWS>
    //     </InsertUpdateRelationalTable>
    //   </Body>
    // </Envelope>
    //
    // A stdClass object will have it's data members evaluated as such:
    //   $record->attributes : an array of key=>value pairs of tag attributes
    //   $record->value : a normal text string value
    //   $record->children : an array of children tags

    // This is the long format.
    $column1 = new stdClass();
    $column1->attributes = array('name' => 'Record Id');
    $column1->value = 'GHbjh73643hsdiy';

    // Casting an array into an object makes this easier to perform inline.
    $column2 = (object) array(
      'attributes' => array('name' => 'Purchase Date'),
      'value' => '01/09/1975',
    ));
    $column3 = (object) array(
      'attributes' => array('name' => 'Product Id'),
      'value' => '123454',
    ));

    $silverpop_api->build('InsertUpdateRelationalTable', array(
      'TABLE_ID' => '86767',
      'ROWS' => array(
        0 => array(
          'ROW' => array(
            0 => array('COLUMN' => $column1),
            1 => array('COLUMN' => $column2),
            2 => array('COLUMN' => $column3),
          ),
        ),
        1 => array('ROW' => array(
          0 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Record Id'),
            'value' => 'WStfh73643hsdgw',
          )),
          1 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Purchase Date'),
            'value' => '02/11/1980',
          )),
          2 => array('COLUMN' => (object) array(
            'attributes' => array('name' => 'Product Id'),
            'value' => '45789',
          )),
        )),
      ),
    ))->execute();

    ?>