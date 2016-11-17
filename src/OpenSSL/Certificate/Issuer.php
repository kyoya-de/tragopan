<?php

namespace KyoyaDe\Tragopan\OpenSSL\Certificate;

use Pimple\Container;
use Symfony\Component\Process\Process;

class Issuer
{
    private        $container;

    private static $fieldMap = [
        'country'            => 'C',
        'state'              => 'ST',
        'locality'           => 'L',
        'organization'       => 'O',
        'organizationalUnit' => 'OU',
        'name'               => 'CN',
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $certConfig
     * @param string $hostname
     *
     * @return array
     */
    public function issueCert($certConfig, $hostname)
    {
        if (!isset($certConfig['name'])) {
            throw new \InvalidArgumentException('You must provide at least the Common Name (CN)!');
        }

        $certDir = "{$this->container['cert.base_dir']}/{$hostname}";
        if (!@mkdir($certDir, 0770, true) && !is_dir($certDir)) {
            throw new \RuntimeException("Can't create certification directory '{$certDir}'!");
        }

        $configContent = $this->renderConfig($certConfig, $hostname);
        file_put_contents("{$certDir}/openssl-server.cnf", $configContent);

        $subjects = [];
        foreach ($certConfig as $key => $value) {
            if (isset(static::$fieldMap[$key])) {
                $mappedField = self::$fieldMap[$key];
                $subjects[] = "{$mappedField}={$value}";
            }
        }

        $subject = escapeshellarg('/' . implode('/', $subjects));

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

        return [
            'key'  => "{$certDir}/serverkey.pem",
            'cert' => "{$certDir}/servercert.pem",
        ];
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
