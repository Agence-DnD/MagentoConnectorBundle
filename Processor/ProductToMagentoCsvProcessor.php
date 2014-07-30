<?php

namespace Pim\Bundle\MagentoConnectorBundle\Processor;

use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;
use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AttributeManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AssociationTypeManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\CurrencyManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\AbstractNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductMagentoCsvNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Magento Csv product processor
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductToMagentoCsvProcessor extends AbstractProcessor
{
    const MAGENTO_VISIBILITY_CATALOG_SEARCH = 4;

    /** @var boolean $enabled */
    protected $enabled;

    /** @var integer $visibility */
    protected $visibility = self::MAGENTO_VISIBILITY_CATALOG_SEARCH;

    /** @var string */
    protected $pimGrouped;

    /** @var string */
    protected $smallImageAttribute;

    /** @var string */
    protected $baseImageAttribute;

    /** @var string */
    protected $thumbnailAttribute;

    /** @var AttributeManager $attributeManager */
    protected $attributeManager;

    /** @var AssociationTypeManager $associationTypeManager */
    protected $associationTypeManager;

    /** @var ProductMagentoCsvNormalizer $productNormalizer */
    protected $productNormalizer;

    /** @var array $magentoStoreViews */
    protected $magentoStoreViews;

    /** @var array $magentoAttributes */
    protected $magentoAttributes;

    /** @var array $attributesToBeSent */
    protected $attributesToBeSent;

    /** @var array $magentoAttributesOptions */
    protected $magentoAttributesOptions;

    /** @var boolean $initialized */
    protected $initialized = false;

    /** @var ChannelManager $channelManager */
    protected $channelManager;

    /** @var CurrencyManager $currencyManager */
    protected $currencyManager;

    /** @var string $attributeCodeMapping */
    protected $attributeCodeMapping;

    /** @var MagentoMappingMerger $attributeMappingMerger */
    protected $attributeMappingMerger;

    /**
     * @var Currency
     * @Assert\NotBlank(groups={"Execution"})
     */
    protected $currency;

    /**
     * @Assert\NotBlank(groups={"Execution"})
     */
    protected $channel;

    /**
     * {@inheritDoc}
     * @param AttributeManager       $attributeManager
     * @param AssociationTypeManager $associationTypeManager
     * @param CurrencyManager        $currencyManager
     * @param ChannelManager         $channelManager
     * @param MagentoMappingMerger   $attributeMappingMerger
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
        MagentoMappingMerger $attributeMappingMerger
    ) {
        parent::__construct(
            $webserviceGuesser,
            $normalizerGuesser,
            $localeManager,
            $storeViewMappingMerger,
            $clientParametersRegistry
        );

        $this->attributeManager       = $attributeManager;
        $this->associationTypeManager = $associationTypeManager;
        $this->currencyManager        = $currencyManager;
        $this->channelManager         = $channelManager;
        $this->attributeMappingMerger = $attributeMappingMerger;
    }

    /**
     * Function called before all process
     */
    protected function beforeExecute()
    {
        parent::beforeExecute();

        if (!$this->initialized) {
            $this->magentoStoreViews        = $this->webservice->getStoreViewsList();
            $this->magentoAttributes        = $this->webservice->getAllAttributes();
            $this->magentoAttributesOptions = $this->webservice->getAllAttributesOptions();
            $this->productNormalizer        = $this->normalizerGuesser->getProductMagentoCsvNormalizer(
                $this->getClientParameters()
            );

            $this->initialized = true;
        }

        $this->globalContext = array_merge(
            $this->globalContext,
            [
                'channel'                  => $this->channel,
                'locales'                  => $this->localeManager->getActiveCodes(),
                'defaultLocale'            => $this->getDefaultLocale(),
                'website'                  => $this->website,
                'magentoAttributes'        => $this->magentoAttributes,
                'magentoAttributesOptions' => $this->magentoAttributesOptions,
                'magentoStoreViews'        => $this->magentoStoreViews,
                'pimGrouped'               => $this->pimGrouped,
                'smallImageAttribute'      => $this->smallImageAttribute,
                'baseImageAttribute'       => $this->baseImageAttribute,
                'thumbnailAttribute'       => $this->thumbnailAttribute,
                'defaultStoreView'         => $this->getDefaultStoreView(),
                'attributeCodeMapping'     => $this->attributeMappingMerger->getMapping()
            ]
        );
//        die(var_dump($this->globalContext));
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
     * Normalize the given product
     *
     * @param ProductInterface $product
     * @param array            $context
     *
     * @throws InvalidItemException If a normalization error occurs
     *
     * @return array                processed item
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

    /**
     * {@inheritDoc}
     */
    public function process($item)
    {
        $this->beforeExecute();

        $processedItem = $this->normalizeProduct($item, $this->globalContext);
        die(var_dump($processedItem));

//        $magentoProducts = $this->webservice->getProductsStatus($item);


        printf('ITEM');
        die(var_dump($item));
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
     * Get channel
     *
     * @return string channel
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * Set channel
     *
     * @param string $channel channel
     *
     * @return AbstractProcessor
     */
    public function setChannel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * Get currency
     *
     * @return string currency
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * Set currency
     *
     * @param string $currency currency
     *
     * @return AbstractProcessor
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * get enabled
     *
     * @return string enabled
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set enabled
     *
     * @param string $enabled enabled
     *
     * @return AbstractProcessor
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * get visibility
     *
     * @return string visibility
     */
    public function getVisibility()
    {
        return $this->visibility;
    }

    /**
     * Set visibility
     *
     * @param string $visibility visibility
     *
     * @return AbstractProcessor
     */
    public function setVisibility($visibility)
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Get attribute code mapping
     *
     * @return string attributeCodeMapping
     */
    public function getAttributeCodeMapping()
    {
        return json_encode($this->attributeMappingMerger->getMapping()->toArray());
    }

    /**
     * Set attribute code mapping
     *
     * @param string $attributeCodeMapping attributeCodeMapping
     *
     * @return AbstractProcessor
     */
    public function setAttributeCodeMapping($attributeCodeMapping)
    {
        $this->attributeMappingMerger->setMapping(json_decode($attributeCodeMapping, true));

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigurationFields()
    {
        return array_merge(
            parent::getConfigurationFields(),
            $this->attributeMappingMerger->getConfigurationField(),
            [
                'enabled' => [
                    'type'    => 'switch',
                    'options' => [
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.enabled.help',
                        'label'    => 'pim_magento_connector.export.enabled.label'
                    ]
                ],
                'visibility' => [
                    'type'    => 'text',
                    'options' => [
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.visibility.help',
                        'label'    => 'pim_magento_connector.export.visibility.label'
                    ]
                ],
                'currency' => [
                    'type'    => 'choice',
                    'options' => [
                        'choices'  => $this->currencyManager->getCurrencyChoices(),
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.currency.help',
                        'label'    => 'pim_magento_connector.export.currency.label',
                        'attr' => [
                            'class' => 'select2'
                        ]
                    ]
                ],
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
                ],
                'channel' => [
                    'type'    => 'choice',
                    'options' => [
                        'choices'  => $this->channelManager->getChannelChoices(),
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.channel.help',
                        'label'    => 'pim_magento_connector.export.channel.label'
                    ]
                ]
            ]
        );
    }
}