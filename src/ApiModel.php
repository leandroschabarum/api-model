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
	 * Indicates whether lazy loading will
	 * be prevented in the ApiModel.
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
		$api_class_name = static::$ApiClass;
		$full_path_api_class_name = str_contains($api_class_name, "\\") ? explode("\\", $api_class_name) : [$api_class_name];

		return end($full_path_api_class_name);
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
	 */
	final protected static function checkResponseOk($response, bool $strict = false)
	{
		$ok = function ($status_code)
		{
			$status_code = (int) $status_code;

			return ($status_code >= 200 && $status_code < 300) ? true : false;
		};

		if ($response instanceof ApiResponse)
		{
			$status = $ok($response->status());
		}
		else if (is_array($response) && isset($response['status'])) // @todo add property to specify status code in arrays
		{
			$status = $ok($response['status']);
		}
		else if (ctype_digit((string) $response))
		{
			$status = $ok($response);
		}

		if (! isset($status))
		{
			throw new InvalidArgumentException("Response argument is not of the correct type.");
		}

		if ($strict && $status === false)
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
	 * Método estático para retornar o identificador
	 * do atributo do objeto ApiModel.
	 * 
	 * @param  string  $attr
	 * @return mixed|int
	 */
	final public static function getAttributeId(string $attr)
	{
		if (empty(static::$field_mapping))
		{
			throw new LogicException("No attribute mapping is defined on ApiModel.");
		}
		else if (! is_array(static::$field_mapping) || self::hasReferenceById(static::$field_mapping))
		{
			throw new LogicException("Attribute mapping is not defined properly on ApiModel.");
		}

		return isset(static::$field_mapping[$attr]) ? static::$field_mapping[$attr] : null;
	}

	/**
	 * Método estático para retornar o nome
	 * do atributo do objeto ApiModel.
	 * 
	 * @param  int  $id
	 * @return mixed|string
	 */
	final public static function getAttributeName(int $id)
	{
		if (empty(static::$field_mapping))
		{
			throw new LogicException("No attribute mapping is defined on ApiModel.");
		}
		else if (! is_array(static::$field_mapping) || self::hasReferenceById(static::$field_mapping))
		{
			throw new LogicException("Attribute mapping is not defined properly on ApiModel.");
		}

		$flipped_field_mapping = array_flip(static::$field_mapping);

		return isset($flipped_field_mapping[$id]) ? $flipped_field_mapping[$id] : null;
	}

	/**
	 * Método para prevenir que relações externas
	 * sejam 'lazy loaded' no ApiModel.
	 *
	 * @param  bool  $value
	 * @return void
	 */
	public static function preventLazyLoading($value = true)
	{
		static::$modelsShouldPreventLazyLoading = $value;
	}

	/**
	 * Método para determinar se 'lazy loading' está desabilitado.
	 *
	 * @return bool
	 */
	public static function preventsLazyLoading()
	{
		return static::$modelsShouldPreventLazyLoading;
	}

	/**
	 * Método para registrar callback responsavel por gerenciar violações de 'lazy loading'.
	 *
	 * @param  callable  $callback
	 * @return void
	 */
	public static function handleLazyLoadingViolationUsing(callable $callback)
	{
		static::$lazyLoadingViolationCallback = $callback;
	}

	/**
	 * Método estático para executar ações adicionais
	 * durante a inicialização de ApiModels. Sua
	 * implementação deve ser feita na classe
	 * que extende ApiModels.
	 */
	protected static function boot()
	{
		// (opcional) implementar na classe que extende ApiModels
	}

	/**
	 * Método para limpar a lista de ApiModels inicializados.
	 *
	 * @return void
	 */
	public static function clearBootedModels()
	{
		static::$booted = [];
		static::$globalScopes = [];
	}

	/**
	 * Construtor base para objetos Models de requisições de APIs.
	 * 
	 * @param  array  $attr
	 */
	final public function __construct(array $attr = [], bool $exists = false)
	{
		$this->syncOriginal();
		$this->forceFill($attr);
		$this->reguard();

		$this->model_class = static::class;
		$this->setApiClass(static::$ApiClass);
		$this->exists = $exists;

		if (!isset(static::$booted[static::class]))
		{
			static::$booted[static::class] = true;
			$this->fireModelEvent('booting', false);

			// implementação do método boot() é de responsabilidade
			// do programador da classe que extende ApiModels
			static::boot();

			$this->fireModelEvent('booted', false);
		}
	}
}
