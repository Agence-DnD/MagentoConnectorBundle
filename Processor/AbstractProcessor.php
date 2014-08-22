<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Symfony\Component\Validator\Constraints as Assert;
use Akeneo\Bundle\BatchBundle\Item\ItemProcessorInterface;
use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;

use Pim\Bundle\MagentoConnectorBundle\Item\MagentoItemStep;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Webservice\AttributeSetNotFoundException;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;

/**
 * Magento product processor
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
abstract class AbstractProcessor extends MagentoItemStep implements ItemProcessorInterface
{
    /**
     * @var NormalizerGuesser
     */
    protected $normalizerGuesser;

    /**
     * @var LocaleManager
     */
    protected $localeManager;

    /**
     * @Assert\NotBlank(groups={"Execution"})
     */
    protected $defaultLocale;

    /**
     * @Assert\NotBlank(groups={"Execution"})
     */
    protected $website = 'base';

    /**
     * @var MagentoMappingMerger
     */
    protected $storeViewMappingMerger;

    /**
     * @var string
     */
    protected $storeviewMapping = '';

    /**
     * @var array
     */
    protected $globalContext = [];

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param ProductNormalizerGuesser            $normalizerGuesser
     * @param LocaleManager                       $localeManager
     * @param MagentoMappingMerger                $storeViewMappingMerger
     * @param MagentoSoapClientParametersRegistry $clientParametersRegistry
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        LocaleManager $localeManager,
        MagentoMappingMerger $storeViewMappingMerger,
        MagentoSoapClientParametersRegistry $clientParametersRegistry
    ) {
        parent::__construct($webserviceGuesser, $clientParametersRegistry);

        $this->normalizerGuesser      = $normalizerGuesser;
        $this->localeManager          = $localeManager;
        $this->storeViewMappingMerger = $storeViewMappingMerger;
    }

    /**
     * get defaultLocale
     *
     * @return string defaultLocale
     */
    public function getDefaultLocale()
    {
        return $this->defaultLocale;
    }

    /**
     * Set defaultLocale
     *
     * @param string $defaultLocale defaultLocale
     *
     * @return AbstractProcessor
     */
    public function setDefaultLocale($defaultLocale)
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    /**
     * get website
     *
     * @return string website
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * Set website
     *
     * @param string $website website
     *
     * @return AbstractProcessor
     */
    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }

    /**
     * Set attribute mapping
     * @param string $storeViewMapping
     *
     * @return AbstractProcessor
     */
    public function setStoreViewMapping($storeViewMapping)
    {
        $decodedStoreViewMapping = json_decode($storeViewMapping, true);

        if (!is_array($decodedStoreViewMapping)) {
            $decodedStoreViewMapping = [$decodedStoreViewMapping];
        }

        $this->storeViewMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
        $this->storeViewMappingMerger->setMapping($decodedStoreViewMapping);
        $this->storeviewMapping = $this->getStoreViewMapping();

        return $this;
    }

    /**
     * Get attribute mapping
     * @return string
     */
    public function getStoreViewMapping()
    {
        return json_encode($this->storeViewMappingMerger->getMapping()->toArray());
    }

    /**
     * Get computed mapping
     * @param string $mapping
     *
     * @return array
     */
    protected function getComputedMapping($mapping)
    {
        $computedMapping = [];

        foreach (explode(chr(10), $mapping) as $line) {
            $computedLine = explode(':', $line);

            if (isset($computedLine[0]) && isset($computedLine[1])) {
                $computedMapping[$computedLine[0]] = $computedLine[1];
            }
        }

        return $computedMapping;
    }

    /**
     * Function called before all process
     */
    protected function beforeExecute()
    {
        parent::beforeExecute();

        $this->globalContext['defaultLocale']    = $this->defaultLocale;
        $this->globalContext['storeViewMapping'] = $this->storeViewMappingMerger->getMapping();
        $this->globalContext['defaultStoreView'] = $this->getDefaultStoreView();
    }

    /**
     * Get the attribute set id for the given family code
     *
     * @param string $familyCode
     * @param mixed  $relatedItem
     *
     * @throws InvalidItemException If The attribute set doesn't exist on Mangento
     * @return integer
     */
    protected function getAttributeSetId($familyCode, $relatedItem)
    {
        try {
            return $this->webservice
                ->getAttributeSetId(
                    $familyCode
                );
        } catch (AttributeSetNotFoundException $e) {
            throw new InvalidItemException($e->getMessage(), [$relatedItem]);
        }
    }

    /**
     * Called after the configuration is set
     */
    protected function afterConfigurationSet()
    {
        parent::afterConfigurationSet();

        $this->storeViewMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array_merge(
            parent::getConfigurationFields(),
            [
                'defaultLocale' => [
                    'type'    => 'choice',
                    'options' => [
                        'choices'  => $this->localeManager->getLocaleChoices(),
                        'required' => true,
                        'attr' => [
                            'class' => 'select2'
                        ],
                        'help'  => 'pim_magento_connector.export.defaultLocale.help',
                        'label' => 'pim_magento_connector.export.defaultLocale.label'
                    ]
                ],
                'website' => [
                    'type'    => 'text',
                    'options' => [
                        'required' => true,
                        'help'  => 'pim_magento_connector.export.website.help',
                        'label' => 'pim_magento_connector.export.website.label'
                    ]
                ]
            ],
            $this->storeViewMappingMerger->getConfigurationField()
        );
    }
}
