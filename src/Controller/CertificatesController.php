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

class CertificatesController
{
    public function issueNew(Application $app, Request $request)
    {
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
            'cert.email'              => $certReq['email'],
        ];

        $issuer->issueCert($certConfig, $hostname);


        return new JsonResponse(
            [
                'it' => [
                    'works' => true,
                ],
            ]
        );
    }

    public function download(Application $app, Request $request, $id)
    {
        $response = new Response('', 403);
        if ($request->headers->has('x-ca-api-key')) {
            if ($request->headers->get('x-ca-api-key') === $app['ca.api-key']) {
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
            // TODO Create an archive and prepare the file response.

            $cache->deleteItem("download.{$id}");
        }

        return $response;
    }
}
