<?php

namespace KyoyaDe\Tragopan\Command;

use Pimple\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;

class CreateCACommand extends Command
{
    /**
     * @var Container
     */
    private $container;

    /**
     * CreateCACommand constructor.
     *
     * @param null|string $name
     * @param Container   $container
     *
     * @throws LogicException
     */
    public function __construct($name, Container $container)
    {
        $this->container = $container;
        parent::__construct($name);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName('ca:create')
            ->setDescription('Creates a new CA key and certificate.');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws ProcessFailedException
     * @throws RuntimeException
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     * @throws \RuntimeException
     * @throws ParseException
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $openSslConfig = $this->renderConfig();

        if (!@mkdir($this->container['ca.base_dir']) && !is_dir($this->container['ca.base_dir'])) {
            throw new \RuntimeException("Can't create directory for CA files!");
        }

        $certFile = $this->container['ca.base_dir'] . '/cacert.pem';
        if (file_exists($certFile)) {
            $output->writeln(
                "WARNING: File '{$certFile}' already exists. If you create a new CA, it will overwrite the old one!"
            );
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Are you sure to create a new CA? [y/N] ', false);
            if (!$helper->ask($input, $output, $question)) {
                return;
            }
        }

        $confFile = $this->container['ca.base_dir'] . '/openssl-ca.cnf';
        file_put_contents($confFile, $openSslConfig);

        touch("{$this->container['ca.base_dir']}/index.txr");
        file_put_contents("{$this->container['ca.base_dir']}/serial.txt", "01\n");

        $caConfig = $this->container['ca.config'];
        $subject  = escapeshellarg(
            sprintf(
                '/C=%s/ST=%s/L=%s/O=%s/OU=%s/CN=%s',
                $caConfig['ca.country'],
                $caConfig['ca.state'],
                $caConfig['ca.locality'],
                $caConfig['ca.organization'],
                $caConfig['ca.organizationalUnit'],
                $caConfig['ca.name']
            )
        );

        $caPass  = escapeshellarg($caConfig['ca.secret']);

        $output->writeln('Generating key and certificate for your CA.');
        $process = new Process(
            "openssl req -x509 -config {$confFile} -newkey rsa:4096 -sha256 -passin pass:{$caPass} " .
            "-out {$certFile} -outform PEM  -passout pass:{$caPass} -subj {$subject}",
            $this->container['ca.base_dir']
        );

        $process->mustRun();
    }

    /**
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     * @throws ParseException
     *
     * @return string
     */
    private function renderConfig()
    {
        /** @var \Twig_Environment $twig */
        $twig = $this->container['twig'];

        return $twig->render('openssl-ca.cnf.twig', $this->getCATemplateConfig());
    }

    /**
     * @throws ParseException
     *
     * @return array
     */
    private function getCATemplateConfig()
    {
        $config = $this->container['ca.config'];

        return [
            'ca'     => [
                'base_dir'           => $this->container['ca.base_dir'],
                'country'            => $config['ca.country'],
                'state'              => $config['ca.state'],
                'locality'           => $config['ca.locality'],
                'organization'       => $config['ca.organization'],
                'organizationalUnit' => $config['ca.organizationalUnit'],
                'name'               => $config['ca.name'],
                'email'              => $config['ca.email'],
            ],
            'kernel' => [
                'cache_dir' => $this->container['kernel.cache_dir'],
            ],
        ];
    }
}
