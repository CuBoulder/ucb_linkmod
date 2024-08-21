<?php

declare(strict_types=1);

namespace Drupal\ucb_linkmod;

use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use simplehtmldom\HtmlDocument;

/**
 * @todo Add a description for the middleware.
 */
final class UcbLinkmodMiddleware implements HttpKernelInterface
{

    /**
     * Constructs an UcbLinkmodMiddleware object.
     */

    /**
     * The decorated kernel.
     *
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    protected $httpKernel;

    /**
     * The site settings.
     *
     * @var \Drupal\Core\Config\ConfigFactory
     */
    protected $configFactory;

    /**
     * The original request when this middleware was first run.
     *
     * @var \Symfony\Component\HttpFoundation\Request;
     */
    protected $origRequest;

    /**
     * Create a new StackOptionsRequest instance.
     *
     * @param \Symfony\Component\HttpKernel\HttpKernelInterface $http_kernel
     *   The decorated kernel.
     * @param \Drupal\Core\Config\ConfigFactory $config_factory
     *   The site settings.
     */
    public function __construct(HttpKernelInterface $http_kernel, ConfigFactory $config_factory)
    {
        $this->httpKernel = $http_kernel;
        $this->configFactory = $config_factory;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = TRUE): Response
    {
        if (PHP_SAPI !== 'cli')
        {
            $config = $this->configFactory->get('pantheon_domain_masking.settings');
            $domain = $config->get('domain');
            $subpath = $config->get('subpath', '');
//            return $this->httpKernel->handle($request, $type, $catch);
            $enabled = \filter_var($config->get('enabled', 'no'), FILTER_VALIDATE_BOOLEAN);
            if ($enabled === TRUE)
            {

//                \Drupal::logger('ucb_linkmod')->info($domain);
//                \Drupal::logger('ucb_linkmod')->info($subpath);

                $response = $this->httpKernel->handle($request, $type, $catch);

                \Drupal::logger('ucb_linkmod')->info($response->getContent());

                $html = new HtmlDocument();

                $html->load($response->getContent());

                foreach($html->find('.ucb-page-content a') as $key=>$element) {
                    $newelement = $html->find('.ucb-page-content a', $key);


                    $href = $newelement->href;
                    if($href != false)
                    {
                        if(preg_match('/https:\/\/www\.colorado\.edu\/p1[0-9 a-z]{10}/', $href))
                        {
                            $href = substr($href, 37);

                        }

                        if (str_starts_with($href, '/'))
                        {
                            if(preg_match('/p1[0-9 a-z]{10}/', $href))
                            {
                                $href = substr($href, 13);
                            }

                            if (!str_starts_with($href, '/' . $subpath . '/'))
                            {
                                $href = '/' . $subpath . $href;
                            }
                        }
                    }

                    $newelement->href = $href;
                }



                \Drupal::logger('ucb_linkmod')->info((string)$html);

                $response->setContent((string)$html);
                $response->headers->set('Content-Length', (string)strlen((string)$html));

    //            $response->setContent('test');

                return $response;
            }



        }

        return $this->httpKernel->handle($request, $type, $catch);


    }

}
