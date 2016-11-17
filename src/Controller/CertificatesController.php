<?php

namespace KyoyaDe\Tragopan\Controller;

use KyoyaDe\Tragopan\Application;
use KyoyaDe\Tragopan\OpenSSL\Certificate\Issuer;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Generator\UrlGenerator;

class CertificatesController
{
    private static $requiredFields = [
        'country',
        'state',
        'locality',
        'organization',
        'organizationalUnit',
        'name',
    ];

    public function issueNew(Application $app, Request $request)
    {
        $this->validateIssueRequest($request);

        $issuer     = new Issuer($app);
        $certReq    = $request->request->get('cert');
        $hostname   = $request->request->get('host');
        $certConfig = [
            'cert.country'            => $certReq['country'],
            'cert.state'              => $certReq['state'],
            'cert.locality'           => $certReq['locality'],
            'cert.organization'       => $certReq['organization'],
            'cert.organizationalUnit' => $certReq['organizationalUnit'],
            'cert.name'               => $certReq['name'],
        ];

        if (isset($certReq['email'])) {
            $certConfig['cert.email'] = $certReq['email'];
        }

        $files = $issuer->issueCert($certConfig, $hostname);

        $certDownloadId = $this->createDownloadId();
        $keyDownloadId  = $this->createDownloadId();

        /** @var FilesystemAdapter $cache */
        $cache = $app['cache'];

        $cacheItem = $cache->getItem("download.{$certDownloadId}");
        $cacheItem->set($files['cert']);
        $cacheItem->expiresAfter(new \DateInterval('PT1H'));
        $cache->save($cacheItem);

        $cacheItem = $cache->getItem("download.{$keyDownloadId}");
        $cacheItem->set($files['key']);
        $cacheItem->expiresAfter(new \DateInterval('PT1H'));
        $cache->save($cacheItem);

        /** @var UrlGenerator $urlGenerator */
        $urlGenerator = $app['url_generator'];

        return new JsonResponse(
            [
                'key'  => $urlGenerator->generate('downloadCert', ['id' => $keyDownloadId]),
                'cert' => $urlGenerator->generate('downloadCert', ['id' => $certDownloadId]),
            ]
        );
    }

    public function download(Application $app, Request $request, $id)
    {
        $response = new Response('', 404);
        if ($request->headers->has('x-ca-api-key')) {
            if ($request->headers->get('x-ca-api-key') === $app['ca.config']['ca.api-key']) {
                $caCertResponse = new BinaryFileResponse("{$app['ca.base_dir']}/cacert.pem");
                $caCertResponse->prepare($request);
                $caCertResponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'cacert.pem');

                return $caCertResponse;
            }
        }

        /** @var FilesystemAdapter $cache */
        $cache     = $app['cache'];
        $cacheItem = $cache->getItem("download.{$id}");

        if ($cacheItem->isHit()) {
            $filename = $cacheItem->get();
            $response = new BinaryFileResponse($filename);
            $response->prepare($request);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($filename));

            $cache->deleteItem("download.{$id}");
        }

        return $response;
    }

    private function createDownloadId()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(10));
        }

        mt_srand();
        $pool       = array_merge(range(0, 9), range('a', 'f'));
        $maxIndex   = count($pool) - 1;
        $downloadId = '';
        for ($i = 0; $i < 10; $i++) {
            $downloadId .= $pool[mt_rand(0, $maxIndex)];
        }

        return $downloadId;
    }

    private function validateIssueRequest(Request $request)
    {
        if (null !== ($certReq = $request->request->get('cert'))) {
            foreach (static::$requiredFields as $requiredField) {
                if (!isset($certReq[$requiredField])) {
                    throw new \InvalidArgumentException("The field 'cert[{$requiredField}]' is missing!");
                }
            }
        }

        if (null !== ($hostname = $request->request->get('host'))) {
            return;
        }

        throw new \InvalidArgumentException('Some required parameters are missing!');
    }
}
