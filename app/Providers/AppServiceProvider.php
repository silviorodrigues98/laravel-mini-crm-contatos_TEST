<?php

namespace App\Providers;

use App\Infrastructure\Models\Contact;
use App\Infrastructure\Observers\ContactObserver;
use App\Infrastructure\Repositories\EloquentContactRepository;
use Domain\Repositories\ContactRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ContactRepositoryInterface::class,
            EloquentContactRepository::class,
        );
    }

    public function boot(): void
    {
        Contact::observe(ContactObserver::class);
    }
}
