<?php

namespace Coyote\Http\Middleware;

use Closure;
use Illuminate\Container\Container as App;
use Coyote\Repositories\Contracts\BlockRepositoryInterface;
use Coyote\Repositories\Contracts\FirewallRepositoryInterface;
use Coyote\Repositories\Contracts\GroupRepositoryInterface;
use Coyote\Repositories\Contracts\MicroblogRepositoryInterface;
use Coyote\Repositories\Contracts\PastebinRepositoryInterface;
use Coyote\Repositories\Contracts\TopicRepositoryInterface;
use Coyote\Repositories\Contracts\WikiRepositoryInterface;

class DefaultBindings
{
    /**
     * @var array
     */
    protected $default = [
        'topic' => TopicRepositoryInterface::class,
        'microblog' => MicroblogRepositoryInterface::class,
        'wiki' => WikiRepositoryInterface::class,
        'pastebin' => PastebinRepositoryInterface::class,
        'firewall' => FirewallRepositoryInterface::class,
        'group' => GroupRepositoryInterface::class,
        'block' => BlockRepositoryInterface::class
    ];

    /**
     * @var App
     */
    protected $app;

    /**
     * Create the event listener.
     *
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route();
        $optional = $this->getOptionalParameters($route->getUri());

        foreach ($optional as $parameter) {
            if (isset($this->default[$parameter]) && null === $route->getParameter($parameter)) {
                $model = $this->app->make($this->default[$parameter])->makeModel();
                $route->setParameter($parameter, $model);
            }
        }

        return $next($request);
    }

    /**
     * @param string $uri
     * @return array
     */
    protected function getOptionalParameters($uri)
    {
        $segments = explode('/', $uri);
        $optional = [];

        foreach ($segments as $segment) {
            $len = strlen($segment);
            if ($len > 0 && $segment[0] === '{' && $segment[$len - 1] === '}' && $segment[$len - 2] === '?') {
                $optional[] = substr($segment, 1, -2);
            }
        }

        return $optional;
    }
}
