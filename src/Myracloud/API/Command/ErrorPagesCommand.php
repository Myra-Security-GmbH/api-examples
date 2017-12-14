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
 * Class ErrorPagesCommand
 *
 * @package Myracloud\API\Command
 */
class ErrorPagesCommand extends AbstractCommand
{
    const TYPE_OPERATION_UPLOAD = 'upload';
    const TYPE_OPERATION_DELETE = 'delete';

    private static $availableCodes = [429, 500, 502, 503, 504];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('myracloud:api:errorPages');
        $this->addArgument('apiKey', InputArgument::REQUIRED, 'Api key to authenticate against Myra API.', null);
        $this->addArgument('secret', InputArgument::REQUIRED, 'Secret to authenticate against Myra API.', null);
        $this->addArgument('fqdn', InputArgument::REQUIRED, 'Domain that should be used to clear the cache.');

        $this->addOption('errorCodes', 'e', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Error codes to append given errorpage');
        $this->addOption('contentFile', 'f', InputOption::VALUE_REQUIRED, 'HTML file that contains the error page.');
        $this->addOption('operation', 'o', InputOption::VALUE_REQUIRED, sprintf(
            'Operation that should be done possible values are "%s" and "%s".',
            self::TYPE_OPERATION_UPLOAD,
            self::TYPE_OPERATION_DELETE
        ), self::TYPE_OPERATION_UPLOAD);


        $this->setDescription('The errorPages command allows you to set error pages.');
        $this->setHelp(sprintf(<<<EOF
The errorPages command allows you to set error pages.

Valid errorcodes are: %s.

Example usage of errorPages to upload error pages:
bin/console myracloud:api:errorPages -f file.html -e 429 -e 500 -o upload <apiKey> <secret> <fqdn>

Example usage of errorPages to remove error pages:
bin/console myracloud:api:errorPages -e 429 -e 505 -o delete <apiKey> <secret> <fqdn>

EOF
                , implode(', ', self::$availableCodes))
        );

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
            'language'    => self::DEFAULT_LANGUAGE,
            'apiEndpoint' => self::DEFAULT_API_ENDPOINT,
            'errorCodes'  => [],
            'operation'   => self::TYPE_OPERATION_UPLOAD,
            'contentFile' => null
        ]);

        $this->resolver->setAllowedValues('operation', [self::TYPE_OPERATION_UPLOAD, self::TYPE_OPERATION_DELETE]);

        $this->resolver->setNormalizer('fqdn', Normalizer::normalizeFqdn());
        $this->resolver->setNormalizer('errorCodes', function (OptionsResolver $resolver, $value) {
            $data = $value;

            if (is_string($value)) {
                $data = explode(',', $value);
            }

            for ($i = 0; $i < count($data); $i++) {
                $data[$i] = trim($data[$i]);
            }

            return $data;
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->resolveOptions($input, $output);

        $ret                  = null;
        $content              = [];
        $content['selection'] = [
            $this->options['fqdn'] => []
        ];

        foreach (self::$availableCodes as $code) {
            $content['selection'][$this->options['fqdn']][$code] = in_array($code, $this->options['errorCodes']);
        }

        if ($this->options['operation'] == self::TYPE_OPERATION_UPLOAD) {
            if ($this->options['contentFile'] == '' || !is_readable($this->options['contentFile'])) {
                throw new \RuntimeException('Could not read file "' . $this->options['contentFile'] . '".');
            }

            $content['pageContent'] = file_get_contents($this->options['contentFile']);

            $ret = $this->service->errorPages(MyracloudService::METHOD_UPDATE, $this->options['fqdn'], $content);
        } else if ($this->options['operation'] == self::TYPE_OPERATION_DELETE) {
            $ret = $this->service->errorPages(MyracloudService::METHOD_DELETE, $this->options['fqdn'], $content);
        }

        if ($output->isVerbose()) {
            print_r($ret);
        }

        if ($ret) {
            $output->writeln('<fg=green;options=bold>Success</>');
        }
    }
}
