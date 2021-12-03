<?php

namespace Leandro\ApiModel;

use Illuminate\Database\Eloquent\Concerns\HasEvents;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasGlobalScopes;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;

use Illuminate\Http\Client\Response as ApiResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

	/**
	 * Holds the calling ApiModel class name.
	 * 
	 * @var string
	 */
	protected static $ApiClass = null;

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
	 * Indicates whether lazy loading should be restricted on all ApiModels.
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
	 * Indicates whether lazy loading will be prevented in ApiModel.
	 *
	 * @var bool
	 */
	public $preventsLazyLoading = false;

	/**
	 * Constant for standard error messages.
	 * 
	 * @var array
	 */
	const DEFAULT_ERRORS = [
		'api_method_not_found' => "Unable to find API method.",
		'invalid_api_class' => "Unable to register API class to ApiModel.",
		'bad_api_request_code' => "An error has occurred while processing API request.",
		'saving_model_failed' => "An error has occurred while saving API Model.",
		'model_not_found' => "Unable to find API Model."
	];

	/**
	 * Class constant that defines the name
	 * of the column to be used as a reference
	 * for the creation date of the ApiModel object.
	 *
	 * @var string|null
	 */
	const CREATED_AT = null;

	/**
	 * Class constant that defines the name of the
	 * column to be used as a reference for the
	 * modification date of the ApiModel object.
	 *
	 * @var string|null
	 */
	const UPDATED_AT = null;
}
