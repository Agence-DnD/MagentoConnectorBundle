<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\AbstractNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\NormalizeException;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;

/**
 * Magento option processor
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class OptionProcessor extends AbstractProcessor
{
    /**
     * @var OptionNormalizer
     */
    protected $optionNormalizer;

    /**
     * @var MagentoMappingMerger
     */
    protected $attributeMappingMerger;

    /**
     * @var string
     */
    protected $attributeCodeMapping;

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param ProductNormalizerGuesser            $normalizerGuesser
     * @param LocaleManager                       $localeManager
     * @param MagentoMappingMerger                $storeViewMappingMerger
     * @param MagentoMappingMerger                $attributeMappingMerger
     * @param MagentoSoapClientParametersRegistry $clientParametersRegistry
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        LocaleManager $localeManager,
        MagentoMappingMerger $storeViewMappingMerger,
        MagentoMappingMerger $attributeCodeMappingMerger,
        MagentoSoapClientParametersRegistry $clientParametersRegistry
    ) {
        parent::__construct(
            $webserviceGuesser,
            $normalizerGuesser,
            $localeManager,
            $storeViewMappingMerger,
            $clientParametersRegistry
        );

        $this->attributeCodeMappingMerger = $attributeCodeMappingMerger;
    }

    /**
     * Set attribute code mapping
     *
     * @param string $attributeCodeMapping
     *
     * @return AttributeProcessor
     */
    public function setAttributeCodeMapping($attributeCodeMapping)
    {
        $decodedAttributeCodeMapping = json_decode($attributeCodeMapping, true);

        if (!is_array($decodedAttributeCodeMapping)) {
            $decodedAttributeCodeMapping = [$decodedAttributeCodeMapping];
        }

        $this->attributeCodeMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
        $this->attributeCodeMappingMerger->setMapping($decodedAttributeCodeMapping);
        $this->attributeCodeMapping = $this->getAttributeCodeMapping();

        return $this;
    }

    /**
     * Get attribute code mapping
     *
     * @return string
     */
    public function getAttributeCodeMapping()
    {
        return json_encode($this->attributeCodeMappingMerger->getMapping()->toArray());
    }

    /**
     * {@inheritdoc}
     */
    protected function beforeExecute()
    {
        parent::beforeExecute();

        $this->optionNormalizer = $this->normalizerGuesser->getOptionNormalizer($this->getClientParameters());

        $magentoStoreViews = $this->webservice->getStoreViewsList();

        $this->globalContext['magentoStoreViews']    = $magentoStoreViews;
        $this->globalContext['attributeCodeMapping'] = $this->attributeCodeMappingMerger->getMapping();
    }

    /**
     * {@inheritdoc}
     */
    public function process($groupedOptions)
    {
        $this->beforeExecute();

        $attribute     = $groupedOptions[0]->getAttribute();
        $attributeCode = strtolower($this->globalContext['attributeCodeMapping']->getTarget($attribute->getCode()));

        try {
            $optionsStatus = $this->webservice->getAttributeOptions($attributeCode);
        } catch (SoapCallException $e) {
            throw new InvalidItemException(
                sprintf(
                    'An error occurred during the retrieval of option list of the attribute "%s". This may be ' .
                    'due to the fact that "%s" attribute doesn\'t exist on Magento side. Please be sure that ' .
                    'this attribute is created (mannualy or by export) on Magento before options\' export. ' .
                    '(Original error : "%s")',
                    $attributeCode,
                    $attributeCode,
                    $e->getMessage()
                ),
                [
                    'code'  => $attribute->getCode(),
                    'label' => $attribute->getLabel(),
                    'type'  => $attribute->getAttributeType()
                ]
            );
        }

        $this->globalContext['attributeCode'] = $attributeCode;

        $normalizedOptions = [];

        foreach ($groupedOptions as $option) {
            if (!array_key_exists($option->getCode(), $optionsStatus)) {
                $normalizedOptions[] = $this->getNormalizedOption($option, $this->globalContext);
            }
        }

        return $normalizedOptions;
    }

    /**
     * Get the normalized
     * @param AttributeOption $option
     * @param array           $context
     *
     * @return array
     */
    protected function getNormalizedOption(AttributeOption $option, array $context)
    {
        try {
            $normalizedOption = $this->optionNormalizer->normalize(
                $option,
                AbstractNormalizer::MAGENTO_FORMAT,
                $context
            );
        } catch (NormalizeException $e) {
            throw new InvalidItemException($e->getMessage(), [$option]);
        }

        return $normalizedOption;
    }

    /**
     * Called after the configuration is set
     */
    protected function afterConfigurationSet()
    {
        parent::afterConfigurationSet();

        $this->attributeCodeMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array_merge(
            parent::getConfigurationFields(),
            $this->attributeCodeMappingMerger->getConfigurationField()
        );
    }
}
