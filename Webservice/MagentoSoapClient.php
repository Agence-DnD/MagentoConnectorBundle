<?php

namespace Pim\Bundle\MagentoConnectorBundle\Webservice;

use Pim\Bundle\MagentoConnectorBundle\Guesser\AbstractGuesser;

/**
 * A magento soap client to abstract interaction with the php soap client
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MagentoSoapClient
{
    protected $session;

    protected $client;

    protected $calls;

    protected $clientParameters;

    /**
     * Create and init the soap client
     *
     * @param MagentoSoapClientParametersRegistry $clientParameters
     * @param SoapClient                          $soapClient
     */
    public function __construct(MagentoSoapClientParameters $clientParameters, \SoapClient $soapClient = null)
    {
        $this->clientParameters = $clientParameters;

        if (!$soapClient) {
            $wsdlUrl     = $this->clientParameters->getSoapUrl();
            $soapOptions = [
                'encoding'   => 'UTF-8',
                'trace'      => true,
                'exceptions' => true,
                'login'      => $this->clientParameters->getHttpLogin(),
                'password'   => $this->clientParameters->getHttpPassword(),
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'keep-alive' => 1
            ];

            try {
                $this->client = new \SoapClient($wsdlUrl, $soapOptions);
            } catch (\SoapFault $e) {
                throw new ConnectionErrorException(
                    'The soap connection could not be established',
                    $e->getCode(),
                    $e
                );
            }
        } else {
            $this->client = $soapClient;
        }

        $this->connect();
    }

    /**
     * Initialize the soap client with the local informations
     *
     * @throws InvalidCredentialException If given credentials are invalid
     */
    protected function connect()
    {
        try {
            $this->session = $this->client->login(
                $this->clientParameters->getSoapUsername(),
                $this->clientParameters->getSoapApiKey()
            );
        } catch (\Exception $e) {
            throw new InvalidCredentialException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Is the client connected ?
     *
     * @return boolean
     */
    public function isConnected()
    {
        return (bool) $this->session;
    }

    /**
     * Call soap api
     *
     * @param string $resource
     * @param array  $params
     *
     * @return mixed
     */
    public function call($resource, $params = null)
    {
        if ($this->isConnected()) {
            try {
                $response = $this->client->call($this->session, $resource, $params);
            } catch (\SoapFault $e) {
                if ($resource === 'core_magento.info' && $e->getMessage()
                    === AbstractGuesser::MAGENTO_CORE_ACCESS_DENIED) {
                    $response = ['magento_version' => AbstractGuesser::UNKNOWN_VERSION];
                } elseif ($e->getMessage() === AbstractGuesser::MAGENTO_CORE_ACCESS_DENIED) {
                    throw new SoapCallException(
                        sprintf(
                            '%s Called resource : "%s" with parameters : %s.' .
                            ' Soap user needs access on this resource. Please ' .
                            'check in your Magento webservice soap roles and ' .
                            'users configuration.',
                            $e->getMessage(),
                            $resource,
                            json_encode($params)
                        )
                    );
                } else {
                    throw new SoapCallException(
                        sprintf(
                            'Error on Magento soap call : "%s". Called resource : "%s" with parameters : %s',
                            $e->getMessage(),
                            $resource,
                            json_encode($params)
                        )
                    );
                }
            }

            if (is_array($response) && isset($response['isFault']) && $response['isFault']) {
                throw new SoapCallException(
                    sprintf(
                        'Error on Magento soap call : "%s". Called resource : "%s" with parameters : %s.' .
                        'Response from API : %s',
                        $e->getMessage(),
                        $resource,
                        json_encode($params),
                        json_encode($response)
                    )
                );
            }

            return $response;
        } else {
            throw new NotConnectedException();
        }
    }

    /**
     * Add a call to the soap call stack
     *
     * @param array $call A magento soap call
     */
    public function addCall(array $call)
    {
        $this->call($call[0], $call[1]);
    }

    /**
     * Send pending calls to the magento soap api (with multiCall function)
     */
    public function sendCalls()
    {
        if (count($this->calls) > 0) {
            if ($this->isConnected()) {
                try {
                    $this->client->multiCall(
                        $this->session,
                        $this->calls
                    );
                } catch (\SoapFault $e) {
                    throw new SoapCallException(
                        sprintf(
                            'Error on Magento soap call : "%s". Called resources : "%s".',
                            $e->getMessage(),
                            json_encode($this->calls)
                        )
                    );
                }
            } else {
                throw new NotConnectedException();
            }

            $this->calls = [];
        }
    }
}
