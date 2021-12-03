<?php

namespace Leandro\ApiModel\Console;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

class ApiModelMakeCommand extends GeneratorCommand
{
	use CreatesMatchingTest;

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:apimodel';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new API model class';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'ApiModel';

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

		if ($this->option('all'))
		{
			$this->input->setOption('controller', true);
			$this->input->setOption('policy', true);
			$this->input->setOption('resource', true);
		}

		if ($this->option('controller') || $this->option('resource') || $this->option('api'))
		{
			$this->createController();
		}

		if ($this->option('policy'))
		{
			$this->createPolicy();
		}
	}

	/**
	 * Create a controller for the model.
	 *
	 * @return void
	 */
	protected function createController()
	{
		$controller = Str::studly(class_basename($this->argument('name')));

		$modelName = $this->qualifyClass($this->getNameInput());

		$this->call('make:controller', array_filter([
			'name' => "{$controller}Controller",
			'--model' => $this->option('resource') || $this->option('api') ? $modelName : null,
			'--api' => $this->option('api'),
			'--requests' => $this->option('requests') || $this->option('all'),
		]));
	}

	/**
	 * Create a policy file for the model.
	 *
	 * @return void
	 */
	protected function createPolicy()
	{
		$policy = Str::studly(class_basename($this->argument('name')));

		$this->call('make:policy', [
			'name' => "{$policy}Policy",
			'--model' => $this->qualifyClass($this->getNameInput()),
		]);
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		return $this->resolveStubPath('/stubs/apimodel.stub');
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
		return is_dir(app_path('Models')) ? $rootNamespace.'\\Models' : $rootNamespace;
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return [
			['all', 'a', InputOption::VALUE_NONE, 'Generate a policy and resource controller for the API model'],
			['controller', 'c', InputOption::VALUE_NONE, 'Create a new controller for the API model'],
			['force', null, InputOption::VALUE_NONE, 'Create the class even if the API model already exists'],
			['policy', null, InputOption::VALUE_NONE, 'Create a new policy for the API model'],
			['resource', 'r', InputOption::VALUE_NONE, 'Indicates if the generated controller should be a resource controller'],
			['api', null, InputOption::VALUE_NONE, 'Indicates if the generated controller should be an API controller'],
		];
	}
}
