<?php

namespace Leandro\ApiModel\Console;

use Illuminate\Console\Concerns\CreatesMatchingTest;
use Illuminate\Console\GeneratorCommand;
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
			['force', null, InputOption::VALUE_NONE, 'Create the class even if it already exists']
		];
	}
}
