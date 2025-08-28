<?php

namespace App\Providers;

use App\Repository\TripRepository;
use App\Repository\UserRepository;
use App\Repository\RouteRepository;
use App\Services\SocialAuthService;


use App\Repository\LocationRepository;
use Illuminate\Support\ServiceProvider;
use App\Repository\TripStationRepository;
use App\Repository\UserProviderRepository;
use App\Repository\SeatLayoutTemplateRepository;
use App\Repository\Interfaces\TripRepositoryInterface;

use App\Repository\Interfaces\UserRepositoryInterface;
use App\Repository\Interfaces\RouteRepositoryInterface;
use App\Repository\Interfaces\LocationRepositoryInterface;
use App\Repository\Interfaces\TripStationRepositoryInterface;
use App\Repository\Interfaces\SeatLayoutTemplateRepositoryInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepository::class, UserRepository::class);
        $this->app->bind(UserProviderRepository::class, UserProviderRepository::class);


        $this->app->bind(SocialAuthService::class, function ($app) {
            return new SocialAuthService(
                $app->make(UserRepository::class),
                $app->make(UserProviderRepository::class)
            );
        });

        $this->app->bind(LocationRepositoryInterface::class,  LocationRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(RouteRepositoryInterface::class, RouteRepository::class);
        $this->app->bind(TripRepositoryInterface::class, TripRepository::class);
        $this->app->bind(TripStationRepositoryInterface::class, TripStationRepository::class);

        $this->app->bind(SeatLayoutTemplateRepositoryInterface::class, SeatLayoutTemplateRepository::class);
    }


    public function boot(): void
    {
        //
    }
}
