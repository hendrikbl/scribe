<?php

namespace Knuckles\Scribe\Commands;

use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Knuckles\Scribe\Extracting\Generator;
use Knuckles\Scribe\Matching\Match;
use Knuckles\Scribe\Matching\RouteMatcherInterface;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Tools\Flags;
use Knuckles\Scribe\Tools\Utils;
use Knuckles\Scribe\Writing\Writer;
use Mpociot\Reflection\DocBlock;
use ReflectionClass;
use ReflectionException;
use Shalvah\Clara\Clara;

class GenerateDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "scribe:generate
                            {--force : Discard any changes you've made to the Markdown files}
    ";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate your API documentation from existing Laravel routes.';

    /**
     * @var DocumentationConfig
     */
    private $docConfig;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var Clara
     */
    private $clara;

    /**
     * Execute the console command.
     *
     * @param RouteMatcherInterface $routeMatcher
     *
     * @return void
     */
    public function handle(RouteMatcherInterface $routeMatcher)
    {
        $this->bootstrap();

        $routes = $routeMatcher->getRoutes($this->docConfig->get('routes'), $this->docConfig->get('router'));

        $generator = new Generator($this->docConfig);
        $parsedRoutes = $this->processRoutes($generator, $routes);

        $groupedRoutes = collect($parsedRoutes)
            ->groupBy('metadata.groupName')
            ->sortBy(static function ($group) {
                /* @var $group Collection */
                return $group->first()['metadata']['groupName'];
            }, SORT_NATURAL);
        $writer = new Writer(
            $this->docConfig,
            $this->option('force'),
            $this->clara
        );
        $writer->writeDocs($groupedRoutes);
    }

    /**
     * @param \Knuckles\Scribe\Extracting\Generator $generator
     * @param Match[] $routes
     *
     * @return array
     *@throws \ReflectionException
     *
     */
    private function processRoutes(Generator $generator, array $routes)
    {
        $parsedRoutes = [];
        foreach ($routes as $routeItem) {
            $route = $routeItem->getRoute();
            /** @var Route $route */
            $messageFormat = '%s route: [%s] %s';
            $routeMethods = implode(',', $generator->getMethods($route));
            $routePath = $generator->getUri($route);

            $routeControllerAndMethod = Utils::getRouteClassAndMethodNames($route->getAction());
            if (! $this->isValidRoute($routeControllerAndMethod)) {
                $this->clara->warn(sprintf($messageFormat, 'Skipping invalid', $routeMethods, $routePath));
                continue;
            }

            if (! $this->doesControllerMethodExist($routeControllerAndMethod)) {
                $this->clara->warn(sprintf($messageFormat, 'Skipping', $routeMethods, $routePath) . ': Controller method does not exist.');
                continue;
            }

            if (! $this->isRouteVisibleForDocumentation($routeControllerAndMethod)) {
                $this->clara->warn(sprintf($messageFormat, 'Skipping', $routeMethods, $routePath) . ': @hideFromAPIDocumentation was specified.');
                continue;
            }

            try {
                $parsedRoutes[] = $generator->processRoute($route, $routeItem->getRules());
                $this->clara->info(sprintf($messageFormat, 'Processed', $routeMethods, $routePath));
            } catch (\Exception $exception) {
                $this->clara->warn(sprintf($messageFormat, 'Skipping', $routeMethods, $routePath) . '- Exception ' . get_class($exception) . ' encountered : ' . $exception->getMessage());
            }
        }

        return $parsedRoutes;
    }

    /**
     * @param array $routeControllerAndMethod
     *
     * @return bool
     */
    private function isValidRoute(array $routeControllerAndMethod = null)
    {
        if (is_array($routeControllerAndMethod)) {
            [$classOrObject, $method] = $routeControllerAndMethod;
            if (Utils::isInvokableObject($classOrObject)) {
                return true;
            }
            $routeControllerAndMethod = $classOrObject . '@' . $method;
        }

        return ! is_callable($routeControllerAndMethod) && ! is_null($routeControllerAndMethod);
    }

    /**
     * @param array $routeControllerAndMethod
     *
     * @throws ReflectionException
     *
     * @return bool
     */
    private function doesControllerMethodExist(array $routeControllerAndMethod)
    {
        [$class, $method] = $routeControllerAndMethod;
        $reflection = new ReflectionClass($class);

        if (! $reflection->hasMethod($method)) {
            return false;
        }

        return true;
    }

    /**
     * @param array $routeControllerAndMethod
     *
     * @throws ReflectionException
     *
     * @return bool
     */
    private function isRouteVisibleForDocumentation(array $routeControllerAndMethod)
    {
        $comment = Utils::reflectRouteMethod($routeControllerAndMethod)->getDocComment();

        if ($comment) {
            $phpdoc = new DocBlock($comment);

            return collect($phpdoc->getTags())
                ->filter(function ($tag) {
                    return $tag->getName() === 'hideFromAPIDocumentation';
                })
                ->isEmpty();
        }

        return true;
    }

    public function bootstrap(): void
    {
        // Using a global static variable here, so fuck off if you don't like it.
        // Also, the --verbose option is included with all Artisan commands.
        Flags::$shouldBeVerbose = $this->option('verbose');

        $this->clara = clara('knuckleswtf/scribe', Flags::$shouldBeVerbose)
            ->useOutput($this->output)
            ->only();

        $this->docConfig = new DocumentationConfig(config('scribe'));
        $this->baseUrl = $this->docConfig->get('base_url') ?? config('app.url');

        // Force root URL so it works in Postman collection
        URL::forceRootUrl($this->baseUrl);
    }
}
