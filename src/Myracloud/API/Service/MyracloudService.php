<?php

namespace Myracloud\API\Service;

use Myracloud\API\Authentication\MyraSignature;
use Myracloud\API\Command\AbstractCommand;
use Myracloud\API\Exception\ApiCallException;
use Myracloud\API\Exception\PermissionDeniedException;
use Myracloud\API\Exception\UnknownErrorException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class MyracloudService
 *
 * @package Myracloud\API\Service
 */
class MyracloudService
{
    const METHOD_CREATE = 'PUT';
    const METHOD_UPDATE = 'POST';
    const METHOD_DELETE = 'DELETE';
    const METHOD_LIST = 'GET';

    /** @var OutputInterface */
    private $output;

    /** @var OptionsResolver */
    private $requestResolver;

    /**
     * Prepares the header data for curl.
     * Also append the authentication part for the request that is created.
     *
     * @param string $url
     * @param array  $options
     * @return array
     */
    private function prepareHeaderData($url, array $options)
    {
        $signature = MyraSignature::create(
            $options['method'],
            $url,
            $options['secret'],
            $options['header'],
            $options['content']
        );

        $tmp = array_merge($options['header'], [
            'Authorization' => 'MYRA ' . $options['apiKey'] . ':' . $signature->retrieveSignature()
        ]);

        $headerData = [];
        foreach ($tmp as $key => $value) {
            $headerData[] = sprintf("%s: %s", $key, $value);
        }

        return $headerData;
    }

    /**
     * Outputs the violations on command line when a proper outputInterface ist set.
     *
     * @param array $retData
     */
    private function outputViolations(array $retData)
    {
        if (!$this->output) {
            return;
        }

        if ($this->output->isVerbose()) {
            print_r($retData);
        }

        $row = null;
        if (isset($retData['targetObject'][0])) {
            $row = $retData['targetObject'][0];
        }

        foreach ($retData['violationList'] as $violation) {
            $this->output->writeln(sprintf(
                '<fg=red;options=bold>%s [property="%s", givenValue="%s"]</>',
                $violation['message'],
                $violation['propertyPath'],
                ($row ? $row[$violation['propertyPath']] : 'N/A')
            ));
        }
    }

    /**
     * @param string $method
     * @param string $fqdn
     * @param array  $data
     * @param int    $page
     * @return null
     * @throws ApiCallException
     * @throws PermissionDeniedException
     * @throws UnknownErrorException
     */
    public function maintenance($method, $fqdn, array $data = [], $page = 1)
    {
        try {
            return $this->request([
                'method'  => $method,
                'url'     => 'maintenance/' . trim($fqdn, '.'),
                'content' => (!empty($data) ? json_encode($data) : ''),
            ], $page);
        } catch (ApiCallException $ex) {
            if (!$this->output) {
                throw $ex;
            }

            $this->outputViolations($ex->getData());
        }

        return null;
    }

    /**
     * @param string $method
     * @param string $fqdn
     * @param array  $data
     * @return mixed|null
     * @throws ApiCallException
     * @throws PermissionDeniedException
     * @throws UnknownErrorException
     */
    public function errorPages($method, $fqdn, array $data = [])
    {
        try {
            return $this->request([
                'method'  => $method,
                'url'     => 'errorpages/' . trim($fqdn, '.'),
                'content' => (!empty($data) ? json_encode($data) : ''),
            ]);
        } catch (ApiCallException $ex) {
            if (!$this->output) {
                throw $ex;
            }

            $this->outputViolations($ex->getData());
        }

        return null;
    }

    /**
     * Handles cache clear operations.
     *
     * @param string $method
     * @param string $fqdn
     * @param array  $data
     * @return mixed
     * @throws ApiCallException
     */
    public function cacheClear($method, $fqdn, array $data = [])
    {
        try {
            return $this->request([
                'method'  => $method,
                'url'     => 'cacheClear/' . trim($fqdn, '.'),
                'content' => (!empty($data) ? json_encode($data) : ''),
            ]);
        } catch (ApiCallException $ex) {
            if (!$this->output) {
                throw $ex;
            }

            $this->outputViolations($ex->getData());
        }

        return null;
    }

    /**
     * Handles DNS Records operations.
     *
     * @param string $method
     * @param string $fqdn
     * @param array  $data
     * @return mixed
     * @throws ApiCallException
     */
    public function dnsRecord($method, $fqdn, array $data = [])
    {
        try {
            return $this->request([
                'method'  => $method,
                'url'     => 'dnsRecords/' . trim($fqdn, '.'),
                'content' => (!empty($data) ? json_encode($data) : ''),
            ]);
        } catch (ApiCallException $ex) {
            if (!$this->output) {
                throw $ex;
            }

            $this->outputViolations($ex->getData());
        }

        return null;
    }

    public function statistic($method, array $data = [])
    {

        try {
            return $this->request([
                'method'  => $method,
                'url'     => 'statistic/query',
                'content' => (!empty($data) ? json_encode($data) : ''),
            ]);
        } catch (ApiCallException $ex) {
            if (!$this->output) {
                throw $ex;
            }

            $this->outputViolations($ex->getData());
        }

        return null;
    }


    /**
     * Calls the given command
     *
     * @param array $options
     * @param int   $page
     * @return null
     * @throws ApiCallException
     * @throws PermissionDeniedException
     * @throws UnknownErrorException
     */
    protected function request(array $options, $page = 1)
    {
        $options = $this->requestResolver->resolve($options);

        $url      = '/' . $options['language'] . '/rapi/' . ltrim($options['url'], '/');
        $endpoint = rtrim($options['apiEndpoint'], '/') . $url;

        // When listing append the current page
        if ($options['method'] === self::METHOD_LIST) {
            $endpoint .= '/' . $page;
            $url .= '/' . $page;
        }

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $options['method'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $this->prepareHeaderData($url, $options),
        ]);

        if ($options['noCheckCert'] === true) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        if ($options['verbose'] === true) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
        }

        if (!empty($options['content']) && $options['method'] !== self::METHOD_LIST) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['content']);
        }

        $ret        = curl_exec($ch);
        $returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = null;

        switch ($returnCode) {
            case 403:
                throw new PermissionDeniedException();

            case 200:
                $data = json_decode($ret, true);

                if ($data['error']) {
                    throw new ApiCallException('There was an error in the last api call.', $data);
                }

                break;

            default:
                throw new UnknownErrorException();
        }

        return $data;
    }

    /**
     * MyracloudService constructor.
     *
     * @param array $defaults
     */
    public function __construct(array $defaults, OutputInterface $output = null)
    {
        $this->requestResolver = new OptionsResolver();
        $this->requestResolver->setDefaults(array_merge([
            'apiEndpoint' => AbstractCommand::DEFAULT_API_ENDPOINT,
            'language'    => AbstractCommand::DEFAULT_LANGUAGE,
            'method'      => self::METHOD_LIST,
            'content'     => null,
            'header'      => [
                'Date'         => date('c'),
                'Content-Type' => 'application/json',
            ]
        ], $defaults));

        $this->requestResolver->setRequired(['method', 'url', 'apiKey', 'secret']);
        $this->output = $output;
    }
}
