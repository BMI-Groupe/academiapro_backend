<?php

namespace App\Providers;

use App\Interfaces\ClassroomInterface;
use App\Repositories\ClassroomRepository;
use Illuminate\Support\ServiceProvider;

class ClassroomServiceProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->app->bind(ClassroomInterface::class, ClassroomRepository::class);
	}

	public function boot(): void
	{
		//
	}
}


