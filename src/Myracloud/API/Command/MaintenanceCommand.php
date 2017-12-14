<?php

namespace Myracloud\API\Command;

use Myracloud\API\Service\MyracloudService;
use Myracloud\API\Util\Normalizer;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class MaintenanceCommand
 *
 * @package Myracloud\API\Command
 */
class MaintenanceCommand extends AbstractCommand
{
    const OPERATION_CREATE = 'create';
    const OPERATION_DELETE = 'delete';
    const OPERATION_LIST = 'list';
    const OPERATION_UPDATE = 'update';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('myracloud:api:maintenance');
        $this->addArgument('apiKey', InputArgument::REQUIRED, 'Api key to authenticate against Myra API.', null);
        $this->addArgument('secret', InputArgument::REQUIRED, 'Secret to authenticate against Myra API.', null);
        $this->addArgument('fqdn', InputArgument::REQUIRED, 'Domain that should be used to clear the cache.');

        $this->addOption('operation', 'o', InputOption::VALUE_REQUIRED, '', self::OPERATION_CREATE);
        $this->addOption('contentFile', 'f', InputOption::VALUE_REQUIRED, 'HTML file that contains the maintenance page.');
        $this->addOption('start', 's', InputOption::VALUE_REQUIRED, 'Time to start the maintenance from.', date('Y-m-d H:i:s'));
        $this->addOption('end', 'e', InputOption::VALUE_REQUIRED, 'Time to end the maintenance.', date('Y-m-d H:i:s'));
        $this->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'Page to show when listing maintenance objects.', 1);

        $this->addOption('nStart', null, InputOption::VALUE_REQUIRED, 'When updating a maintenance this will be the new start date.');
        $this->addOption('nEnd', null, InputOption::VALUE_REQUIRED, 'When updating a maintenance this will be the new end date.');

        $this->setDescription('The maintenance command allows you to list, create, update, and delete maintenace pages.');
        $this->setHelp(sprintf(<<<EOF
The maintenance command allows you to list, create, update, and delete maintenace pages.

The options "start" and "end" are used to identify the maintenance that should be updated or deleted.
In case of an update (that changes the start and / or end date) you need also to set nStart and / or nEnd to the new dates. 

<fg=green>Valid operations are: %s.</>

<fg=yellow>Example usage to list maintenance pages:</>
bin/console myracloud:api:maintenance -o list <apiKey> <secret> <fqdn>

<fg=yellow>Example usage of maintenance to enqueue a new maintenance page:</>
bin/console myracloud:api:maintenance -f file.html -s "2016-03-30 00:00:00" -e "2017-04-01 00:00:00" <apiKey> <secret> <fqdn>

<fg=yellow>Example usage of maintenance to update the content of a existing maintenance:</>
bin/console myracloud:api:maintenance -o update -f newFile.html -s "2016-03-30 00:00:00" -e "2017-04-01 00:00:00" <apiKey> <secret> <fqdn>

<fg=yellow>Example usage of maintenance to update the start / end date of a existing maintenance:</>
bin/console myracloud:api:maintenance -o update --nStart="2016-04-01 01:00:00" --nEnd="2016-04-02 02:00:00" -s "2016-03-30 00:00:00" -e "2017-04-01 00:00:00" <apiKey> <secret> <fqdn>

<fg=yellow>Example usage of maintenance to update the start date of a existing maintenance:</>
bin/console myracloud:api:maintenance -o update --nStart="2016-04-01 01:00:00" -s "2016-03-30 00:00:00" -e "2017-04-01 00:00:00" <apiKey> <secret> <fqdn>

<fg=yellow>Example usage to remove a existing maintenance:</>
bin/console myracloud:api:maintenance -o delete -s "2016-03-30 00:00:00" -e "2017-04-01 00:00:00" <apiKey> <secret> <fqdn>
EOF
                , implode(', ', [
                    self::OPERATION_LIST,
                    self::OPERATION_CREATE,
                    self::OPERATION_UPDATE,
                    self::OPERATION_DELETE
                ]))
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
            'start'       => null,
            'end'         => null,
            'nStart'      => null,
            'nEnd'        => null,
            'operation'   => self::OPERATION_CREATE,
            'contentFile' => null,
            'page'        => 1,
        ]);

        $this->resolver->setNormalizer('fqdn', Normalizer::normalizeFqdn());
        $this->resolver->setNormalizer('start', Normalizer::normalizeDate(true));
        $this->resolver->setNormalizer('end', Normalizer::normalizeDate(true));
        $this->resolver->setNormalizer('nStart', Normalizer::normalizeDate(true));
        $this->resolver->setNormalizer('nEnd', Normalizer::normalizeDate(true));
        $this->resolver->setNormalizer('page', Normalizer::normalizeInt());

        $this->resolver->setAllowedValues('operation', [
            self::OPERATION_LIST,
            self::OPERATION_CREATE,
            self::OPERATION_DELETE,
            self::OPERATION_UPDATE,
        ]);
    }

    /**
     * @throws \Myracloud\API\Exception\ApiCallException
     * @return array|null
     */
    private function findMaintenance($throwException = false)
    {
        $ret = $this->service->maintenance(MyracloudService::METHOD_LIST, $this->options['fqdn'], [], 1);

        $start = ($this->options['start'] instanceof \DateTime ? $this->options['start']->format(\DateTime::ISO8601) : null);
        $end   = ($this->options['end'] instanceof \DateTime ? $this->options['end']->format(\DateTime::ISO8601) : null);

        $maintenance = null;
        $pages       = ceil($ret['count'] / $ret['pageSize']);

        for ($i = 1; $i <= $pages; $i++) {
            if ($i > 1) {
                $ret = $this->service->maintenance(MyracloudService::METHOD_LIST, $this->options['fqdn'], [], $i);
            }

            foreach ($ret['list'] as $m) {
                $isMaintenace = true;
                $isMaintenace &= (!empty($m['start']) ? $m['start'] === $start : empty($start));
                $isMaintenace &= (!empty($m['end']) ? $m['end'] === $end : empty($end));
                $isMaintenace &= $m['fqdn'] === $this->options['fqdn'];

                if ($isMaintenace) {
                    if ($maintenance !== null) {
                        throw new \RuntimeException('Found multiple maintenance that matches the given data');
                    }
                    $maintenance = $m;
                }
            }
        }

        if ($throwException && $maintenance === null) {
            throw new \RuntimeException('Could not find a maintenance matching the given start / end / fqdn.');
        }

        return $maintenance;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->resolveOptions($input, $output);

        $ret = null;

        switch ($this->options['operation']) {
            case self::OPERATION_CREATE:
                if (!is_readable($this->options['contentFile'])) {
                    throw new \RuntimeException(sprintf('Could not find given file "%s".', $this->options['contentFile']));
                }

                $content = [
                    "content" => file_get_contents($this->options['contentFile']),
                    "start"   => $this->options['start']->format(\DateTime::ISO8601),
                    "end"     => $this->options['end']->format(\DateTime::ISO8601),
                ];

                $ret = $this->service->maintenance(MyracloudService::METHOD_CREATE, $this->options['fqdn'], $content);
                break;

            case self::OPERATION_DELETE:
                $maintenance = $this->findMaintenance(true);

                $content = [
                    'id'       => $maintenance['id'],
                    'modified' => $maintenance['modified']
                ];

                $ret = $this->service->maintenance(MyracloudService::METHOD_DELETE, $this->options['fqdn'], $content);
                break;

            case self::OPERATION_UPDATE:
                $maintenance = $this->findMaintenance(true);

                $content = [
                    'id'       => $maintenance['id'],
                    'modified' => $maintenance['modified']
                ];

                if (empty($this->options['nStart']) && empty($this->options['nEnd']) && empty($this->options['contentFile'])) {
                    throw new \RuntimeException('There is nothing to change');
                }

                if ($this->options['nStart'] !== null) {
                    $content['start'] = $this->options['nStart']->format(\DateTime::ISO8601);
                } else {
                    $content['start'] = $this->options['start']->format(\DateTime::ISO8601);
                }

                if ($this->options['nEnd'] !== null) {
                    $content['end'] = $this->options['nEnd']->format(\DateTime::ISO8601);
                } else {
                    $content['end'] = $this->options['end']->format(\DateTime::ISO8601);
                }

                if (is_readable($this->options['contentFile'])) {
                    $content['content'] = file_get_contents($this->options['contentFile']);
                }

                $ret = $this->service->maintenance(MyracloudService::METHOD_UPDATE, $this->options['fqdn'], $content);
                break;

            case self::OPERATION_LIST:
                $ret = $this->service->maintenance(MyracloudService::METHOD_LIST, $this->options['fqdn'], [], $this->options['page']);
                break;
        }

        if ($output->isVerbose()) {
            print_r($ret);
        }

        if ($ret) {
            if ($this->options['operation'] === self::OPERATION_LIST && !$output->isVerbose()) {
                $table = new Table($output);

                $table->setHeaders(['Id', 'Created', 'Modified', 'Fqdn', 'Start', 'End', 'Active']);

                foreach ($ret['list'] as $maintenance) {
                    $table->addRow([
                        $maintenance['id'],
                        $maintenance['created'],
                        $maintenance['modified'],
                        $maintenance['fqdn'],
                        $maintenance['start'],
                        $maintenance['end'],
                        $maintenance['active'] ?: 0,
                    ]);
                }

                $table->render();
            }

            $output->writeln('<fg=green;options=bold>Success</>');
        }
    }

}
