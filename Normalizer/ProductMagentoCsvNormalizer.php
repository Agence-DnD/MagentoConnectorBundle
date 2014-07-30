<?php

namespace Pim\Bundle\MagentoConnectorBundle\Normalizer;

use Pim\Bundle\CatalogBundle\Model\ProductInterface;
use Pim\Bundle\ConnectorMappingBundle\Mapper\MappingCollection;
use Symfony\Component\Serializer\Normalizer\scalar;

/**
 * A normalizer to transform a product entity into Magento Csv format
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductMagentoCsvNormalizer extends AbstractNormalizer
{
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
//        die(var_dump($context['attributeCodeMapping']));
        $processedItem = [];

        foreach ($object->getValues() as $label => $value) {
            printf(PHP_EOL . 'LABEL' . PHP_EOL);
            var_dump($label);
            printf(PHP_EOL . 'VALUE' . PHP_EOL);
            var_dump($value);
        }
        die();
        $processedItem['sku']   = (string) $object->getIdentifier();
        $processedItem['_type'] = 'simple';
//        $processedItem[''] = $object->;
//        $processedItem[''] = $object->;

        $processedItem['name']  = $object->getLabel();
//        $processedItem['price'] = $object->getPrice();
//        $processedItem[''] = $object->;
//        $processedItem[''] = $object->;
//        $processedItem[''] = $object->;


        die(var_dump($processedItem));
        $processedItem['description'] =
            array(
                'description'       => 'Some description',
                '_attribute_set'    => 'Default',
                'short_description' => 'Some short description',
                '_product_websites' => 'base',
                'status'            => Mage_Catalog_Model_Product_Status::STATUS_ENABLED,
                'visibility'        => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                'tax_class_id'      => 0,
                'is_in_stock'       => 1,

                'price'  => rand(1, 1000),
                'weight' => rand(1, 1000),
                'qty'    => rand(1, 30)
            );
        return $object;
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
     * Get the default product with all attributes (ie : event the non localizable ones)
     *
     * @param ProductInterface  $product                  The given product
     * @param array             $magentoAttributes        Attribute list from Magento
     * @param array             $magentoAttributesOptions Attribute options list from Magento
     * @param integer           $attributeSetId           Attribute set id
     * @param string            $defaultLocale            Default locale
     * @param string            $channel                  Channel
     * @param string            $website                  Website name
     * @param MappingCollection $categoryMapping          Root category mapping
     * @param MappingCollection $attributeMapping         Attribute mapping
     * @param string            $pimGrouped               Pim grouped association code
     * @param bool              $create                   Is it a creation ?
     * @param array             $context                  Context
     *
     * @return array The default product data
     */
    protected function getDefaultProduct(
        ProductInterface $product,
        $defaultLocale,
        $website,
        $defaultStoreValue
    ) {
        $sku           = (string) $product->getIdentifier();
        $defaultValues = $this->getValues(
            $product,
            $magentoAttributes,
            $magentoAttributesOptions,
            $defaultLocale,
            $channel,
            $categoryMapping,
            $attributeMapping,
            false
        );

        $defaultValues['websites'] = [$website];

        if ($create) {
            if ($this->hasGroupedProduct($product, $pimGrouped)) {
                $productType = self::MAGENTO_GROUPED_PRODUCT_KEY;
            } else {
                $productType = self::MAGENTO_SIMPLE_PRODUCT_KEY;
            }

            //For the default storeview we create an entire product
            $defaultProduct = [
                $productType,
                $attributeSetId,
                $sku,
                $defaultValues,
                $defaultStoreValue
            ];
        } else {
            $defaultProduct = [
                $sku,
                $defaultValues,
                $defaultStoreValue,
                'sku'
            ];
        }

        return $defaultProduct;
    }

} 