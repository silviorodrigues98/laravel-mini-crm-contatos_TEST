<?php

namespace App\Providers;

use App\Infrastructure\Models\Contact;
use App\Infrastructure\Observers\ContactObserver;
use App\Infrastructure\Repositories\EloquentContactRepository;
use Domain\Repositories\ContactRepositoryInterface;
use Domain\Services\ScoreCalculator;
use Domain\Services\Scoring\EmailDomainScoringStrategy;
use Domain\Services\Scoring\NameLengthScoringStrategy;
use Domain\Services\Scoring\PhoneDddScoringStrategy;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ContactRepositoryInterface::class,
            EloquentContactRepository::class,
        );

        $this->app->bind(ScoreCalculator::class, function ($app) {
            return new ScoreCalculator([
                $app->make(EmailDomainScoringStrategy::class),
                $app->make(NameLengthScoringStrategy::class),
                $app->make(PhoneDddScoringStrategy::class),
            ]);
        });
    }

    public function boot(): void
    {
        Contact::observe(ContactObserver::class);
    }
}
