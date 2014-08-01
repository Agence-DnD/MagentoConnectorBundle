<?php

namespace Pim\Bundle\MagentoConnectorBundle\Normalizer;

use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\ConnectorMappingBundle\Mapper\MappingCollection;
use Symfony\Component\Serializer\Normalizer\scalar;
use Pim\Bundle\CatalogBundle\Entity\AttributeOption;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\MediaManager;

/**
 * A normalizer to transform a product entity into Magento Csv format
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductMagentoCsvNormalizer extends AbstractNormalizer
{
    /** @var ProductValueNormalizer */
    protected $productValueNormalizer;

    /** @var MediaManager */
    protected $mediaManager;

    /** @var string */
    protected $currencyCode;

    /**
     * Constructor
     * @param ChannelManager         $channelManager
     * @param MediaManager           $mediaManager
     * @param ProductValueNormalizer $productValueNormalizer
     */
    public function __construct(
        ChannelManager $channelManager,
        MediaManager $mediaManager,
        ProductValueNormalizer $productValueNormalizer,
        $currencyCode
    ) {
        parent::__construct($channelManager);

        $this->mediaManager           = $mediaManager;
        $this->productValueNormalizer = $productValueNormalizer;
        $this->currencyCode           = $currencyCode;
    }

    /**
     * Normalizes an object into a set of arrays/scalars
     *
     * @param object $object  object to normalize
     * @param string $format  format the normalization result will be encoded as
     * @param array  $context Context options for the normalizer
     *
     * @return array|scalar
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $processedItem = [];

        $processedItem[$context['defaultStoreView']] = [
            'sku'               => (string) $object->getIdentifier(),
            '_type'             => 'simple',
            '_product_websites' => $context['website'],
            'status'            => (integer) $context['enabled'],
            'visibility'        => (integer) $context['visibility'],
            '_attribute_set'    => $object->getFamily()->getCode()
        ];

        $normalizedValues = $this->getValues(
            $object,
            $context['magentoAttributes'],
            $context['magentoAttributesOptions'],
            $context['defaultLocale'],
            $context['defaultStoreView'],
            $context['channel'],
            $context['attributeCodeMapping'],
            false
        );

        $processedItem = array_merge_recursive($processedItem, $normalizedValues);

        foreach ($processedItem as $storeView => &$value) {
            if ($context['storeViewMapping']->getTarget($storeView) !== $context['defaultStoreView']) {
                unset($value['tax_class_id']);
            }
            $value['_store'] = $context['storeViewMapping']->getTarget($storeView);
        }

        return array_values($processedItem);
    }

    /**
     * Get all images of a product normalized
     *
     * @param ProductInterface $product
     *
     * @return array
     */
    public function getNormalizedImages(ProductInterface $product)
    {
        // TODO: Implement getNormalizedImages() method.
    }

    /**
     * Get values array for a given product
     *
     * @param ProductInterface  $product
     * @param array             $magentoAttributes
     * @param array             $magentoAttributesOptions
     * @param string            $defaultLocale
     * @param string            $defaultStoreView
     * @param string            $scopeCode
     * @param MappingCollection $attributeCodeMapping
     * @param boolean           $onlyLocalized
     *
     * @return array
     */
    protected function getValues(
        ProductInterface $product,
        $magentoAttributes,
        $magentoAttributesOptions,
        $defaultLocale,
        $defaultStoreView,
        $scopeCode,
        MappingCollection $attributeCodeMapping,
        $onlyLocalized
    ) {
        $context = [
            'identifier'               => $product->getIdentifier(),
            'scopeCode'                => $scopeCode,
            'onlyLocalized'            => $onlyLocalized,
            'magentoAttributes'        => $magentoAttributes,
            'magentoAttributesOptions' => $magentoAttributesOptions,
            'attributeCodeMapping'     => $attributeCodeMapping,
            'currencyCode'             => $this->currencyCode
        ];

        $normalizedValues = [];

        foreach ($product->getValues() as $productValue) {
            $locale = $productValue->getLocale();
            $code = $productValue->getAttribute()->getCode();
            $context['localeCode'] = $locale;
            if ($attributeCodeMapping->containsKey($code)) {
                if ($productValue->getData() instanceof AttributeOption) {
                    $this->productValueNormalizer->addIgnoredOptionMatchingAttributes($code);
                }

                $normalizedValue = $this->productValueNormalizer->normalize(
                    $productValue,
                    'MagentoArray',
                    $context
                );

                if (count($normalizedValue) === 1 && !empty(end($normalizedValue))) {
                    if ('tax_class_id' === $code) {
                        $value = (integer) end($normalizedValue);
                    } else {
                        $value = end($normalizedValue);
                    }

                    if (!empty($locale) && $locale != $defaultLocale) {
                        $storeView = $locale;
                    } else {
                        $storeView = $defaultStoreView;
                    }

                    $keys = array_keys($normalizedValue);
                    $normalizedValues[$storeView][end($keys)] = $value;
                }
            }
        }

        return $normalizedValues;
    }

} 