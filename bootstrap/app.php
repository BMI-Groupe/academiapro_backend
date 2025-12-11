<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleCors;
use App\Providers\AuthServiceProvider;
use App\Providers\ClassroomServiceProvider;
use App\Providers\StudentServiceProvider;
use Rakutentech\LaravelRequestDocs\LaravelRequestDocsMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
		web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
		$middleware->alias([
			'role' => EnsureUserHasRole::class,
		]);

		// CORS Configuration
		$middleware->statefulApi();
		$middleware->prependToGroup('api', HandleCors::class);

		$middleware->appendToGroup('api', LaravelRequestDocsMiddleware::class);
    })
    ->withProviders([
        \Rakutentech\LaravelRequestDocs\LaravelRequestDocsServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
