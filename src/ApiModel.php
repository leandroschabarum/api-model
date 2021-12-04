<?php

namespace Leandro\ApiModel;

use Illuminate\Http\Client\Response as ApiResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasGlobalScopes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Broadcasting\HasBroadcastChannel;
use Illuminate\Contracts\Routing\UrlRoutable;

use ArrayAccess;
use JsonSerializable;
use Exception;
use LogicException;
use InvalidArgumentException;
use ReturnTypeWillChange;
use ReflectionClass;

abstract class ApiModel implements Arrayable, ArrayAccess, HasBroadcastChannel, Jsonable, JsonSerializable, UrlRoutable
{
	use HasEvents;
	use HasAttributes;
	use HidesAttributes;
	use GuardsAttributes;
	use HasGlobalScopes;
	use HasTimestamps;
	use ForwardsCalls;

	use ApiBuilder;

	/**
	 * Constant for standard error messages.
	 * 
	 * @var array
	 */
	const DEFAULT_ERRORS = [
		'api_method_not_found' => "Unable to find API method.",
		'invalid_api_class'    => "Unable to register API class to ApiModel.",
		'bad_api_request_code' => "An error has occurred while processing API request.",
		'saving_model_failed'  => "An error has occurred while saving API Model.",
		'model_not_found'      => "Unable to find API Model."
	];

	/**
	 * Class constant that defines the name
	 * of the field to be used as a reference
	 * for the creation date of the ApiModel resource.
	 *
	 * @var string|null
	 */
	const CREATED_AT = null;

	/**
	 * Class constant that defines the name of the
	 * field to be used as a reference for the
	 * modification date of the ApiModel resource.
	 *
	 * @var string|null
	 */
	const UPDATED_AT = null;

	/**
	 * Holds the calling ApiModel class name.
	 * 
	 * @var string
	 */
	protected static $apiClass = null;

	/**
	 * Property that defines the field containing
	 * the status code from responses converted
	 * or received as array types.
	 * 
	 * @var string
	 */
	protected static $statusCode = 'status';

	/**
	 * Property that defines ApiModel
	 * attributes from REST API fields.
	 * 
	 * @var array
	 */
	protected static $fields = [];

	/**
	 * Property that holds ApiModel reference
	 * attributes for REST API field mapping.
	 * 
	 * @var array
	 */
	protected static $field_mapping = [];

	/**
	 * List of ApiModels already initialized.
	 *
	 * @var array
	 */
	protected static $booted = [];

	/**
	 * List of ApiModel global scopes.
	 *
	 * @var array
	 */
	protected static $globalScopes = [];

	/**
	 * Event dispatcher instance.
	 *
	 * @var \Illuminate\Contracts\Events\Dispatcher
	 */
	protected static $dispatcher;

	/**
	 * Indicates whether lazy loading
	 * should be restricted on all ApiModels.
	 *
	 * @var bool
	 */
	protected static $modelsShouldPreventLazyLoading = false;

	/**
	 * Callback responsible for managing lazy loading violations.
	 *
	 * @var callable|null
	 */
	protected static $lazyLoadingViolationCallback;

	/**
	 * Indicates whether lazy loading will
	 * be prevented in the ApiModel.
	 *
	 * @var bool
	 */
	public $preventsLazyLoading = false;

	/**
	 * Property that stores the extended class
	 * that called the constructor for the
	 * ApiModel object.
	 * 
	 * @var string
	 */
	protected $model_class = null;

	/**
	 * Property that stores the class
	 * responsible for API requests calls
	 * when building the ApiModel object.
	 * 
	 * @var string
	 */
	protected $api_class = null;

	/**
	 * Property that stores the status code field
	 * name from API responses in array format.
	 * 
	 * @var string
	 */
	protected $status_code = null;

	/**
	 * Indicates whether the ApiModel exists.
	 *
	 * @var bool
	 */
	public $exists = false;

	/**
	 * Class property that indicates whether
	 * the attribute being used as primary key
	 * in the ApiModel object is incremental.
	 *
	 * @var bool
	 */
	public $incrementing = false;

	/**
	 * Class property that defines the
	 * name of the attribute to be used
	 * as primary key in the ApiModel object.
	 * 
	 * @var string
	 */
	protected $primaryKey = 'id';

	/**
	 * Class property that defines the
	 * type of attribute being used as
	 * primary key in the ApiModel object.
	 *
	 * @var string
	 */
	protected $keyType = 'int';

	/**
	 * Property of the class that stores external
	 * relations with other ApiModels already initialized.
	 *
	 * @var array
	 */
	protected $relations = [];

	/**
	 * Property of the class that stores external
	 * relations that were touched by ApiModel.
	 *
	 * @var array
	 */
	protected $touches = [];

	/**
	 * Static method to return the name
	 * of the ApiModel extending class.
	 * 
	 * @return string
	 */
	private static function getModelClassName()
	{
		$class_name = static::class;
		$full_path_class_name = str_contains($class_name, "\\") ? explode("\\", $class_name) : [$class_name];

		return end($full_path_class_name);
	}

	/**
	 * Static method to return the name
	 * of the class making API calls for
	 * the ApiModel object.
	 * 
	 * @return string
	 */
	private static function getApiClassName()
	{
		$api_class_name = static::$apiClass;
		$full_path_api_class_name = str_contains($api_class_name, "\\") ? explode("\\", $api_class_name) : [$api_class_name];

		return end($full_path_api_class_name);
	}

	/**
	 * Static method to perform additional
	 * actions during ApiModels initialization.
	 * Its implementation must be done in
	 * the class that extends ApiModels.
	 */
	protected static function boot()
	{
		// Implementation of this method is left
		// to the programmer writing the class
		// extending the ApiModel
	}

	/**
	 * Static method to check presence of
	 * ID-referenced fields from REST API.
	 * 
	 * @param  array  $attr
	 * @return bool
	 */
	final protected static function hasReferenceById(array $attr = [])
	{
		$attributes = array_keys($attr);

		foreach ($attributes as $field)
		{
			if (ctype_digit((string) $field)) { return true; }
		}

		return false;
	}

	/**
	 * Static method for converting ID-referenced
	 * attributes to name-referencing.
	 * 
	 * @param  array  $attr
	 * @return array
	 */
	final protected static function convertIdToNamedFields(array $attr = [])
	{
		if (empty(static::$field_mapping))
		{
			return array_intersect_key($attr, array_flip(static::$fields));
		}
		else if (self::hasReferenceById($attr))
		{
			foreach ($attr as $field => $value)
			{
				if (ctype_digit((string) $field))
				{
					$field_name = self::getAttributeName((int) $field);

					if (isset($field_name)) { $converted[$field_name] = $value; }
				}

				$converted[$field] = $value; 
			}

			$attr = $converted;
		}

		return array_intersect_key($attr, array_flip(static::$fields));
	}

	/**
	 * Static method to check response status code from REST API
	 * 
	 * @param  mixed  $response
	 * @param  bool  $strict
	 * @return bool
	 * 
	 * @throws InvalidArgumentException|Exception
	 */
	final protected static function isResponseOk($response, bool $strict = false)
	{
		$is_ok = function ($code) { return ((int) $code >= 200 && (int) $code < 300); };

		if ($response instanceof ApiResponse && method_exists($response, 'status'))
		{
			$status = $is_ok($response->status());
		}
		else if (is_array($response) && array_key_exists($this->getStatusCodeField(), $response))
		{
			$status = $is_ok($response[$this->getStatusCodeField()]);
		}
		else if (ctype_digit((string) $response))
		{
			$status = $is_ok($response);
		}

		if (! isset($status))
		{
			throw new InvalidArgumentException("Unable to process response status code.");
		}

		if ($strict && $status !== true)
		{
			throw new Exception(sprintf("%s (%s%s) - %s",
				self::getModelClassName(),
				self::getApiClassName(),
				(isset($response['message']) ? sprintf(":\t%s", $response['message']) : null),
				self::DEFAULT_ERRORS['bad_api_request_code']
			));
		}

		return $status;
	}

	/**
	 * Static method to return the attribute
	 * identifier for the ApiModel object.
	 * 
	 * @param  string  $attr
	 * @return mixed|int
	 */
	final public static function getAttributeId(string $attr)
	{
		if (empty(static::$field_mapping))
		{
			throw new LogicException("No field mapping is defined on ApiModel.");
		}
		else if (! is_array(static::$field_mapping) || self::hasReferenceById(static::$field_mapping))
		{
			throw new LogicException("Field mapping is not defined properly on ApiModel.");
		}

		return isset(static::$field_mapping[$attr]) ? static::$field_mapping[$attr] : null;
	}

	/**
	 * Static method to return the attribute
	 * name of the ApiModel object.
	 * 
	 * @param  int  $id
	 * @return mixed|string
	 */
	final public static function getAttributeName(int $id)
	{
		if (empty(static::$field_mapping))
		{
			throw new LogicException("No field mapping is defined on ApiModel.");
		}
		else if (! is_array(static::$field_mapping) || self::hasReferenceById(static::$field_mapping))
		{
			throw new LogicException("Field mapping is not defined properly on ApiModel.");
		}

		$flipped_field_mapping = array_flip(static::$field_mapping);

		return isset($flipped_field_mapping[$id]) ? $flipped_field_mapping[$id] : null;
	}

	/**
	 * Method to prevent external relations
	 * from being lazy loaded in ApiModel.
	 *
	 * @param  bool  $value
	 * @return void
	 */
	public static function preventLazyLoading($value = true)
	{
		static::$modelsShouldPreventLazyLoading = $value;
	}

	/**
	 * Method to determine if lazy loading is disabled.
	 *
	 * @return bool
	 */
	public static function preventsLazyLoading()
	{
		return static::$modelsShouldPreventLazyLoading;
	}

	/**
	 * Method to log callback responsible
	 * for handling lazy loading violations.
	 *
	 * @param  callable  $callback
	 * @return void
	 */
	public static function handleLazyLoadingViolationUsing(callable $callback)
	{
		static::$lazyLoadingViolationCallback = $callback;
	}

	/**
	 * Method to clear the list of initialized ApiModels.
	 *
	 * @return void
	 */
	public static function clearBootedModels()
	{
		static::$booted = [];
		static::$globalScopes = [];
	}

	/**
	 * Base constructor for API request Models objects.
	 * 
	 * @param  array  $attr
	 */
	final public function __construct(array $attr = [], bool $exists = false)
	{
		$this->syncOriginal();
		$this->forceFill($attr);
		$this->reguard();

		$this->model_class = static::class;
		$this->setApiClass(static::$apiClass);
		$this->setStatusCodeField(static::$statusCode);
		$this->exists = $exists;

		if (!isset(static::$booted[static::class]))
		{
			static::$booted[static::class] = true;
			$this->fireModelEvent('booting', false);

			// Implementation of the boot() method is the left to
			// the programmer writing the class that extends ApiModels
			static::boot();

			$this->fireModelEvent('booted', false);
		}
	}

	/**
	 * Special method to dynamically return
	 * attribute values from ApiModel object.
	 * 
	 * @param  string  $attr
	 * @return mixed
	 */
	final public function __get($attr)
	{
		return $this->getAttribute($attr);
	}

	/**
	 * Special method for dynamically assigning
	 * values to attributes of ApiModel object.
	 * 
	 * @param  string  $attr
	 * @param  mixed   $value
	 * @return void
	 */
	final public function __set($attr, $value)
	{
		$this->setAttribute($attr, $value);
	}

	/**
	 * Method used to check if the
	 * primary key is incremental.
	 *
	 * @return bool
	 */
	final protected function getIncrementing()
	{
		return $this->incrementing;
	}

	/**
	 * Method to return the name of
	 * the attribute used as the primary
	 * key on the ApiModel object.
	 *
	 * @return string
	 */
	final protected function getKeyName()
	{
		return $this->primaryKey;
	}

	/**
	 * Method to change the name of
	 * the attribute used as the primary
	 * key on the ApiModel object.
	 *
	 * @param  string  $pk
	 * @return ApiModel
	 */
	final protected function setKeyName(string $pk)
	{
		$this->primaryKey = $pk;

		return $this;
	}

	/**
	 * Method to return the attribute
	 * value used as the ApiModel
	 * object's primary key.
	 *
	 * @return mixed
	 */
	final protected function getKey()
	{
		return $this->getAttribute($this->getKeyName());
	}

	/**
	 * Method to return the attribute
	 * type used as the ApiModel
	 * object's primary key.
	 *
	 * @return string
	 */
	final protected function getKeyType()
	{
		return $this->keyType;
	}

	/**
	 * Method to change the attribute type
	 * used as primary key on ApiModel object.
	 *
	 * @param  string  $type
	 * @return ApiModel
	 */
	final protected function setKeyType(string $type)
	{
		$this->keyType = $type;

		return $this;
	}

	/**
	 * Method to return the class name for the
	 * API methods of the ApiModel object.
	 * 
	 * @return mixed|static::Class
	 */
	final protected function getApiClass()
	{
		return $this->api_class;
	}

	/**
	 * Method to change the class name for the
	 * API methods of the ApiModel object.
	 *
	 * @param  string  $class_name
	 * @return ApiModel
	 * 
	 * @throws Exception
	 */
	final protected function setApiClass(string $class_name)
	{
		if (class_exists($class_name)) {
			$this->api_class = $class_name;

			return $this;
		}

		throw new Exception(sprintf("%s (%s) - %s",
			self::getModelClassName(),
			self::getApiClassName(),
			self::DEFAULT_ERRORS['invalid_api_class']
		));
	}

	/**
	 * Method to return the status code field.
	 *
	 * @return string
	 */
	final protected function getStatusCodeField()
	{
		return $this->status_code;
	}

	/**
	 * Method to change the status code field.
	 *
	 * @param  string  $field
	 * @return ApiModel
	 */
	final protected function setStatusCodeField(string $field)
	{
		$this->status_code = $field;

		return $this;
	}

	/**
	 * Method to return all external relations
	 * with other ApiModels already initialized.
	 *
	 * @return array
	 */
	public function getRelations()
	{
		return $this->relations;
	}

	/**
	 * Method to return specific external relationship
	 * with another ApiModel that has already been initialized.
	 *
	 * @param  string  $relation
	 * @return mixed
	 */
	public function getRelation($relation)
	{
		return $this->relations[$relation];
	}

	/**
	 * Method to register specific external
	 * relationship with another ApiModel.
	 *
	 * @param  string  $relation
	 * @param  mixed  $value
	 * @return ApiModel
	 */
	public function setRelation($relation, $value)
	{
		$this->relations[$relation] = $value;

		return $this;
	}

	/**
	 * Method to clean specific external
	 * relationship with another ApiModel.
	 *
	 * @param  string  $relation
	 * @return ApiModel
	 */
	public function unsetRelation($relation)
	{
		unset($this->relations[$relation]);

		return $this;
	}

	/**
	 * Method to register all external
	 * relations with other ApiModels.
	 *
	 * @param  array  $relations
	 * @return ApiModel
	 */
	public function setRelations(array $relations)
	{
		$this->relations = $relations;

		return $this;
	}

	/**
	 * Method to clean all external
	 * relations with other ApiModels.
	 *
	 * @return ApiModel
	 */
	public function unsetRelations()
	{
		$this->relations = [];

		return $this;
	}

	/**
	 * Method to determine if external relationship
	 * with other ApiModels was loaded.
	 *
	 * @param  string  $relation
	 * @return bool
	 */
	public function relationLoaded(string $relation)
	{
		return array_key_exists($relation, $this->relations);
	}

	/**
	 * Method to return external relations
	 * with other ApiModels when they exist.
	 *
	 * @param  string  $attr
	 * @param  mixed  $contents
	 * @return mixed
	 */
	public function getRelationValue(string $attr, $contents = null)
	{
		if ($this->relationLoaded($attr))
		{
			return $this->getRelation($attr);
		}
		else if ($this->isRelation($attr))
		{
			$this->setRelation($attr, $this->$attr($contents));
			
			return $this->getRelation($attr);
		}

		return $contents;
	}

	/**
	 * Method to duplicate ApiModel without
	 * any external relations loaded.
	 *
	 * @return ApiModel
	 */
	public function withoutRelations()
	{
		$model = clone $this;

		return $model->unsetRelations();
	}

	/**
	 * Method to return external
	 * relations touched by ApiModel.
	 *
	 * @return array
	 */
	public function getTouchedRelations()
	{
		return $this->touches;
	}

	/**
	 * Method for storing external
	 * relation touched by ApiModel.
	 *
	 * @param  array  $touches
	 * @return ApiModel
	 */
	public function setTouchedRelations(array $touches)
	{
		$this->touches = $touches;

		return $this;
	}

	/**
	 * Method to determine if ApiModel
	 * touches informed external relationship.
	 *
	 * @param  string  $relation
	 * @return bool
	 */
	public function touches(string $relation)
	{
		return in_array($relation, $this->getTouchedRelations());
	}

	/**
	 * Method to determine the existence
	 * of the offset attribute.
	 *
	 * @param  mixed  $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return ! is_null($this->getAttribute($offset));
	}

	/**
	 * Method to return the offset value.
	 *
	 * @param  mixed  $offset
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->getAttribute($offset);
	}

	/**
	 * Method for storing the offset value.
	 *
	 * @param  mixed  $offset
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->setAttribute($offset, $value);
	}

	/**
	 * Method to dereference the offset.
	 *
	 * @param  mixed  $offset
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		unset($this->attributes[$offset]);
	}

	/**
	 * Method to return the value associated
	 * with ApiModel's route attribute.
	 *
	 * @return mixed
	 */
	public function getRouteKey()
	{
		return $this->getAttribute($this->getRouteKeyName());
	}

	/**
	 * Method to return the attribute name
	 * used as primary key and registered
	 * as ApiModel route.
	 *
	 * @return string
	 */
	public function getRouteKeyName()
	{
		return $this->getKeyName();
	}

	/**
	 * Method to return linked value of ApiModel.
	 *
	 * @param  mixed  $value
	 * @param  string|null  $field
	 * @return ApiModel
	 * 
	 * @throws Exception
	 */
	public function resolveRouteBinding($value, $field = null)
	{
		if (isset($field) && $field !== $this->getRouteKeyName())
		{
			throw new Exception(sprintf("Non primaryKey field <%s> not supported.", (string) $field));
		}

		return self::findOrFail($value);
	}

	/**
	 * Method to return external relation
	 * to ApiModel by bound value.
	 *
	 * @param  string  $childType
	 * @param  mixed  $value
	 * @param  string|null  $field
	 * @return ApiModel|null
	 */
	public function resolveChildRouteBinding($childType, $value, $field)
	{
		if (isset($field) && $field !== $this->getRouteKeyName())
		{
			throw new Exception(sprintf("Non primaryKey field <%s> not supported.", (string) $field));
		}

		$ModelClass = Str::camel($childType);

		return is_subclass_of(self::class, $ModelClass) ? $ModelClass::findOrFail($value) : null;
	}

	/**
	 * Method to return the defined route for the broadcast
	 * channel that is associated with the entity.
	 *
	 * @return string
	 */
	public function broadcastChannelRoute()
	{
		return str_replace('\\', '.', get_class($this)) . ".{" . Str::camel(class_basename($this)) . "}";
	}

	/**
	 * Method to return the name of the broadcast
	 * channel that is associated with the entity.
	 *
	 * @return string
	 */
	public function broadcastChannel()
	{
		return str_replace('\\', '.', get_class($this)) . "." . $this->getKey();
	}

	/**
	 * Method to convert ApiModel object to array.
	 *
	 * @return array
	 */
	public function toArray()
	{
		return $this->attributesToArray();
	}

	/**
	 * Method to convert ApiModel object to JSON.
	 *
	 * @param  int  $options
	 * @return string
	 *
	 * @throws \Illuminate\Database\Eloquent\JsonEncodingException
	 */
	public function toJson($options = 0)
	{
		$json = json_encode($this->jsonSerialize(), $options);

		if (json_last_error() !== JSON_ERROR_NONE) {
			throw JsonEncodingException::forModel($this, json_last_error_msg());
		}

		return $json;
	}

	/**
	 * Helper method to convert ApiModel object
	 * into something possible to serialize.
	 *
	 * @return array
	 */
	#[ReturnTypeWillChange]
	public function jsonSerialize()
	{
		return $this->toArray();
	}

	/**
	 * Method to check whether properties cause changes
	 * to the attributes of the instantiated object.
	 * 
	 * @param  array  $properties
	 * @return bool
	 */
	final protected function hasChanges(array $properties = [])
	{
		if (! empty($properties))
		{
			$original = $this->attributesToArray();

			return ($properties != $original);
		}

		return false;
	}

	/**
	 * Method to determine if external
	 * relationship with other ApiModels exists.
	 *
	 * @param  string  $attr
	 * @return bool
	 */
	public function isRelation($attr)
	{
		$model_class_info = new ReflectionClass(static::class);

		return ($model_class_info->hasMethod($attr) && ($model_class_info->getMethod($attr)->class === static::class));
	}

	/**
	 * Method for filling attributes listed as fillable.
	 * 
	 * @param  array  $properties
	 * 
	 * @throws \Illuminate\Database\Eloquent\MassAssignmentException
	 */
	final public function fill(array $properties)
	{
		$totallyGuarded = $this->totallyGuarded();
		$properties = array_merge(array_fill_keys(static::$fields, null), self::convertIdToNamedFields($properties));

		foreach ($this->fillableFromArray($properties) as $attr => $value)
		{
			if ($this->isFillable($attr))
			{
				$this->setAttribute($attr, $this->getRelationValue($attr, $value));
			}
			else if ($totallyGuarded)
			{
				unset($model_class_info);

				throw new MassAssignmentException(
					sprintf("Add [%s] to fillable property to allow mass assignment on [%s].", $attr, get_class($this))
				);
			}
		}

		unset($model_class_info);
	}

	/**
	 * Method to force filling of attributes not listed as fillable.
	 * It is recommended to use fill() instead to not overwrite protected attributes.
	 * 
	 * @param  array  $properties
	 */
	private function forceFill(array $properties)
	{
		return static::unguarded(
			function () use ($properties) {	return $this->fill($properties); }
		);
	}
}
