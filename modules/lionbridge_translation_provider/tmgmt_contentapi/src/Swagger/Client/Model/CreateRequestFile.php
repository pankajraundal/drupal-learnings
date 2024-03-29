<?php
/**
 * CreateRequestFile
 *
 * PHP version 5
 *
 * @category Class
 * @package  Drupal\tmgmt_contentapi\Swagger\Client
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */

/**
 * The Lionbridge Content API
 *
 * Enable translations with the Lionbridge Content API.
 *
 * OpenAPI spec version: 1.4.3
 * 
 * Generated by: https://github.com/swagger-api/swagger-codegen.git
 * Swagger Codegen version: 2.3.1
 */

/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace Drupal\tmgmt_contentapi\Swagger\Client\Model;

use \ArrayAccess;
use \Drupal\tmgmt_contentapi\Swagger\Client\ObjectSerializer;

/**
 * CreateRequestFile Class Doc Comment
 *
 * @category Class
 * @package  Drupal\tmgmt_contentapi\Swagger\Client
 * @author   Swagger Codegen team
 * @link     https://github.com/swagger-api/swagger-codegen
 */
class CreateRequestFile implements ModelInterface, ArrayAccess
{
    const DISCRIMINATOR = null;

    /**
      * The original name of the model.
      *
      * @var string
      */
    protected static $swaggerModelName = 'CreateRequestFile';

    /**
      * Array of property to type mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerTypes = [
        'request_name' => 'string',
        'source_native_id' => 'string',
        'source_native_language_code' => 'string',
        'target_native_ids' => 'string[]',
        'target_native_language_codes' => 'string[]',
        'word_count' => 'int',
        'file_id' => 'string'
    ];

    /**
      * Array of property to format mappings. Used for (de)serialization
      *
      * @var string[]
      */
    protected static $swaggerFormats = [
        'request_name' => null,
        'source_native_id' => null,
        'source_native_language_code' => null,
        'target_native_ids' => null,
        'target_native_language_codes' => null,
        'word_count' => null,
        'file_id' => null
    ];

    /**
     * Array of property to type mappings. Used for (de)serialization
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public static function swaggerTypes()
    {
        return self::$swaggerTypes;
    }

    /**
     * Array of property to format mappings. Used for (de)serialization
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public static function swaggerFormats()
    {
        return self::$swaggerFormats;
    }

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @var string[]
     */
    protected static $attributeMap = [
        'request_name' => 'requestName',
        'source_native_id' => 'sourceNativeId',
        'source_native_language_code' => 'sourceNativeLanguageCode',
        'target_native_ids' => 'targetNativeIds',
        'target_native_language_codes' => 'targetNativeLanguageCodes',
        'word_count' => 'wordCount',
        'file_id' => 'fmsFileId'
    ];

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @var string[]
     */
    protected static $setters = [
        'request_name' => 'setRequestName',
        'source_native_id' => 'setSourceNativeId',
        'source_native_language_code' => 'setSourceNativeLanguageCode',
        'target_native_ids' => 'setTargetNativeIds',
        'target_native_language_codes' => 'setTargetNativeLanguageCodes',
        'word_count' => 'setWordCount',
        'file_id' => 'setFileId'
    ];

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @var string[]
     */
    protected static $getters = [
        'request_name' => 'getRequestName',
        'source_native_id' => 'getSourceNativeId',
        'source_native_language_code' => 'getSourceNativeLanguageCode',
        'target_native_ids' => 'getTargetNativeIds',
        'target_native_language_codes' => 'getTargetNativeLanguageCodes',
        'word_count' => 'getWordCount',
        'file_id' => 'getFileId'
    ];

    /**
     * Array of attributes where the key is the local name,
     * and the value is the original name
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public static function attributeMap()
    {
        return self::$attributeMap;
    }

    /**
     * Array of attributes to setter functions (for deserialization of responses)
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public static function setters()
    {
        return self::$setters;
    }

    /**
     * Array of attributes to getter functions (for serialization of requests)
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public static function getters()
    {
        return self::$getters;
    }

    /**
     * The original name of the model.
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function getModelName()
    {
        return self::$swaggerModelName;
    }

    

    

    /**
     * Associative array for storing property values
     *
     * @var mixed[]
     */
    protected $container = [];

    /**
     * Constructor
     *
     * @param mixed[] $data Associated array of property values
     *                      initializing the model
     */
    public function __construct(array $data = null)
    {
        $this->container['request_name'] = isset($data['request_name']) ? $data['request_name'] : null;
        $this->container['source_native_id'] = isset($data['source_native_id']) ? $data['source_native_id'] : null;
        $this->container['source_native_language_code'] = isset($data['source_native_language_code']) ? $data['source_native_language_code'] : null;
        $this->container['target_native_ids'] = isset($data['target_native_ids']) ? $data['target_native_ids'] : null;
        $this->container['target_native_language_codes'] = isset($data['target_native_language_codes']) ? $data['target_native_language_codes'] : null;
        $this->container['word_count'] = isset($data['word_count']) ? $data['word_count'] : null;
        $this->container['file_id'] = isset($data['file_id']) ? $data['file_id'] : null;
    }

    /**
     * Show all the invalid properties with reasons.
     *
     * @return array invalid properties with reasons
     */
    #[\ReturnTypeWillChange]
    public function listInvalidProperties()
    {
        $invalidProperties = [];

        if ($this->container['request_name'] === null) {
            $invalidProperties[] = "'request_name' can't be null";
        }
        if ($this->container['source_native_id'] === null) {
            $invalidProperties[] = "'source_native_id' can't be null";
        }
        if ($this->container['source_native_language_code'] === null) {
            $invalidProperties[] = "'source_native_language_code' can't be null";
        }
        if ($this->container['target_native_language_codes'] === null) {
            $invalidProperties[] = "'target_native_language_codes' can't be null";
        }
        return $invalidProperties;
    }

    /**
     * Validate all the properties in the model
     * return true if all passed
     *
     * @return bool True if all properties are valid
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {

        if ($this->container['request_name'] === null) {
            return false;
        }
        if ($this->container['source_native_id'] === null) {
            return false;
        }
        if ($this->container['source_native_language_code'] === null) {
            return false;
        }
        if ($this->container['target_native_language_codes'] === null) {
            return false;
        }
        return true;
    }


    /**
     * Gets request_name
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function getRequestName()
    {
        return $this->container['request_name'];
    }

    /**
     * Sets request_name
     *
     * @param string $request_name Name of the translation request.
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function setRequestName($request_name)
    {
        $this->container['request_name'] = $request_name;

        return $this;
    }

    /**
     * Gets source_native_id
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function getSourceNativeId()
    {
        return $this->container['source_native_id'];
    }

    /**
     * Sets source_native_id
     *
     * @param string $source_native_id Source ID of the request in the content system.  This is intended to be used for accessing the objects within the CMS. What to actually store here is arbitrary; it serves as a locator for read/write by the API consumer. For example, in a file-based connector, the sourceNativeId can be a file path /foo/bar/catalog/laptop/en/dell/inspiron/17/5000/features.txt, and the targetNativeId as /foo/bar/catalog/laptop/fr/dell/inspiron/17/5000/features.txt.  Then when the translated content is received, the targetNativeId can be used, to open the target file for writing back translations directly.
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function setSourceNativeId($source_native_id)
    {
        $this->container['source_native_id'] = $source_native_id;

        return $this;
    }

    /**
     * Gets source_native_language_code
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function getSourceNativeLanguageCode()
    {
        return $this->container['source_native_language_code'];
    }

    /**
     * Sets source_native_language_code
     *
     * @param string $source_native_language_code Source language code of the request in the content system.
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function setSourceNativeLanguageCode($source_native_language_code)
    {
        $this->container['source_native_language_code'] = $source_native_language_code;

        return $this;
    }

    /**
     * Gets target_native_ids
     *
     * @return string[]
     */
    #[\ReturnTypeWillChange]
    public function getTargetNativeIds()
    {
        return $this->container['target_native_ids'];
    }

    /**
     * Sets target_native_ids
     *
     * @param string[] $target_native_ids Target IDs of the requests in content system.  Also see description of sourceNativeId.
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function setTargetNativeIds($target_native_ids)
    {
        $this->container['target_native_ids'] = $target_native_ids;

        return $this;
    }

    /**
     * Gets target_native_language_codes
     *
     * @return string[]
     */
    #[\ReturnTypeWillChange]
    public function getTargetNativeLanguageCodes()
    {
        return $this->container['target_native_language_codes'];
    }

    /**
     * Sets target_native_language_codes
     *
     * @param string[] $target_native_language_codes Target languages of requests for translation.
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function setTargetNativeLanguageCodes($target_native_language_codes)
    {
        $this->container['target_native_language_codes'] = $target_native_language_codes;

        return $this;
    }

    /**
     * Gets word_count
     *
     * @return int
     */
    #[\ReturnTypeWillChange]
    public function getWordCount()
    {
        return $this->container['word_count'];
    }

    /**
     * Sets word_count
     *
     * @param int $word_count Word count in translation request. If provided, the value will be available when querying the request.
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function setWordCount($word_count)
    {
        $this->container['word_count'] = $word_count;

        return $this;
    }

    /**
     * Gets file_id
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function getFileId()
    {
        return $this->container['file_id'];
    }

    /**
     * Sets file_id
     *
     * @param string $file_id ID of source file to use in this request.
     *
     * @return $this
     */
    #[\ReturnTypeWillChange]
    public function setFileId($file_id)
    {
        $this->container['file_id'] = $file_id;

        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     *
     * @param integer $offset Offset
     *
     * @return boolean
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Gets offset.
     *
     * @param integer $offset Offset
     *
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Sets value based on offset.
     *
     * @param integer $offset Offset
     * @param mixed   $value  Value to be set
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Unsets offset.
     *
     * @param integer $offset Offset
     *
     * @return void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * Gets the string presentation of the object
     *
     * @return string
     */
    #[\ReturnTypeWillChange]
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) { // use JSON pretty print
            return json_encode(
                ObjectSerializer::sanitizeForSerialization($this),
                JSON_PRETTY_PRINT
            );
        }

        return json_encode(ObjectSerializer::sanitizeForSerialization($this));
    }
}


