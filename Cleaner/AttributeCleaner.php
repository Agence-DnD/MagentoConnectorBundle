<?php

namespace Pim\Bundle\MagentoConnectorBundle\Cleaner;

use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Validator\Constraints\HasValidCredentials;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Doctrine\ORM\EntityManager;

/**
 * Magento attribute cleaner
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @HasValidCredentials()
 */
class AttributeCleaner extends Cleaner
{
    const ATTRIBUTE_DELETED = 'Attribute deleted';

    /**
     * @var MagentoMappingMerger
     */
    protected $attributeCodeMappingMerger;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var string
     */
    protected $attributeClassName;

    /**
     * @var string
     */
    protected $attributeCodeMapping;

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param MagentoMappingMerger                $attributeMappingMerger
     * @param EntityManager                       $em
     * @param string                              $attributeClassName
     * @param MagentoSoapClientParametersRegistry $clientParametersRegistry
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        MagentoMappingMerger $attributeCodeMappingMerger,
        EntityManager $em,
        $attributeClassName,
        MagentoSoapClientParametersRegistry $clientParametersRegistry
    ) {
        parent::__construct($webserviceGuesser, $clientParametersRegistry);

        $this->attributeCodeMappingMerger = $attributeCodeMappingMerger;
        $this->em                         = $em;
        $this->attributeClassName         = $attributeClassName;
    }

    /**
     * Set attribute code mapping
     *
     * @param string $attributeCodeMapping
     *
     * @return AttributeCleaner
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
     * @return string
     */
    public function getAttributeCodeMapping()
    {
        return json_encode($this->attributeCodeMappingMerger->getMapping()->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        parent::beforeExecute();

        $magentoAttributes = $this->webservice->getAllAttributes();

        foreach ($magentoAttributes as $attribute) {
            $this->cleanAttribute($attribute, $magentoAttributes);
        }
    }

    /**
     * Clean the given attribute
     * @param array $attribute
     * @param array $magentoAttributes
     */
    protected function cleanAttribute(array $attribute, array $magentoAttributes)
    {
        $magentoAttributeCode = $attribute['code'];
        $pimAttributeCode     = $this->attributeCodeMappingMerger->getMapping()->getSource($magentoAttributeCode);
        $pimAttribute         = $this->getAttribute($pimAttributeCode);

        if (!in_array($attribute['code'], $this->getIgnoredAttributes()) &&
            (
                $pimAttributeCode == null ||
                (!$pimAttribute || ($pimAttribute && !$pimAttribute->getFamilies()))
            )
        ) {
            try {
                $this->handleAttributeNotInPimAnymore($attribute);
            } catch (SoapCallException $e) {
                throw new InvalidItemException($e->getMessage(), [$attribute['code']]);
            }
        }
    }

    /**
     * Handle deletion or disableing of attributes which are not in PIM anymore
     * @param array $attribute
     */
    protected function handleAttributeNotInPimAnymore(array $attribute)
    {
        if ($this->notInPimAnymoreAction === self::DELETE) {
            $this->webservice->deleteAttribute($attribute['code']);
            $this->stepExecution->incrementSummaryInfo(self::ATTRIBUTE_DELETED);
        }
    }

    /**
     * Get attribute for attribute code
     * @param string $attributeCode
     *
     * @return mixed
     */
    protected function getAttribute($attributeCode)
    {
        return $this->em->getRepository($this->attributeClassName)->findOneBy(['code' => $attributeCode]);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        $configurationFields = parent::getConfigurationFields();

        $configurationFields['notInPimAnymoreAction']['options']['choices'] = [
            Cleaner::DO_NOTHING => 'pim_magento_connector.export.do_nothing.label',
            Cleaner::DELETE     => 'pim_magento_connector.export.delete.label'
        ];

        $configurationFields['notInPimAnymoreAction']['options']['help'] =
            'pim_magento_connector.export.notInPimAnymoreAction.help';
        $configurationFields['notInPimAnymoreAction']['options']['label'] =
            'pim_magento_connector.export.notInPimAnymoreAction.label';

        return array_merge(
            $configurationFields,
            $this->attributeCodeMappingMerger->getConfigurationField()
        );
    }

    /**
     * Called after the configuration is set
     */
    protected function afterConfigurationSet()
    {
        $this->attributeCodeMappingMerger->setParameters($this->getClientParameters(), $this->getDefaultStoreView());
    }

    /**
     * Get all ignored attributes
     * @return array
     */
    protected function getIgnoredAttributes()
    {
        return [
            'visibility',
            'old_id',
            'news_from_date',
            'news_to_date',
            'image_label',
            'small_image_label',
            'thumbnail_label',
            'country_of_manufacture',
            'price_type',
            'links_purchased_separately',
            'samples_title',
            'links_title',
            'links_exist',
            'tax_class_id',
            'status',
            'url_key',
            'url_path',
            'created_at',
            'meta_title',
            'updated_at',
            'meta_description',
            'meta_keyword',
            'is_recurring',
            'recurring_profile',
            'options_container',
            'custom_design',
            'custom_design_from',
            'custom_design_to',
            'custom_layout_update',
            'page_layout',
            'price',
            'category_ids',
            'required_options',
            'has_options',
            'sku_type',
            'weight_type',
            'shipment_type',
            'group_price',
            'special_price',
            'special_from_date',
            'special_to_date',
            'cost',
            'tier_price',
            'minimal_price',
            'msrp_enabled',
            'msrp_display_actual_price_type',
            'msrp',
            'price_view',
            'gift_message_available',
        ];
    }
}
