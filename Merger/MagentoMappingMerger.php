<?php

namespace Pim\Bundle\MagentoConnectorBundle\Merger;

use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use Pim\Bundle\ConnectorMappingBundle\Merger\MappingMerger;

/**
 * Magento mapping merger
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MagentoMappingMerger extends MappingMerger
{
    /**
     * Set parameters of all mappers
     *
     * @param MagentoSoapClientParameters $clientParameters
     * @param string                      $defaultStoreView
     */
    public function setParameters(MagentoSoapClientParameters $clientParameters, $defaultStoreView)
    {
        foreach ($this->getOrderedMappers() as $mapper) {
            $mapper->setParameters($clientParameters, $defaultStoreView);
        }

        $this->hasParametersSet = true;
    }

    /**
     * Get configuration field for the merger
     *
     * @return array
     */
    public function getConfigurationField()
    {
        return [
            $this->name . 'Mapping' => [
                'type'    => 'textarea',
                'options' => [
                    'required' => false,
                    'attr'     => [
                        'class' => 'mapping-field',
                        'data-sources' => json_encode($this->getAllSources()),
                        'data-targets' => json_encode($this->getAllTargets()),
                        'data-name'    => $this->name
                    ],
                    'label' => 'pim_magento_connector.' . $this->direction . '.' . $this->name . 'Mapping.label',
                    'help'  => 'pim_magento_connector.' . $this->direction . '.' . $this->name . 'Mapping.help'
                ]
            ]
        ];
    }
}
