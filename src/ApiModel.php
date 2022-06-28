<?php

namespace Ordnael\ApiModel;

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
use ReturnTypeWillChange;
use ReflectionClass;
use Exception;
use LogicException;
use InvalidArgumentException;

abstract class ApiModel implements Arrayable, ArrayAccess, HasBroadcastChannel, Jsonable, JsonSerializable, UrlRoutable
{
	use HasEvents;
	use HasAttributes;
	use HidesAttributes;
	use GuardsAttributes;
	use HasGlobalScopes;
	use HasTimestamps;
	use ForwardsCalls;

	use Helpers;
	use ApiBuilder;

	/**
	 * Constant for standard error messages.
	 *
	 * @var array
	 */
	const DEFAULT_ERRORS = [
		'api_method_not_found' => "Unable to find API class method.",
		'invalid_api_class'    => "Unable to register API class for ApiModel.",
		'bad_api_request_code' => "An error has occurred while processing the API request.",
		'saving_model_failed'  => "An error has occurred while saving the ApiModel.",
		'model_not_found'      => "Unable to find ApiModel."
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
	protected static $statusField = 'status';

	/**
	 * Property that defines the field containing
	 * data from the API responses converted
	 * or received as array types.
	 *
	 * @var string
	 */
	protected static $dataField = 'data';

	/**
	 * Property that defines the field containing
	 * total counts from the API responses converted
	 * or received as array types.
	 *
	 * @var string
	 */
	protected static $totalField = 'all';

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
	 * Stores if the model will send only
	 * changed attributes on update calls.
	 *
	 * @var bool
	 */
	protected onlyDiff = true;

	/**
	 * Stores modified attributes on ApiModel object.
	 *
	 * @var array
	 */
	private $modified = [];

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
	private $model_class = null;

	/**
	 * Property that stores the class
	 * responsible for API requests calls
	 * when building the ApiModel object.
	 *
	 * @var string
	 */
	private $api_class = null;

	/**
	 * Property that stores the status code
	 * field name from API responses.
	 *
	 * @var string
	 */
	protected $status_code = null;

	/**
	 * Property that stores the data
	 * field name from API responses.
	 *
	 * @var string
	 */
	protected $data_field = null;

	/**
	 * Property that stores the total count
	 * field name from API responses.
	 *
	 * @var string
	 */
	protected $total_field = null;

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
	 * Static method to check presence of
	 * number-referenced fields from REST API.
	 *
	 * @param  array  $attr
	 * @return bool
	 */
	private static function hasNumericReference(array $attr = [])
	{
		$attributes = array_keys($attr);

		foreach ($attributes as $field) {
			if (ctype_digit((string) $field)) return true;
		}

		return false;
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
		if (is_array(static::$field_mapping) && ! empty(static::$field_mapping)) {
			$flipped_field_mapping = array_flip(static::$field_mapping);

			$id_referenced_fields = array_filter(
				$attr,
				function ($field) use ($flipped_field_mapping) {
					return array_key_exists($field, $flipped_field_mapping);
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		return $id_referenced_fields ?? [];
	}

	/**
	 * Static method to perform additional
	 * actions during ApiModels initialization.
	 * Its implementation must be done in
	 * the class that extends ApiModel.
	 */
	protected static function boot()
	{
		// This method is called between the 'booting'
		// and 'booted' events of the ApiModel constructor
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
		$id_referenced_fields = self::hasReferenceById($attr);

		if (! empty($id_referenced_fields)) {
			foreach ($id_referenced_fields as $field => $value) {
				$field_name = self::getAttributeName($field);
				unset($attr[$field]);
				$attr[$field_name ?? $field] = $value;
			}
		}

		return array_intersect_key($attr, array_flip(static::$fields));
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
	 * @return void
	 */
	final public function __construct(array $attr = [], bool $exists = false)
	{
		$this->setObjApiClass(static::$apiClass)
			 ->setObjStatusCodeField(static::$statusField)
			 ->setObjDataField(static::$dataField)
			 ->setObjTotalField(static::$totalField);
		$this->model_class = self::getModelClass();
		$this->exists = $exists;

		if (!isset(static::$booted[static::class])) {
			static::$booted[static::class] = true;
			$this->fireModelEvent('booting', false);

			// Implementation of the boot() method is left to the
			// programmer writing the class that extends ApiModel
			static::boot();

			$this->fireModelEvent('booted', false);
		}

		$this->syncOriginal();
		$this->forceFill($attr, false);
		$this->reguard();
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
		// add attributes to list of modified fields
		if ($this->getAttribute($attr) != $value) $this->setChanged($attr);

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
	 * @return $this
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
	 * @return $this
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
	 * @return string
	 */
	final protected function getObjApiClass()
	{
		return $this->api_class;
	}

	/**
	 * Method to change the class name for the
	 * API methods of the ApiModel object.
	 *
	 * @param  string  $class_name
	 * @return $this
	 *
	 * @throws Exception
	 */
	final protected function setObjApiClass(string $class_name)
	{
		if (class_exists($class_name)) {
			$this->api_class = $class_name;

			return $this;
		}

		throw new Exception(sprintf("%s (%s) - %s",
			self::getModelClassName(),
			class_basename($class_name),
			self::DEFAULT_ERRORS['invalid_api_class']
		));
	}

	/**
	 * Method to return the status code field name.
	 *
	 * @return string
	 */
	final protected function getObjStatusCodeField()
	{
		return $this->status_code;
	}

	/**
	 * Method to change the status code field name.
	 *
	 * @param  string  $field
	 * @return $this
	 *
	 * @throws InvalidArgumentException
	 */
	final protected function setObjStatusCodeField(string $field)
	{
		if (! self::isValidKeyPath($field)) {
			throw new InvalidArgumentException(sprintf('[%s] Status field is not valid.', $field));
		}

		$this->status_code = $field;

		return $this;
	}

	/**
	 * Method to return the data field name.
	 *
	 * @return string
	 */
	final protected function getObjDataField()
	{
		return $this->data_field;
	}

	/**
	 * Method to change the data field name.
	 *
	 * @param  string  $field
	 * @return $this
	 *
	 * @throws InvalidArgumentException
	 */
	final protected function setObjDataField(string $field = null)
	{
		if (! self::isValidKeyPath($field)) {
			throw new InvalidArgumentException(sprintf('[%s] Data field is not valid.', $field));
		}

		$this->data_field = $field;

		return $this;
	}

	/**
	 * Method to return the total field name.
	 *
	 * @return string
	 */
	final protected function getObjTotalField()
	{
		return $this->total_field;
	}

	/**
	 * Method to change the total field name.
	 *
	 * @param  string  $field
	 * @return $this
	 *
	 * @throws InvalidArgumentException
	 */
	final protected function setObjTotalField(string $field = null)
	{
		if (! self::isValidKeyPath($field)) {
			throw new InvalidArgumentException(sprintf('[%s] Total field is not valid.', $field));
		}

		$this->total_field = $field;

		return $this;
	}

	/**
	 * Method to determine if external
	 * relationship with other ApiModel exists.
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
	 * @return $this
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
	 * @return $this
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
	 * @return $this
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
	 * @return $this
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
	 * @param  mixed   $contents
	 * @return mixed
	 */
	public function getRelationValue(string $attr, $contents = null)
	{
		if ($this->relationLoaded($attr)) {
			return $this->getRelation($attr);
		} else if ($this->isRelation($attr)) {
			$this->setRelation($attr, $this->{$attr}($contents));

			return $this->getRelation($attr);
		}

		return $contents;
	}

	/**
	 * Method to duplicate ApiModel without
	 * any external relations loaded.
	 *
	 * @return $this
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
	 * @return $this
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
	 * @param  mixed   $value
	 * @param  string  $field
	 * @return $this
	 *
	 * @throws Exception
	 */
	public function resolveRouteBinding($value, $field = null)
	{
		if (isset($field) && $field !== $this->getRouteKeyName()) {
			throw new Exception(sprintf("Non primaryKey field <%s> not supported.", (string) $field));
		}

		return self::findOrFail($value);
	}

	/**
	 * Method to return external relation
	 * to ApiModel by bound value.
	 *
	 * @param  string  $childType
	 * @param  mixed   $value
	 * @param  string  $field
	 * @return $this|null
	 */
	public function resolveChildRouteBinding($childType, $value, $field)
	{
		if (isset($field) && $field !== $this->getRouteKeyName()) {
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
	 * Retrieve list of modified attributes on model.
	 *
	 * @return array
	 */
	final protected function getChanges()
	{
		return $this->modified ?? [];
	}

	/**
	 * Add field name to list of modified attributes on model.
	 *
	 * @param  string  $attr
	 * @return void
	 */
	final protected function setChanged(string $attr)
	{
		if (! in_array($attr, $this->getChanges(), true)) $this->modified[] = $attr;
	}

	/**
	 * Resets tracked changes on ApiModel object.
	 *
	 * @return $this
	 */
	final protected function unsetChanges()
	{
		$this->modified = [];

		return $this;
	}

	/**
	 * Method to check whether properties cause changes
	 * to the attributes of the instantiated object. If
	 * properties are not provided or are empty then
	 * checks wheter there are tracked changes on the
	 * object itself.
	 *
	 * @param  array  $properties
	 * @return bool
	 */
	final protected function hasChanges(array $properties = [])
	{
		if (! empty($properties)) {
			$original = array_intersect_key($this->attributesToArray(), $properties);

			return $properties != $original;
		}

		return ! empty($this->getChanges());
	}

	/**
	 * Method to force filling of attributes not listed as fillable.
	 * It is recommended to use fill() instead to not overwrite protected attributes.
	 *
	 * @param  mixed ...$args
	 * @return void
	 */
	final protected function forceFill(...$args)
	{
		return static::unguarded(
			function () use ($args) { return $this->fill(...$args); }
		);
	}

	/**
	 * Method for filling attributes listed as fillable.
	 *
	 * @param  array  $properties
	 * @param  bool   $track_changes
	 * @return void
	 *
	 * @throws \Illuminate\Database\Eloquent\MassAssignmentException
	 */
	final public function fill(array $properties, bool $track_changes = true)
	{
		$totallyGuarded = $this->totallyGuarded();
		$fields = array_merge(
			array_fill_keys(static::$fields, null),
			self::convertIdToNamedFields($properties)
		);

		$properties = array_filter(
			$fields, function ($attr) {
				return preg_match('%^[a-zA-Z_][a-zA-Z0-9_]+$%', (string) $attr);
			}, ARRAY_FILTER_USE_KEY
		);

		foreach ($this->fillableFromArray($properties) as $attr => $value) {
			if ($this->isFillable($attr)) {
				if ($track_changes && $this->getAttribute($attr) != $value) $this->setChanged($attr);

				$this->setAttribute($attr, $this->getRelationValue($attr, $value));
			} else if ($totallyGuarded) {
				throw new MassAssignmentException(
					sprintf("Add [%s] to fillable property to allow mass assignment on [%s].", $attr, get_class($this))
				);
			}
		}
	}
