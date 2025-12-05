<?php

namespace App\Providers;

use App\Interfaces\GradeInterface;
use App\Repositories\GradeRepository;
use Illuminate\Support\ServiceProvider;

class GradeServiceProvider extends ServiceProvider
{
	/**
	 * Register services.
	 */
	public function register(): void
	{
		$this->app->bind(GradeInterface::class, GradeRepository::class);
	}

	/**
	 * Bootstrap services.
	 */
	public function boot(): void
	{
		//
	}
}
