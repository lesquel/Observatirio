<?php

namespace App\Providers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticuloModel;
use App\Infrastructure\Persistence\Eloquent\Models\DatasetModel;
use App\Infrastructure\Persistence\Eloquent\Models\ReporteModel;
use App\Infrastructure\Persistence\Eloquent\Models\VariableMetadatoModel;
use App\Models\User;
use App\Policies\ArticuloPolicy;
use App\Policies\DatasetPolicy;
use App\Policies\ReportePolicy;
use App\Policies\UserPolicy;
use App\Policies\VariablePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ArticuloModel::class => ArticuloPolicy::class,
        ReporteModel::class => ReportePolicy::class,
        DatasetModel::class => DatasetPolicy::class,
        VariableMetadatoModel::class => VariablePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
