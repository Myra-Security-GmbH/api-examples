<?php

namespace Myracloud\API\Command;

use Myracloud\API\Service\MyracloudService;
use Myracloud\API\Util\Normalizer;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Statistic
 *
 * @package Myracloud\API\Statistic
 */
class StatisticCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('myracloud:api:statistic');
        $this->addArgument('apiKey', InputArgument::REQUIRED, 'Api key to authenticate against Myracloud API.', null);
        $this->addArgument('secret', InputArgument::REQUIRED, 'Secret to authenticate against Myracloud API.', null);
        $this->addArgument('fqdn', InputArgument::REQUIRED, 'Domain that should be used to clear the cache.'); 

        $this->addOption('startDate', 's', InputOption::VALUE_REQUIRED, 'startDate Time.', date('Y-m-d H:i:s', strtotime("yesterday")));
        $this->addOption('endDate', 'e', InputOption::VALUE_REQUIRED, 'endDate Time.', date('Y-m-d H:i:s', strtotime("now")));

        $this->setHelp(<<<EOF
<fg=yellow>Example usage:</>
bin/console myracloud:api:statistic -s '2017-12-13 00:00:00' -e '2017-12-13 12:00:59' <apiKey> <secret> <fqdn> -v
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
            'startDate'   => null,
            'endDate'     => null,
            'language'    => self::DEFAULT_LANGUAGE,
            'apiEndpoint' => self::DEFAULT_API_ENDPOINT,
        ]);

        $this->resolver->setNormalizer('startDate', Normalizer::normalizeDate(true));
        $this->resolver->setNormalizer('endDate', Normalizer::normalizeDate(true));
        $this->resolver->setNormalizer('fqdn', Normalizer::normalizeFqdn());
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->resolveOptions($input, $output);

        if  ($this->options['startDate'] instanceof \DateTime) {
            $startDate = $this->options['startDate']->format(\DateTime::ISO8601);
        } else {
            throw new \RuntimeException('Required startDate Format: Y-m-d H:i:s');
        }

        if  ($this->options['endDate'] instanceof \DateTime) {
            $endDate = $this->options['endDate']->format(\DateTime::ISO8601);
        } else {
            throw new \RuntimeException('Required endDate Format: Y-m-d H:i:s');
        }

        $data = array(
            'query' => array(
                'startDate'           => $startDate,
                'endDate'             => $endDate,
                'type'                => 'fqdn',
                'fqdn'                => array('ALL:'.$this->options['fqdn']),
                'aggregationInterval' => 'hour',
                'dataSources'         => $this->getKPIDataSources()
            )
        );

        $ret = $this->service->statistic(MyracloudService::METHOD_UPDATE, $data);

        if ($output->isVerbose()) {
            print_r($ret);
        }

        if ($ret) {

            $table = new Table($output);

            $table->setHeaders([
                'Domain',
                'Total Requests (Cached/Uncached)', 
                'Total Traffic', 'Total abgewehrt', 
                'Ø Antwortzeit Upstream', 'Statuscode 200', '301', '302', '304', '403', '404', '500', 'Other',
            ]); 
   
            $table->addRow([
                $this->options['fqdn'],
                number_format($ret['result']['requests_stats']['sum'], 0, ',', '.')
                    . ' (' 
                    . number_format($ret['result']['requests_cached_stats']['sum'], 0, ',', '.') 
                    . '/'
                    . number_format($ret['result']['requests_uncached_stats']['sum'], 0, ',', '.') 
                    . ')' ,
                round($ret['result']['bytes_stats']['sum'] / 1024 / 1024 / 1024 ) . ' GB',
                $ret['result']['requests_blocked_stats']['sum'],
                floor($ret['result']['upstream_performance_stats']['avg'] * 1000) . ' ms',
                number_format($ret['result']['response_codes_stats']['200']['sum'], 0, ',', '.'),
                number_format($ret['result']['response_codes_stats']['301']['sum'], 0, ',', '.'),
                number_format($ret['result']['response_codes_stats']['302']['sum'], 0, ',', '.'),
                number_format($ret['result']['response_codes_stats']['304']['sum'], 0, ',', '.'),
                number_format($ret['result']['response_codes_stats']['403']['sum'], 0, ',', '.'),
                number_format($ret['result']['response_codes_stats']['404']['sum'], 0, ',', '.'),
                number_format($ret['result']['response_codes_stats']['500']['sum'], 0, ',', '.'),
                number_format($ret['result']['response_codes_stats']['Other']['sum'], 0, ',', '.'),
            ]);

            $table->render();
        
        }
    }

    protected function getKPIDataSources()
    {
        return array(
            'requests_stats' => array(
                'source' => 'requests',
                'type'   => 'stats',
            ),
            'requests_cached_stats' => array(
                'source' => 'requests_cached',
                'type'   => 'stats',
            ),
            'requests_uncached_stats' => array(
                'source' => 'requests_uncached',
                'type'   => 'stats',
            ),
            'bytes_stats' => array(
                'source' => 'bytes',
                'type'   => 'stats',
            ),
            'requests_blocked_stats' => array(
                'source' => 'requests_blocked',
                'type'   => 'stats',
            ),
            'upstream_performance_stats' => array(
                'source' => 'upstream_performance',
                'type'   => 'stats',
            ),
            'response_codes_stats' => array(
                'source' => 'response_codes',
                'type'   => 'stats',
            ),
        );
    }
   
}