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
	 * @return mixed|$this
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
	 * @return mixed|\Illuminate\Support\Collection
	 */
	final public static function all()
	{
		$ApiClass = self::getApiClass();
		$api = new $ApiClass();

		$api_method = "read" . Str::plural(self::getModelClassName());
		$response = method_exists($api, $api_method)
			? $api->$api_method()
			: [
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				'status' => 404
			];

		if (self::isResponseOk($response))
		{
			$ModelClass = self::getModelClass();
			$data = $response->json();
			$models = array();

			foreach ($data['data'] as $attributes)
			{
				$models[] = new $ModelClass($attributes, true);
			}

			return [
				'collection' => collect($models),
				'all' => $data['all']
			];
		}
		else
		{
			$status = is_array($response) ? $response['status'] : $response->status();

			return [
				'message' => sprintf("%d - %s", $status, self::DEFAULT_ERRORS['model_not_found']),
				'status' => $status,
				'error' => (is_array($response) ? $response : $response->json())
			];
		}
	}

	/**
	 * Static method to find an ApiModel.
	 * 
	 * @param  mixed  $id
	 * @return ApiModel
	 */
	final public static function find($id)
	{
		$ApiClass = self::getApiClass();
		$api = new $ApiClass();
		$api_method = "read" . Str::singular(self::getModelClassName());

		$response = method_exists($api, $api_method)
			? $api->$api_method($id)
			: [
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				'status' => 404
			];

		if (self::isResponseOk($response))
		{
			$ModelClass = self::getModelClass();
			$model = new $ModelClass($response->json(), true);

			return $model;
		}

		return null;
	}

	/**
	 * Static method to find an ApiModel, however if
	 * it doesn't exist, throws an Exception.
	 * 
	 * @param  mixed  $id
	 * @return ApiModel
	 * 
	 * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
	 */
	final public static function findOrFail($id)
	{
		$model = self::find($id);

		if (isset($model) && $model->exists) { return $model; }

		throw (new ModelNotFoundException)->setModel(self::getModelClassName(), $id);
	}

	/**
	 * Static method to search ApiModels and return
	 * a collection with the obtained results.
	 * 
	 * @param  array  $parameters
	 * @return mixed|\Illuminate\Support\Collection
	 */
	final public static function query(array $parameters)
	{
		$ApiClass = self::getApiClass();
		$api = new $ApiClass();

		$api_method = "read" . Str::plural(self::getModelClassName());
		$response = method_exists($api, $api_method)
			? $api->$api_method($parameters)
			: [
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				'status' => 404
			];

		if (self::isResponseOk($response))
		{
			$ModelClass = self::getModelClass();
			$data = $response->json();

			$models = array_map(
				function ($attr) use ($ModelClass)
				{
					return new $ModelClass($attr, true);
				},
				($data['data'] ?? [])
			);

			return [
				'collection' => collect($models),
				'all' => ($data['all'] ?? null)
			];
		}
		else
		{
			$status = is_array($response) ? $response['status'] : $response->status();

			return [
				'message' => sprintf("%d - %s", $status, self::DEFAULT_ERRORS['model_not_found']),
				'status' => $status,
				'error' => (is_array($response) ? $response : $response->json())
			];
		}
	}

	/**
	 * Method for updating an object's properties via the API.
	 * They must be listed under the fillable properties.
	 * 
	 * @param  array  $properties
	 * @return mixed|bool
	 */
	final public function update(array $properties)
	{
		if (!empty($properties) && $this->fireModelEvent('updating') === true)
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

		return true;
	}

	/**
	 * Method to save changes made locally in the ApiModel object.
	 * 
	 * @param  bool  $api_strict
	 * @return mixed|bool
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
			? (isset($model) ? $api->$api_method($pk, $current_attributes) : $api->$api_method($current_attributes))
			: [
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				'status' => 404
			];

		if (self::isResponseOk($response, $api_strict))
		{
			$pk = $this->getKeyName();
			$this->$pk = $response[$pk] ?? null;
			$this->exists = true;

			$this->fireModelEvent('saved', false);
			$this->syncOriginal();

			return true;
		}

		$status = is_array($response) ? $response['status'] : $response->status();

		return [
			'message' => sprintf("%d - %s", $status, self::DEFAULT_ERRORS['saving_model_failed']),
			'status' => $status,
			'error' => (is_array($response) ? $response : $response->json())
		];
	}

	/**
	 * Method to save changes made locally in the ApiModel object,
	 * however in case there is any failure, it throws an Exception.
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
	 */
	final public function delete()
	{
		$pk = $this->getKey();

		if (! isset($pk)) { throw new LogicException("No primary key defined on ApiModel."); }
		if (! $this->exists) { return true; }
		if ($this->fireModelEvent('deleting') === false) { return false; }

		$ApiClass = $this->getObjApiClass();
		$api = new $ApiClass();

		$api_method = "delete" . Str::singular(self::getModelClassName());
		$response = method_exists($api, $api_method)
			? $api->$api_method($pk)
			: [
				'message' => sprintf("%s - %s", $api_method, self::DEFAULT_ERRORS['api_method_not_found']),
				'status' => 404
			];

		if (self::isResponseOk($response))
		{
			$this->exists = false;
			$this->setAttribute($this->getKeyName(), null);
			$this->fireModelEvent('deleted', false);

			return true;
		}

		return false;
	}
}
