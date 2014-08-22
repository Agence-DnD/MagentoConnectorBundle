<?php

namespace Pim\Bundle\MagentoConnectorBundle\Reader\ORM;

use Pim\Bundle\BaseConnectorBundle\Reader\ORM\EntityReader;
use Pim\Bundle\CatalogBundle\Model\AbstractAttribute;
use Doctrine\ORM\EntityManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\ConnectorMappingBundle\Mapper\MappingCollection;

/**
 * ORM reader for product
 *
 * @author    Julien SAnchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AttributeReader extends EntityReader
{
    const IMAGE_ATTRIBUTE_TYPE = 'pim_catalog_image';

    /**
     * @var MagentoMappingMerger
     */
    protected $attributeCodeMappingMerger;

    /**
     * @var string
     */
    protected $attributeCodeMapping = '';

    /**
     * Set attribute code mapping
     *
     * @param string $attributeCodeMapping
     *
     * @return AttributeReader
     */
    public function setAttributeCodeMapping($attributeCodeMapping)
    {
        $decodedAttributeCodeMapping = json_decode($attributeCodeMapping, true);

        if (!is_array($decodedAttributeCodeMapping)) {
            $decodedAttributeCodeMapping = [$decodedAttributeCodeMapping];
        }

        $this->attributeCodeMappingMerger->setMapping($decodedAttributeCodeMapping);

        return $this;
    }

    /**
     * Get attribute id mapping
     *
     * @return string
     */
    public function getAttributeCodeMapping()
    {
        return json_encode($this->attributeCodeMappingMerger->getMapping()->toArray());
    }

    /**
     * @param EntityManager        $em                     The entity manager
     * @param string               $className              The entity class name used
     * @param MagentoMappingMerger $attributeMappingMerger Attribute mapping merger
     */
    public function __construct(EntityManager $em, $className, MagentoMappingMerger $attributeCodeMappingMerger)
    {
        parent::__construct($em, $className);

        $this->attributeCodeMappingMerger = $attributeCodeMappingMerger;
    }

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        $attribute = parent::read();

        $attributeMapping = $this->attributeCodeMappingMerger->getMapping();

        while ($attribute !== null && $this->isAttributeIgnored($attribute, $attributeMapping)) {
            $attribute = parent::read();
        }

        return $attribute;
    }

    /**
     * Is the given attribute ignored ?
     *
     * @param AbstractAttribute $attribute
     * @param MappingCollection $attributeMapping
     *
     * @return boolean
     */
    protected function isAttributeIgnored(AbstractAttribute $attribute, MappingCollection $attributeMapping)
    {
        return in_array(strtolower($attributeMapping->getTarget($attribute->getCode())), $this->getIgnoredAttributes())
            || $attribute->getAttributeType() === self::IMAGE_ATTRIBUTE_TYPE;
    }

    /**
     * {@inheritdoc}
     */
    public function getQuery()
    {
        if (!$this->query) {
            $this->query = $this->em
                ->getRepository($this->className)
                ->createQueryBuilder('c')
                ->join('c.families', 'PimCatalogBundle:Family')
                ->getQuery();
        }

        return $this->query;
    }

    /**
     * Get all ignored attributes
     *
     * @return array
     */
    protected function getIgnoredAttributes()
    {
        return [
            'sku',
            'name',
            'description',
            'collection'
        ];
    }

    /**
     * Called after the configuration is set
     */
    protected function afterConfigurationSet()
    {
        parent::afterConfigurationSet();

        $this->attributeCodeMappingMerger->setParameters($this->getClientParameters(), $this->getSoapUrl());
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
