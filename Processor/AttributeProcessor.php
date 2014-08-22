<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\CatalogBundle\Model\AbstractAttribute;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\GroupManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\AbstractNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\NormalizeException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;

/**
 * Magento attributes processor
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeProcessor extends AbstractProcessor
{
    /**
     * @var MagentoMappingMerger
     */
    protected $attributeMappingMerger;

    /**
     * @var GroupManager
     */
    protected $groupManager;

    /**
     * @var string
     */
    protected $attributeCodeMapping = '';

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param ProductNormalizerGuesser            $normalizerGuesser
     * @param LocaleManager                       $localeManager
     * @param MagentoMappingMerger                $storeViewMappingMerger
     * @param MagentoMappingMerger                $attributeMappingMerger
     * @param MagentoSoapClientParametersRegistry $clientParametersRegistry
     * @param GroupManager                        $groupManager
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        LocaleManager $localeManager,
        MagentoMappingMerger $storeViewMappingMerger,
        MagentoMappingMerger $attributeMappingMerger,
        MagentoSoapClientParametersRegistry $clientParametersRegistry,
        GroupManager $groupManager
    ) {
        parent::__construct(
            $webserviceGuesser,
            $normalizerGuesser,
            $localeManager,
            $storeViewMappingMerger,
            $clientParametersRegistry
        );

        $this->attributeMappingMerger = $attributeMappingMerger;
        $this->groupManager           = $groupManager;
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

        $this->attributeMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
        $this->attributeMappingMerger->setMapping($decodedAttributeCodeMapping);
        $this->attributeCodeMapping = $this->getAttributeCodeMapping();

        return $this;
    }

    /**
     * Get attribute code mapping
     * @return string
     */
    public function getAttributeCodeMapping()
    {
        return json_encode($this->attributeMappingMerger->getMapping()->toArray());
    }

    /**
     * {@inheritdoc}
     */
    protected function beforeExecute()
    {
        parent::beforeExecute();

        $magentoStoreViews = $this->webservice->getStoreViewsList();

        $this->attributeNormalizer = $this->normalizerGuesser->getAttributeNormalizer($this->getClientParameters());
        $this->globalContext['magentoAttributes']        = $this->webservice->getAllAttributes();
        $this->globalContext['magentoAttributesOptions'] = $this->webservice->getAllAttributesOptions();
        $this->globalContext['attributeCodeMapping']     = $this->attributeMappingMerger->getMapping();
        $this->globalContext['magentoStoreViews']        = $magentoStoreViews;
        $this->globalContext['axisAttributes']           = $this->getAxisAttributes();
    }

    /**
     * {@inheritdoc}
     */
    public function process($attribute)
    {
        $this->beforeExecute();
        $magentoAttributes = $this->webservice->getAllAttributes();

        $this->globalContext['create'] = !$this->magentoAttributeExists($attribute, $magentoAttributes);
        $result = [$attribute, $this->normalizeAttribute($attribute, $this->globalContext)];

        return $result;
    }

    /**
     * Test if an attribute exist on magento
     * @param AbstractAttribute $attribute
     * @param array             $magentoAttributes
     *
     * @return boolean
     */
    protected function magentoAttributeExists(AbstractAttribute $attribute, array $magentoAttributes)
    {
        return array_key_exists(
            strtolower($this->attributeMappingMerger->getMapping()->getTarget($attribute->getCode())),
            $magentoAttributes
        );
    }

    /**
     * Normalize the given attribute
     * @param AbstractAttribute $attribute
     * @param array             $context
     *
     * @throws InvalidItemException If a problem occurred with the normalizer
     * @return array
     */
    protected function normalizeAttribute(AbstractAttribute $attribute, array $context)
    {
        try {
            $processedItem = $this->attributeNormalizer->normalize(
                $attribute,
                AbstractNormalizer::MAGENTO_FORMAT,
                $context
            );
        } catch (NormalizeException $e) {
            throw new InvalidItemException($e->getMessage(), [$attribute]);
        }

        return $processedItem;
    }

    /**
     * Called after the configuration is set
     */
    protected function afterConfigurationSet()
    {
        parent::afterConfigurationSet();

        $this->attributeMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
    }

    /**
     * Get attribute axis
     *
     * @return array
     */
    protected function getAxisAttributes()
    {
        $result = [];

        $attributeAxis = $this->groupManager->getRepository()->getAxisAttributes();

        foreach ($attributeAxis as $attribute) {
            $result[] = $attribute['code'];
        }

        return array_unique($result);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array_merge(
            parent::getConfigurationFields(),
            $this->attributeMappingMerger->getConfigurationField()
        );
    }
}
