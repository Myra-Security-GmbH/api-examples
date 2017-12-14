<?php

namespace Myracloud\API\Command;

use Myracloud\API\Service\MyracloudService;
use Myracloud\API\Util\Normalizer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CacheClear
 *
 * @package Myracloud\API\Command
 */
class CacheClearCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('myracloud:api:cacheClear');
        $this->addArgument('apiKey', InputArgument::REQUIRED, 'Api key to authenticate against Myra API.', null);
        $this->addArgument('secret', InputArgument::REQUIRED, 'Secret to authenticate against Myra API.', null);
        $this->addArgument('fqdn', InputArgument::REQUIRED, 'Domain that should be used to clear the cache.');

        $this->addOption('cleanupRule', null, InputOption::VALUE_REQUIRED, 'Rule that describes which files should be removed from the cache.', null);
        $this->addOption('recursive', 'r', InputOption::VALUE_NONE, 'Should the rule applied recursively.');

        $this->setDescription('CacheClear commands allows you to do a cache clear via Myra API.');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->resolver->setDefaults([
            'noCheckCert' => false,
            'apiKey'      => null,
            'secret'      => null,
            'fqdn'        => null,
            'cleanupRule' => null,
            'language'    => self::DEFAULT_LANGUAGE,
            'apiEndpoint' => self::DEFAULT_API_ENDPOINT
        ]);

        $this->resolver->setNormalizer('fqdn', Normalizer::normalizeFqdn());
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->resolveOptions($input, $output);

        $content = [
            'fqdn'      => $this->options['fqdn'],
            'resource'  => $this->options['cleanupRule'],
            'recursive' => $input->getOption('recursive')
        ];

        $ret = $this->service->cacheClear(MyracloudService::METHOD_CREATE, $this->options['fqdn'], $content);

        if ($output->isVerbose()) {
            print_r($ret);
        }

        if ($ret) {
            $output->writeln('<fg=green;options=bold>Success</>');
        }
    }
}
