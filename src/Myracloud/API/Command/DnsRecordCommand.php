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
 * Class DnsRecord
 *
 * @package Myracloud\API\Command
 */
class DnsRecordCommand extends AbstractCommand
{
    const OPERATION_CREATE = 'create';

    private static $recordTypes = ['A', 'AAAA', 'MX', 'CNAME', 'TXT', 'NS', 'SRV', 'CAA'];

    private static $ttls = [300, 600, 900, 1800, 3600, 7200, 18000, 43200, 86400];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('myracloud:api:dnsRecord');
        $this->addArgument('apiKey', InputArgument::REQUIRED, 'Api key to authenticate against Myracloud API.', null);
        $this->addArgument('secret', InputArgument::REQUIRED, 'Secret to authenticate against Myracloud API.', null);
        $this->addArgument('fqdn', InputArgument::REQUIRED, 'Domain that should be used to clear the cache.');
        
        $this->addOption('recordName', 'r', InputOption::VALUE_REQUIRED, 
            'The domain name. You can use a wildcard domain, domain, or a subdomain.');

        $this->addOption('recordValue', 'i',  InputOption::VALUE_REQUIRED, 
            'For an A record you use an IPv4 address, for AAAA an IPv6 address, for CNAME a domain name, 
            for MX a domain name (no IP), for NS an IP, for SRV a domain name, and for TXT an arbitrary text');

        $this->addOption('recordType', 't', InputOption::VALUE_REQUIRED, 
            'Define the DNS type: A, AAAA, CNAME, MX, NS, SRV, or TXT.');

        $this->addOption('ttl', null, InputOption::VALUE_REQUIRED, 
            'Time To Live: how long should the entry considered to be valid', self::$ttls['0']);

        $this->addOption('active', null, InputOption::VALUE_NONE, 
            'Define wether this subdomain should be protected by MYRACLOUD or not');

        $this->addOption('priority', null, InputOption::VALUE_REQUIRED, 'Priority for MX and SRV records', 10);

        $this->setDescription('DnsRecords commands allows you configure DNS related settings 
            like changing your origin IP or setting up a new DNS record via Myracloud API.');
        
        $this->setHelp(<<<EOF
DnsRecords commands allows you configure DNS related settings 
like changing your origin IP or setting up a new DNS record via Myracloud API.

<fg=yellow>Example usage to add dns recors:</>
bin/console myracloud:api:dnsRecord  -r somevalie -i srv24.examle.com -t MX <apiKey> <secret> <fqdn> -vvv ;  
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
            'ttl'         => self::$ttls['0'],
            'priority'    => 10,
            'active'      => false,
            'recordName'  => null,
            'recordValue' => null,
            'recordType'  => null
        ]);
      
        $this->resolver->setAllowedValues('recordType', self::$recordTypes);

        $this->resolver->setNormalizer('fqdn', Normalizer::normalizeFqdn());
    }

    /**
     * {@inheritdoc}
     *  
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->resolveOptions($input, $output);

        print_r($this->options);

        $content = [
            'fqdn'      => $this->options['fqdn'],
            'name'      => $this->options['recordName'],
            'value'     => $this->options['recordValue'],
            'recordType'=> $this->options['recordType'],
            'ttl'       => $this->options['ttl'],
            'priority'  => $this->options['priority'],
            'active'    => $input->getOption('active')
        ];

        $ret = $this->service->dnsRecord(MyracloudService::METHOD_CREATE, $this->options['fqdn'], $content);

        if ($output->isVerbose()) {
            print_r($ret);
        }

        if ($ret) {
            $output->writeln('<fg=green;options=bold>Success</>');
        }
    }
}