<?php

namespace KyoyaDe\Tragopan\OpenSSL\Certificate;

use Pimple\Container;
use Symfony\Component\Process\Process;

class Issuer
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function issueCert($certConfig, $hostname)
    {
        $certDir = "{$this->container['cert.base_dir']}/{$hostname}";
        if (!@mkdir($certDir) && !is_dir($certDir)) {
            throw new \RuntimeException("Can't create certification directory '{$certDir}'!");
        }

        $configContent = $this->renderConfig($certConfig, $hostname);
        file_put_contents("{$certDir}/openssl-server.cnf", $configContent);

        $subject = escapeshellarg(
            sprintf(
                '/C=%s/ST=%s/L=%s/O=%s/OU=%s/CN=%s',
                $certConfig['cert.country'],
                $certConfig['cert.state'],
                $certConfig['cert.locality'],
                $certConfig['cert.organization'],
                $certConfig['cert.organizationalUnit'],
                $certConfig['cert.name']
            )
        );

        $caConfig = $this->container['ca.config'];
        $caPass   = escapeshellarg($caConfig['ca.secret']);
        $cmd      = "openssl req -config openssl-server.cnf -newkey rsa:2048 -sha256 " .
                    "-nodes -out servercert.csr -outform PEM -subj {$subject}";

        $process = new Process($cmd, $certDir);
        $process->mustRun();

        $caConfFile = "{$this->container['ca.base_dir']}/openssl-ca.cnf";

        $cmd = "openssl ca -config {$caConfFile} -batch -passin pass:{$caPass} -policy signing_policy -days 365 " .
               "-extensions signing_req -out servercert.pem -infiles servercert.csr";

        $process = new Process($cmd, $certDir);
        $process->mustRun();
    }

    private function renderConfig($certConfig, $hostname)
    {
        $certDir        = "{$this->container['cert.base_dir']}/{$hostname}";
        $templateConfig = [
            'cert' => [
                'base_dir'     => $certDir,
                'country'      => $certConfig['cert.country'],
                'state'        => $certConfig['cert.state'],
                'locality'     => $certConfig['cert.locality'],
                'organization' => $certConfig['cert.organization'],
                'name'         => $certConfig['cert.name'],
                'email'        => $certConfig['cert.email'],
                'dns'          => [
                    "{$hostname}",
                    "*.{$hostname}",
                ],
            ],
        ];

        /** @var \Twig_Environment $twig */
        $twig = $this->container['twig'];

        return $twig->render('openssl-server.cnf.twig', $templateConfig);
    }
}
