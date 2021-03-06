<?php

namespace Ordnael\ApiModel\Providers;

use Illuminate\Support\ServiceProvider;
use Ordnael\ApiModel\Console\ApiModelMakeCommand;
use Ordnael\ApiModel\Console\ApiClassMakeCommand;

class ApiModelServiceProvider extends ServiceProvider
{
	/**
	 * The commands to be registered.
	 *
	 * @var array
	 */
	protected $commands = [
		'ApiModelMake' => 'command.apimodel.make',
		'ApiClassMake' => 'command.apiclass.make',
	];

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot()
	{
		//
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerCommands($this->commands);
	}

	/**
	 * Register the given commands.
	 *
	 * @param  array  $commands
	 * @return void
	 */
	protected function registerCommands(array $commands)
	{
		foreach (array_keys($commands) as $command)
		{
			$this->{"register{$command}Command"}();
		}

		$this->commands(array_values($commands));
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerApiModelMakeCommand()
	{
		$this->app->singleton(
			'command.apimodel.make',
			function ($app)
			{
				return new ApiModelMakeCommand($app['files']);
			}
		);
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerApiClassMakeCommand()
	{
		$this->app->singleton(
			'command.apiclass.make',
			function ($app)
			{
				return new ApiClassMakeCommand($app['files']);
			}
		);
	}
}
