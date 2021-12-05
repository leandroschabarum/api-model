<?php

namespace Ordnael\ApiModel\Console;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;

class ApiClassMakeCommand extends GeneratorCommand
{
	use CreatesMatchingTest;

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:apiclass';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new API class for ApiModel builder';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'ApiClass';

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function handle()
	{
		if (parent::handle() === false && ! $this->option('force'))
		{
			return false;
		}
	}

	/**
	 * Build the class with the given name.
	 *
	 * @param  string  $name
	 * @return string
	 */
	protected function buildClass($name)
	{
		$stub = parent::buildClass($name);

		$model = $this->option('apimodel');

		return $this->replaceModel($stub, $model);
	}

	/**
	 * Replace the model for the given stub.
	 *
	 * @param  string  $stub
	 * @param  string  $model
	 * @return string
	 */
	protected function replaceModel($stub, $model)
	{
		$modelClass = $this->parseModel($model);

		$replace = [
			'{{ modelSingular }}' => Str::singular(class_basename($modelClass)),
			'{{ modelPlural }}' => Str::plural(class_basename($modelClass)),
		];

		return str_replace(
			array_keys($replace), array_values($replace), $stub
		);
	}

	/**
	 * Get the fully-qualified model class name.
	 *
	 * @param  string  $model
	 * @return string
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function parseModel($model)
	{
		if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
			throw new InvalidArgumentException('Model name contains invalid characters.');
		}

		return $this->qualifyModel($model);
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		return $this->resolveStubPath('/stubs/apiclass.stub');
	}

	/**
	 * Resolve the fully-qualified path to the stub.
	 *
	 * @param  string  $stub
	 * @return string
	 */
	protected function resolveStubPath($stub)
	{
		return file_exists($customPath = $this->laravel->basePath(trim($stub, '/'))) ? $customPath : __DIR__.$stub;
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		return is_dir(app_path('Apis')) ? $rootNamespace.'\\Apis' : $rootNamespace;
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['force', null, InputOption::VALUE_NONE, 'Create the class even if it already exists'],
			['apimodel', 'm', InputOption::VALUE_OPTIONAL, 'The API model that the class applies to.']
		];
	}
}
