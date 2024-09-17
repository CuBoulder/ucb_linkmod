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
        $sessionfound = false;

        // checking cookies to see if we have a vaid session cookie.
        foreach($request->cookies->all() as $name => $value)
        {
            if(str_starts_with($name, 'SESS'))
            {
                $sessionfound = true;
            }
            if(str_starts_with($name, 'SSESS'))
            {
                $sessionfound = true;
            }
        }
        // if there's a session cookie we know the user is logged in and we don't want to modify anything.  
        if($sessionfound)
        {
            // just return what would normally have been returned without any changes
            return $this->httpKernel->handle($request, $type, $catch);
        }

        if (PHP_SAPI !== 'cli')
        {
            // functionality is dependent upon the Pantheon Domain Masking Module...
            // grab required configuration for site paths from there
            $config = $this->configFactory->get('pantheon_domain_masking.settings');
            $subpath = $config->get('subpath', '');
            $enabled = \filter_var($config->get('enabled', 'no'), FILTER_VALIDATE_BOOLEAN);
            if ($enabled === TRUE)
            {
                $response = $this->httpKernel->handle($request, $type, $catch);

                // we only want to to deal with text/html and leave other content types alone (e.g. js, css, json)
                if(!is_null($response->headers->get('Content-Type'))) {
                    if (str_starts_with($response->headers->get('Content-Type'), 'text/html') && count($request->query->all()) === 0) {
//                        \Drupal::logger('ucb_linkmod')->info(print_r($response->headers->all(), true));

                        error_reporting(E_ALL & ~E_DEPRECATED);
                        $html = new HtmlDocument();

                        $html->load($response->getContent());


                        // look for anchor tags inside of the main content body
                        foreach ($html->find('.ucb-page-content a') as $key => $element) {
                            $newelement = $html->find('.ucb-page-content a', $key);

                            $href = $newelement->href;

                            // if we have an anchor that isn't blank
                            if ($href != false && gettype($href) == 'string') {
                                // Look for p-value patterns in a absolute path (e.g. https://www.colorado.edu/p1ab3d5fgh9j)
                                if (preg_match('/https:\/\/www\.colorado\.edu\/p1[0-9 a-z]{10}/', $href)) {
                                    // strip off the first 37 characters which will leave just the relative content path starting with a /
                                    $href = substr($href, 37);
                                    // the next section of code should match and pick this up to finish correcting the URL now

                                }
                                if (preg_match('/pantheonsite\.io/', $href)) {
                                    // strip off the first 37 characters which will leave just the relative content path starting with a /
                                    $href = explode('pantheonsite.io', $href)[1];
                                    // the next section of code should match and pick this up to finish correcting the URL now

                                }
                                // we're looking to correct all relative links at this point while ignoring anything with a protocol
                                if (str_starts_with($href, '/')) {
                                    // look for the p-value and strip it off of the URL if it is detected
                                    if (preg_match('/p1[0-9 a-z]{10}/', $href)) {
                                        $href = substr($href, 13);
                                    }

                                    // find any relative links that don't have the site's subpath on them and prepend that
                                    if (!str_starts_with($href, '/' . $subpath . '/')) {
                                        $href = '/' . $subpath . $href;
                                    }
                                }
                            }

                            // links should be correctly formatted now.
                            $newelement->href = $href;
                        }

                        // we've adjusted the length of the content so we'll need to recalculate the Content-Length header
                        // before we return the updated response
                        $response->setContent((string)$html);
                        $response->headers->set('Content-Length', (string)strlen((string)$html));

//                        \Drupal::logger('ucb_linkmod')->info((string)$html);

                        error_reporting(E_ALL);

                        return $response;

                    }
                }
            }
        }
        return $this->httpKernel->handle($request, $type, $catch);
    }
}
