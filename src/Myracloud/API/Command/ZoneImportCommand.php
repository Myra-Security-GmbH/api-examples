<?php

namespace Myracloud\API\Command;

use Myracloud\API\Service\MyracloudService;
use Myracloud\API\Util\Normalizer;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use LTDBeget\dns\configurator\Zone;

/**
 * Class ZoneImport
 *
 * @package Myracloud\API\Command
 */
class ZoneImportCommand extends AbstractCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('myracloud:api:zoneImport');
        $this->addArgument('apiKey', InputArgument::REQUIRED, 'Api key to authenticate against Myracloud API.', null);
        $this->addArgument('secret', InputArgument::REQUIRED, 'Secret to authenticate against Myracloud API.', null);
        $this->addArgument('fqdn', InputArgument::REQUIRED, 'Domain that should be used to clear the cache.'); 

        $this->addOption('contentFile', 'f', InputOption::VALUE_REQUIRED, 'HTML file that contains the maintenance page.');

        $this->setHelp(<<<EOF
ZoneImpor commands you parsing dns config files and import records via Myracloud API.

<fg=yellow>Example usage to add dns recors:</>
bin/console myracloud:api:zoneImport -f zone.conf <apiKey> <secret> <fqdn> -vvv; 
EOF
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
            'contentFile' => null,
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
            'fqdn'     => $this->options['fqdn'],
            'active'   => false,
            'priority' => 10,
            'ttl'      => 300,
        ];

        if ($this->options['contentFile'] == '' || !is_readable($this->options['contentFile'])) {
                throw new \RuntimeException('Could not read file "' . $this->options['contentFile'] . '".');
        }

        $contentFile = file_get_contents($this->options['contentFile']);

        // replace tabs and single spaces with double spaces (extra for dns\configurator\Zone) 
        $cleanFile = preg_replace('/[ ]{1,}|[\t]/', '  ', $contentFile);

        $zone = Zone::fromString($this->options['fqdn'], $cleanFile);
        
        $zoneArray = $zone->toArray();

        foreach ($zoneArray as $record) {

            $content['recordType'] = $record['TYPE'];

            $content['name'] = $record['NAME'];
            // as myra do not loke "@"
            if ($record['NAME'] == '@') {
                $content['name'] = ''; 
            }

            switch ($record['TYPE']) {
                case 'A':   
                    $content['value'] = $record['RDATA']['ADDRESS'];
                    break;
                case 'CNAME': 
                    $content['value'] = $record['RDATA']['CNAME'];
                    break;
                case 'MX': 
                    $content['value'] = $record['RDATA']['EXCHANGE'];
                    $content['priority'] = $record['RDATA']['PREFERENCE'];
                    break;
                case 'NS': 
                    $content['value'] = $record['RDATA']['NSDNAME'];
                    break;
                default:
                    $output->writeln('<fg=green;options=bold>skiping ' . $record['TYPE'] . ' </>');
                    continue 2;
            }

            $ret = $this->service->dnsRecord(MyracloudService::METHOD_CREATE, $this->options['fqdn'], $content);

            if ($output->isVerbose()) {
                print_r($ret);
            }

            if ($ret) {
                $output->writeln('<fg=green;options=bold>Success</>');
            }

            sleep(1);
        }

    }
}
