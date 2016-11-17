<?php
namespace KyoyaDe\Tragopan;

use KyoyaDe\Tragopan\Controller\CertificatesController;
use Pimple\Container;
use Silex\Application as BaseApplication;
use Silex\Provider\RoutingServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\VarDumperServiceProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class Application extends BaseApplication
{
    public function __construct(array $values = [])
    {
        $values['environment'] = $values['debug'] ? 'dev' : 'prod';

        $values['ca.base_dir'] = $values['kernel.root_dir'] . '/var/ca';
        $values['cert.base_dir'] = $values['kernel.root_dir'] . '/var/certs';
        $values['kernel.cache_dir'] = $values['kernel.root_dir'] . '/var/cache';

        parent::__construct($values);
    }

    public function boot()
    {
        $this['cache.lifetime'] = function (Container $c) {
            return $c['debug'] ? 1 : 0;
        };

        $this['cache'] = function (Container $c) {
            return new FilesystemAdapter($c['environment'], $c['cache.lifetime'], $c['kernel.cache_dir']);
        };

        $this->register(
            new TwigServiceProvider(),
            [
                'twig.path' => $this['kernel.root_dir'] . '/resources/templates'
            ]
        );

        $this->register(new RoutingServiceProvider());

        $this['ca.config'] = function ($c) {
            return Yaml::parse(
                file_get_contents($c['kernel.root_dir'] . '/var/config/parameters.yml')
            )['config'];
        };

        $this['controller.certificates'] = function () {
            return new CertificatesController();
        };

        $this->before(function (Request $request) {
            if ('json' === $request->getContentType()) {
                $requestBody = $request->getContent();
                $request->request = new ParameterBag((array) json_decode($requestBody, true));
            }
        }, Application::EARLY_EVENT);

        return parent::boot();
    }

    public function addRoutes()
    {
        $this->get('/cert/{id}', [$this['controller.certificates'], 'download'])->bind('downloadCert');
        $this->post('/cert', [$this['controller.certificates'], 'issueNew'])->bind('issueCert');
        $this->get('/', function() {
            return new Response('<h2>Welcome to <em>Tragopan</em></h2><blockquote>Nothing to show or to do here!</blockquote>');
        })->bind('home');
        $app = $this;
        $this->post('/', function(Request $request) use ($app) {
            $response = '<h2>Welcome to <em>Tragopan</em></h2><blockquote>Nothing to show or to do here!</blockquote>';
            ob_start();
            var_dump($request->request->all());
            $response .= ob_get_clean();
            return new Response($response);
        })->bind('home');
    }

}
