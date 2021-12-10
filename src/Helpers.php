<?php

namespace Ordnael\ApiModel;

use Illuminate\Http\Client\Response as ApiResponse;
use Exception;
use LogicException;
use InvalidArgumentException;

trait Helpers
{
	/**
	 * Static method to return the
	 * class extending ApiModel.
	 * 
	 * @return string
	 */
	public static function getModelClass()
	{
		return static::class;
	}

	/**
	 * Static method to return the name
	 * of the ApiModel extending class.
	 * 
	 * @return string
	 */
	public static function getModelClassName()
	{
		return class_basename(static::class);
	}

	/**
	 * Static method to return the class
	 * making API calls for the ApiModel object.
	 * 
	 * @return string
	 */
	public static function getApiClass()
	{
		if (class_exists(static::$apiClass))
		{
			return static::$apiClass;
		}

		throw new Exception(sprintf("%s (%s) - %s",
			self::getModelClassName(),
			class_basename(static::$apiClass),
			self::DEFAULT_ERRORS['invalid_api_class']
		));
	}

	/**
	 * Static method to return the name
	 * of the class making API calls for
	 * the ApiModel object.
	 * 
	 * @return string
	 */
	public static function getApiClassName()
	{
		return class_basename(self::getApiClass());
	}

	/**
	 * Static method to return the status code field name.
	 *
	 * @return string
	 */
	public static function getStatusCodeField()
	{
		return static::$statusField;
	}

	/**
	 * Static method to return the data field name.
	 *
	 * @return string
	 */
	public static function getDataField()
	{
		return static::$dataField;
	}

	/**
	 * Static method to return the total field name.
	 *
	 * @return string
	 */
	public static function getTotalField()
	{
		return static::$totalField;
	}

	/**
	 * Static method to return the attribute
	 * identifier for the ApiModel object.
	 * 
	 * @param  string  $attr
	 * @return mixed
	 */
	final public static function getAttributeId(string $attr)
	{
		if (is_array(static::$field_mapping) && empty(static::$field_mapping))
		{
			throw new LogicException("No field mapping is defined on ApiModel.");
		}
		else if (! is_array(static::$field_mapping) || self::hasNumericReference(static::$field_mapping))
		{
			throw new LogicException("Field mapping is not defined properly on ApiModel.");
		}

		return isset(static::$field_mapping[$attr]) ? static::$field_mapping[$attr] : null;
	}

	/**
	 * Static method to return the attribute
	 * name of the ApiModel object.
	 * 
	 * @param  mixed  $id
	 * @return string|null
	 */
	final public static function getAttributeName($id)
	{
		if (is_array(static::$field_mapping) && empty(static::$field_mapping))
		{
			throw new LogicException("No field mapping is defined on ApiModel.");
		}
		else if (! is_array(static::$field_mapping) || self::hasNumericReference(static::$field_mapping))
		{
			throw new LogicException("Field mapping is not defined properly on ApiModel.");
		}

		$flipped_field_mapping = array_flip(static::$field_mapping);

		return isset($flipped_field_mapping[$id]) && is_string($flipped_field_mapping[$id]) ? $flipped_field_mapping[$id] : null;
	}

	/**
	 * Static method to check response status code from REST API.
	 * 
	 * @param  mixed  $response
	 * @param  bool   $strict
	 * @return bool
	 * 
	 * @throws InvalidArgumentException|Exception
	 */
	final protected static function isResponseCodeOk($response, bool $strict = false)
	{
		if ($response instanceof ApiResponse)
		{
			$code = $response->status();
		}
		else if (is_array($response))
		{
			$code = self::getKeyPathValue($response, self::getStatusCodeField());
		}
		else if (ctype_digit((string) $response))
		{
			$code = $response;
		}

		if (isset($code) && in_array(gettype($code), ['integer', 'string']))
		{
			$accepted = ((int) $code >= 200 && (int) $code < 300);
		}
		else
		{
			throw new InvalidArgumentException("Unable to process response status code.");
		}

		if ($strict && $accepted !== true)
		{
			throw new Exception(sprintf("%s (%s%s) - %s",
				self::getModelClassName(),
				self::getApiClassName(),
				(isset($response['message']) ? sprintf(":\t%s", $response['message']) : null),
				self::DEFAULT_ERRORS['bad_api_request_code']
			));
		}

		return $accepted;
	}

	/**
	 * Static method to check if
	 * string is a valid key path.
	 * 
	 * @param  string  $path
	 * @return bool
	 */
	final protected static function isValidKeyPath(string $path = null)
	{
		return (preg_match('/^[a-zA-Z0-9_\+\-\:\\\/]+(?:(?:\.[a-zA-Z0-9_\+\-\:\\\/]+)*|[^\.\s])$/', $path) || $path === null);
	}

	/**
	 * Static method to follow key path string
	 * in array and retrieve its value.
	 * 
	 * @param  array  $array
	 * @param  string $path
	 * @return mixed
	 * 
	 * @throws InvalidArgumentException
	 */
	final protected static function getKeyPathValue(array $array, string $path = null)
	{
		if (self::isValidKeyPath($path))
		{
			// IMPORTANT! followKeyPath() should ONLY be called after key path string is validated
			return isset($path) ? self::followKeyPath(explode('.', $path), $array) : $array;
		}

		throw new InvalidArgumentException(sprintf('Key path (%s) is not valid.', $path));
	}

	/**
	 * Recursive static method to walk
	 * array representation of key path.
	 * 
	 * @param  array  $keys
	 * @param  mixed  &$partition
	 * @return mixed
	 */
	private static function followKeyPath(array $keys, &$partition)
	{
		if (empty($keys) || ! is_array($partition))
		{
			// completed walking key path OR
			// current partition is not "walkable" anymore
			return $partition;
		}
		// array of keys represents a queue (FIFO)
		$current_key = array_shift($keys);

		return self::followKeyPath($keys, $partition[$current_key]);
	}

	/**
	 * Static method to make fake response
	 * array for reporting errors.
	 * 
	 * @param  array  $blueprint
	 * @return array
	 */
	private static function mockResponseArray(array $blueprint)
	{
		// $blueprint has to be an associative array, where the keys are
		// absolute paths to the targets that are supposed to be created
		$response_like_array = [];

		foreach ($blueprint as $key_path => $content)
		{
			if (! self::isValidKeyPath($key_path)) { continue; }

			$nodes = explode('.', $key_path);
			$partition =& $response_like_array;

			foreach ($nodes as $node)
			{
				if (! isset($partition[$node]))
				{
					$partition[$node] = [];
				}

				$partition =& $partition[$node];
			}

			$partition = $content;
		}

		return $response_like_array;
	}
}
