<?php

namespace Lambda;

use Illuminate\Support\ServiceProvider;

class LambdaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Lambda::class, function () {
            return new Lambda();
        });
    }

    public function boot()
    {

        $this->loadViewsFrom(__DIR__ . '/Agent/Views', 'agent');
        $this->loadViewsFrom(__DIR__ . '/Puzzle/Views', 'puzzle');
        $this->loadViewsFrom(__DIR__ . '/Template/Views', 'template');

        $this->loadRoutesFrom(__DIR__ . '/Agent/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Puzzle/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Krud/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Process/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Notify/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Translation/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Dataform/Editor/routes.php');

        // H5P модуль нь brnysn/laravel-h5p багцтай хост дээр л идэвхжинэ
        if (\Lambda\H5P\H5P::available()) {
            \Lambda\H5P\H5P::bind($this->app);
            \Lambda\H5P\H5P::ensureEditorLanguage();
            \Lambda\H5P\H5P::ensurePublicLibraryLinks();
            $this->loadRoutesFrom(__DIR__ . '/H5P/routes.php');
            // 'h5p' namespace-ийг багцынхаас манай lang хавтас руу шилжүүлнэ (en + mn)
            $this->loadTranslationsFrom(__DIR__ . '/H5P/lang', 'h5p');
        }

        $this->publishes([
            __DIR__ . '/config/lambda-config.php' => config_path('lambda.php'),
        ]);
    }
}
