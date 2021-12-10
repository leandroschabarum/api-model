<?php

namespace Ordnael\ApiModel;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use Exception;
use LogicException;

trait ApiBuilder
{
	/**
	 * Static method to create an ApiModel and save it.
	 * 
	 * @param  array  $properties
	 * @return $this
	 */
	final public static function create(array $properties)
	{
		$ModelClass = self::getModelClass();
		$model = new $ModelClass($properties);

		$model->fireModelEvent('creating', false);
		$created = $model->save();

		if ($created === true)
		{
			$model->fireModelEvent('created', false);

			return $model;
		}
	}

	/**
	 * Static method to return a collection of all ApiModels.
	 * 
	 * @return \Illuminate\Support\Collection|array
	 */
	final public static function all()
	{
		$ApiClass = self::getApiClass();
		$api = new $ApiClass();
		$api_method = "read" . Str::plural(self::getModelClassName());

		$response = method_exists($api, $api_method)
			? $api->{$api_method}()
			: self::mockResponseArray([
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				self::getStatusCodeField() => 404
			]);

		if (self::isResponseCodeOk($response))
		{
			$ModelClass = self::getModelClass();
			$contents = is_array($response) ? $response : $response->json();

			$models = array_map(
				function ($attr) use ($ModelClass)
				{
					return new $ModelClass($attr, true);
				},
				(self::getKeyPathValue($contents, self::getDataField()) ?? [])
			);

			return (object) [
				'collection' => collect($models),
				'total' => self::getKeyPathValue($contents, self::getTotalField())
			];
		}
		else
		{
			$status = is_array($response) ? self::getKeyPathValue($response, self::getStatusCodeField()) : $response->status();

			return self::mockResponseArray([
				'message' => sprintf("%d - %s", $status, self::DEFAULT_ERRORS['model_not_found']),
				'error' => (is_array($response) ? $response : $response->json()),
				self::getStatusCodeField() => $status
			]);
		}
	}

	/**
	 * Static method to find an ApiModel.
	 * 
	 * @param  mixed  $id
	 * @return $this
	 */
	final public static function find($id, ...$args)
	{
		$ApiClass = self::getApiClass();
		$api = new $ApiClass();
		$api_method = "read" . Str::singular(self::getModelClassName());
		array_unshift($args, $id);

		$response = method_exists($api, $api_method)
			? call_user_func_array(array($api, $api_method), $args)
			: self::mockResponseArray([
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				self::getStatusCodeField() => 404
			]);

		if (self::isResponseCodeOk($response))
		{
			$ModelClass = self::getModelClass();
			$model = new $ModelClass((is_array($response) ? $response : $response->json()), true);

			return $model;
		}
	}

	/**
	 * Static method to find an ApiModel, however if
	 * it doesn't exist, throws an Exception.
	 * 
	 * @param  mixed  $id
	 * @return $this
	 * 
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	final public static function findOrFail($id, ...$args)
	{
		array_unshift($args, $id);
		$model = call_user_func_array(array(self::getModelClass(), 'find'), $args);

		if (isset($model) && $model->exists) { return $model; }

		throw (new ModelNotFoundException)->setModel(self::getModelClassName(), $id);
	}

	/**
	 * Static method to search ApiModels and return
	 * a collection with the obtained results.
	 * 
	 * @param  array  $parameters
	 * @return \Illuminate\Support\Collection|array
	 */
	final public static function query(array $parameters)
	{
		$ApiClass = self::getApiClass();
		$api = new $ApiClass();
		$api_method = "read" . Str::plural(self::getModelClassName());

		$response = method_exists($api, $api_method)
			? $api->{$api_method}($parameters)
			: self::mockResponseArray([
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				self::getStatusCodeField() => 404
			]);

		if (self::isResponseCodeOk($response))
		{
			$ModelClass = self::getModelClass();
			$contents = is_array($response) ? $response : $response->json();

			$models = array_map(
				function ($attr) use ($ModelClass)
				{
					return new $ModelClass($attr, true);
				},
				(self::getKeyPathValue($contents, self::getDataField()) ?? [])
			);

			return (object) [
				'collection' => collect($models),
				'total' => self::getKeyPathValue($contents, self::getTotalField())
			];
		}
		else
		{
			$status = is_array($response) ? self::getKeyPathValue($response, self::getStatusCodeField()) : $response->status();

			return self::mockResponseArray([
				'message' => sprintf("%d - %s", $status, self::DEFAULT_ERRORS['model_not_found']),
				'error' => (is_array($response) ? $response : $response->json()),
				self::getStatusCodeField() => $status
			]);
		}
	}

	/**
	 * Method for updating an object's properties with the API.
	 * They must be listed under the fillable properties.
	 * 
	 * @param  array  $properties
	 * @return bool|null
	 */
	final public function update(array $properties)
	{
		if (! empty($properties) && $this->fireModelEvent('updating') === true)
		{
			$this->fill($properties);
			$updated = $this->save();

			if ($updated === true)
			{
				$this->fireModelEvent('updated', false);

				return true;
			}

			return $updated;
		}
	}

	/**
	 * Method to save changes made locally in the ApiModel object.
	 * 
	 * @param  bool  $api_strict
	 * @return bool|array
	 */
	final public function save(bool $api_strict = false)
	{
		$this->mergeAttributesFromClassCasts();
		$pk = $this->getKey();

		if (isset($pk) && $this->exists)
		{
			$model = self::find($pk);

			if (! isset($model)) { return false; }

			$api_method = "update" . Str::singular(self::getModelClassName());
		}
		else
		{
			$api_method = "create" . Str::singular(self::getModelClassName());
		}

		$current_attributes = $this->attributesToArray();

		if ($this->fireModelEvent('saving') === false) { return false; }

		if (isset($model) && ! $model->hasChanges($current_attributes)) { return true; }

		$ApiClass = $this->getObjApiClass();
		$api = new $ApiClass();

		$response = method_exists($api, $api_method)
			? (isset($model) ? $api->{$api_method}($pk, $current_attributes) : $api->{$api_method}($current_attributes))
			: self::mockResponseArray([
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				$this->getObjStatusCodeField() => 404
			]);

		if (self::isResponseCodeOk($response, $api_strict))
		{
			$pk = $this->getKeyName();
			$this->$pk = $response[$pk] ?? null;
			$this->exists = true;

			$this->fireModelEvent('saved', false);
			$this->syncOriginal();

			return true;
		}

		$status = is_array($response) ? self::getKeyPathValue($response, $this->getObjStatusCodeField()) : $response->status();

		return self::mockResponseArray([
			'message' => sprintf("%d - %s", $status, self::DEFAULT_ERRORS['saving_model_failed']),
			'error' => (is_array($response) ? $response : $response->json()),
			$this->getObjStatusCodeField() => $status
		]);
	}

	/**
	 * Method to save changes made locally in the ApiModel object,
	 * however in case there is any failure, it throws an Exception.
	 * 
	 * @return bool
	 * 
	 * @throws Exception
	 */
	final public function saveOrFail()
	{
		if ($this->save(true) === true) { return true; }

		throw new Exception(sprintf("%s - %s",
			self::getModelClassName(),
			self::DEFAULT_ERRORS['saving_model_failed']
		));
	}

	/**
	 * Method to remove object from ApiModel.
	 * 
	 * @return bool
	 * 
	 * @throws LogicException
	 */
	final public function delete(...$args)
	{
		$pk = $this->getKey();

		if (! isset($pk)) { throw new LogicException("No primary key defined on ApiModel."); }
		if (! $this->exists) { return true; }
		if ($this->fireModelEvent('deleting') === false) { return false; }

		$ApiClass = $this->getObjApiClass();
		$api = new $ApiClass();
		$api_method = "delete" . Str::singular(self::getModelClassName());
		array_unshift($args, $pk);

		$response = method_exists($api, $api_method)
			? call_user_func_array(array($api, $api_method), $args)
			: self::mockResponseArray([
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				$this->getObjStatusCodeField() => 404
			]);

		if (self::isResponseCodeOk($response))
		{
			$this->exists = false;
			$this->setAttribute($this->getKeyName(), null);
			$this->fireModelEvent('deleted', false);

			return true;
		}

		return false;
	}
}
