<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\CategoryMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AssociationTypeManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\CurrencyManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\AbstractNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\Exception\NormalizeException;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\TransformBundle\Converter\MetricConverter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Magento csv product processor
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductToMagentoCsvProcessor extends AbstractProductToMagentoCsvProcessor
{
    /** @var MetricConverter */
    protected $metricConverter;

    /** @var string */
    protected $pimGrouped;

    /** @var string */
    protected $smallImageAttribute;

    /** @var string */
    protected $baseImageAttribute;

    /** @var string */
    protected $thumbnailAttribute;

    /** @var AssociationTypeManager $associationTypeManager */
    protected $associationTypeManager;

    /** @var boolean */
    protected $initialized;

    /** @var CategoryMappingManager  */
    protected $categoryMappingManager;

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param NormalizerGuesser                   $normalizerGuesser
     * @param LocaleManager                       $localeManager
     * @param MagentoMappingMerger                $storeViewMappingMerger
     * @param MagentoSoapClientParametersRegistry $clientParametersRegistry
     * @param AttributeManager                    $attributeManager
     * @param AssociationTypeManager              $associationTypeManager
     * @param CurrencyManager                     $currencyManager
     * @param ChannelManager                      $channelManager
     * @param MagentoMappingMerger                $attributeMappingMerger
     * @param MagentoMappingMerger                $categoryMappingMerger
     * @param MetricConverter                     $metricConverter
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        LocaleManager $localeManager,
        MagentoMappingMerger $storeViewMappingMerger,
        MagentoSoapClientParametersRegistry $clientParametersRegistry,
        AttributeManager $attributeManager,
        AssociationTypeManager $associationTypeManager,
        CurrencyManager $currencyManager,
        ChannelManager $channelManager,
        MagentoMappingMerger $attributeMappingMerger,
        MagentoMappingMerger $categoryMappingMerger,
        MetricConverter $metricConverter,
        CategoryMappingManager $categoryMappingManager
    ) {
        parent::__construct(
            $webserviceGuesser,
            $normalizerGuesser,
            $localeManager,
            $storeViewMappingMerger,
            $currencyManager,
            $channelManager,
            $categoryMappingMerger,
            $attributeMappingMerger,
            $clientParametersRegistry
        );

        $this->attributeManager       = $attributeManager;
        $this->associationTypeManager = $associationTypeManager;
        $this->metricConverter        = $metricConverter;
        $this->categoryMappingManager = $categoryMappingManager;
    }

    /**
     * {@inheritDoc}
     */
    public function process($item)
    {
        if (!$this->initialized) {
            $this->beforeExecute();
        }

        $processedItem = $this->normalizeProduct($item, $this->globalContext);
//        die(var_dump($processedItem));

//        $magentoProducts = $this->webservice->getProductsStatus($item);

        $this->stepExecution->incrementSummaryInfo('process');
//        printf('ITEM');
//        die(var_dump($item));
        return $processedItem;
    }

    /**
     * Get pim grouped
     *
     * @return string
     */
    public function getPimGrouped()
    {
        return $this->pimGrouped;
    }

    /**
     * Set pim grouped
     *
     * @param string $pimGrouped
     *
     * @return ProductProcessor
     */
    public function setPimGrouped($pimGrouped)
    {
        $this->pimGrouped = $pimGrouped;

        return $this;
    }

    /**
     * Get small image
     *
     * @return string
     */
    public function getSmallImageAttribute()
    {
        return $this->smallImageAttribute;
    }

    /**
     * Set small image
     *
     * @param string $smallImageAttribute
     *
     * @return ProductProcessor
     */
    public function setSmallImageAttribute($smallImageAttribute)
    {
        $this->smallImageAttribute = $smallImageAttribute;

        return $this;
    }

    /**
     * Get base image attribute
     *
     * @return string
     */
    public function getBaseImageAttribute()
    {
        return $this->baseImageAttribute;
    }

    /**
     * Set base image attribute
     *
     * @param string $baseImageAttribute
     *
     * @return ProductProcessor
     */
    public function setBaseImageAttribute($baseImageAttribute)
    {
        $this->baseImageAttribute = $baseImageAttribute;

        return $this;
    }

    /**
     * Get thumbnail attribute
     *
     * @return string
     */
    public function getThumbnailAttribute()
    {
        return $this->thumbnailAttribute;
    }

    /**
     * Set thumbnail attribute
     *
     * @param string $thumbnailAttribute
     *
     * @return ProductProcessor
     */
    public function setThumbnailAttribute($thumbnailAttribute)
    {
        $this->thumbnailAttribute = $thumbnailAttribute;

        return $this;
    }

    /**
     * Function called before all process
     */
    protected function beforeExecute()
    {
        parent::beforeExecute();

        $channelCategoryRoot     = $this->channelManager->getChannelByCode($this->channel)->getCategory();
        $magentoRootCategoryId   = $this->globalContext['userCategoryMapping']->getTarget($channelCategoryRoot->getCode());
        $magentoCategoryTree     = $this->webservice->getCategoryTree($magentoRootCategoryId);
        $magentoMappingCategory  = $this->categoryMappingManager->getMappingByRootAndMagentoUrl($channelCategoryRoot, $this->getSoapUrl());


        $this->globalContext = array_merge(
            $this->globalContext,
            [
                'locales'                => $this->localeManager->getActiveCodes(),
                'defaultLocale'          => $this->getDefaultLocale(),
                'pimGrouped'             => $this->pimGrouped,
                'smallImageAttribute'    => $this->smallImageAttribute,
                'baseImageAttribute'     => $this->baseImageAttribute,
                'thumbnailAttribute'     => $this->thumbnailAttribute,
                'defaultStoreView'       => $this->getDefaultStoreView(),
                'enabled'                => $this->getEnabled(),
                'visibility'             => $this->getVisibility(),
                'magentoCategoryTree'    => $magentoCategoryTree,
                'channelCategoryRoot'    => $channelCategoryRoot,
                'magentoCategoryMapping' => $magentoMappingCategory
            ]
        );
        $this->initialized = true;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array_merge(
            parent::getConfigurationFields(),
            [
                'smallImageAttribute' => [
                    'type' => 'choice',
                    'options' => [
                        'choices' => $this->attributeManager->getImageAttributeChoice(),
                        'help'    => 'pim_magento_connector.export.smallImageAttribute.help',
                        'label'   => 'pim_magento_connector.export.smallImageAttribute.label',
                        'attr' => [
                            'class' => 'select2'
                        ]
                    ]
                ],
                'baseImageAttribute' => [
                    'type' => 'choice',
                    'options' => [
                        'choices' => $this->attributeManager->getImageAttributeChoice(),
                        'help'    => 'pim_magento_connector.export.baseImageAttribute.help',
                        'label'   => 'pim_magento_connector.export.baseImageAttribute.label',
                        'attr' => [
                            'class' => 'select2'
                        ]
                    ]
                ],
                'thumbnailAttribute' => [
                    'type' => 'choice',
                    'options' => [
                        'choices' => $this->attributeManager->getImageAttributeChoice(),
                        'help'    => 'pim_magento_connector.export.thumbnailAttribute.help',
                        'label'   => 'pim_magento_connector.export.thumbnailAttribute.label',
                        'attr' => [
                            'class' => 'select2'
                        ]
                    ]
                ],
                'pimGrouped' => [
                    'type'    => 'choice',
                    'options' => [
                        'choices' => $this->associationTypeManager->getAssociationTypeChoices(),
                        'help'    => 'pim_magento_connector.export.pimGrouped.help',
                        'label'   => 'pim_magento_connector.export.pimGrouped.label',
                        'attr' => [
                            'class' => 'select2'
                        ]
                    ]
                ]
            ]
        );
    }

    /**
     * Normalize the given product
     *
     * @param ProductInterface $product
     * @param array            $context
     *
     * @throws InvalidItemException If a normalization error occurs
     *
     * @return array                Processed item
     */
    protected function normalizeProduct(ProductInterface $product, $context)
    {
        try {
            $processedItem = $this->productNormalizer->normalize(
                $product,
                AbstractNormalizer::MAGENTO_CSV_FORMAT,
                $context
            );
        } catch (NormalizeException $e) {
            throw new InvalidItemException(
                $e->getMessage(),
                [
                    'id'                                                 => $product->getId(),
                    $product->getIdentifier()->getAttribute()->getCode() => $product->getIdentifier()->getData(),
                    'label'                                              => $product->getLabel(),
                    'family'                                             => $product->getFamily()->getCode()
                ]
            );
        }

        return $processedItem;
    }

}
