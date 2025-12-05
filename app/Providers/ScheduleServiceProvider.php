<?php

namespace App\Providers;

use App\Interfaces\ScheduleInterface;
use App\Repositories\ScheduleRepository;
use Illuminate\Support\ServiceProvider;

class ScheduleServiceProvider extends ServiceProvider
{
	/**
	 * Register services.
	 */
	public function register(): void
	{
		$this->app->bind(ScheduleInterface::class, ScheduleRepository::class);
	}

	/**
	 * Bootstrap services.
	 */
	public function boot(): void
	{
		//
	}
}
