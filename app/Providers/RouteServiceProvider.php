<?php

namespace Coyote\Providers;

use Coyote\Repositories\Contracts\BlockRepositoryInterface;
use Coyote\Repositories\Contracts\FirewallRepositoryInterface;
use Coyote\Repositories\Contracts\ForumRepositoryInterface;
use Coyote\Repositories\Contracts\GroupRepositoryInterface;
use Coyote\Repositories\Contracts\MicroblogRepositoryInterface;
use Coyote\Repositories\Contracts\PastebinRepositoryInterface;
use Coyote\Repositories\Contracts\PostRepositoryInterface;
use Coyote\Repositories\Contracts\TopicRepositoryInterface;
use Coyote\Repositories\Contracts\UserRepositoryInterface;
use Coyote\Repositories\Contracts\WikiRepositoryInterface;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Routing\Router;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to the controller routes in your routes file.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'Coyote\Http\Controllers';

    /**
     * @var Router
     */
    protected $router;

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->router->pattern('id', '[0-9]+');
        $this->router->pattern('wiki', '[0-9]+');
        $this->router->pattern('block', '[0-9]+');
        $this->router->pattern('group', '[0-9]+');
        $this->router->pattern('firewall', '[0-9]+');
        $this->router->pattern('pastebin', '[0-9]+');
        $this->router->pattern('microblog', '[0-9]+');
        $this->router->pattern('topic', '[0-9]+');
        $this->router->pattern('user', '[0-9]+');
        $this->router->pattern('post', '[0-9]+');
        $this->router->pattern('job', '[0-9]+');

        $this->router->pattern('forum', '[A-Za-ząęśćłźżóń\-\_\/\.\+]+');
        $this->router->pattern('tag', '([a-ząęśżźćółń0-9\-\.\#\+])+');
        $this->router->pattern('slug', '.*');
        $this->router->pattern('path', '.*'); // being used on wiki routes

        $this->router->model('user', UserRepositoryInterface::class);
        $this->router->model('post', PostRepositoryInterface::class);
        $this->router->model('topic', TopicRepositoryInterface::class);
        $this->router->model('pastebin', PastebinRepositoryInterface::class);
        $this->router->model('microblog', MicroblogRepositoryInterface::class);
        $this->router->model('wiki', WikiRepositoryInterface::class);
        $this->router->model('pastebin', PastebinRepositoryInterface::class);
        $this->router->model('firewall', FirewallRepositoryInterface::class);
        $this->router->model('group', GroupRepositoryInterface::class);
        $this->router->model('block', BlockRepositoryInterface::class);

        $this->router->bind('forum', function ($slug) {
            return $this->app->make(ForumRepositoryInterface::class, [$this->app])->where('slug', $slug)->firstOrFail();
        });

        parent::boot();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->router = $this->app->make(Router::class);
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapWebRoutes();
        $this->mapApiRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        $this->router->group([
            'namespace' => $this->namespace, 'middleware' => 'web',
        ], function () {
            require base_path('routes/auth.php');
            require base_path('routes/misc.php');
            require base_path('routes/forum.php');
            require base_path('routes/job.php');
            require base_path('routes/microblog.php');
            require base_path('routes/user.php');
            require base_path('routes/profile.php');
            require base_path('routes/pastebin.php');
            require base_path('routes/adm.php');
            require base_path('routes/wiki.php'); // must be at the end
        });
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        //
    }
}
