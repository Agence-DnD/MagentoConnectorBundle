<?php

namespace Pim\Bundle\MagentoConnectorBundle\Webservice;

use Pim\Bundle\CatalogBundle\Model\ProductInterface;

/**
 * A magento soap client to abstract interaction with the magento api
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Webservice
{
    const SOAP_ACTION_CATALOG_PRODUCT_CREATE                 = 'catalog_product.create';
    const SOAP_ACTION_CATALOG_PRODUCT_UPDATE                 = 'catalog_product.update';
    const SOAP_ACTION_CATALOG_PRODUCT_DELETE                 = 'catalog_product.delete';
    const SOAP_ACTION_CATALOG_PRODUCT_CURRENT_STORE          = 'catalog_product.currentStore';
    const SOAP_ACTION_CATALOG_PRODUCT_LIST                   = 'catalog_product.list';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_LIST             = 'product_attribute_set.list';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_ATTRIBUTE_ADD    = 'product_attribute_set.attributeAdd';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_ATTRIBUTE_REMOVE = 'product_attribute_set.attributeRemove';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_CREATE           = 'product_attribute_set.create';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_GROUP_ADD        = 'product_attribute_set.groupAdd';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_GROUP_REMOVE     = 'product_attribute_set.groupRemove';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_GROUP_RENAME     = 'product_attribute_set.groupRename';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_REMOVE           = 'product_attribute_set.remove';
    const SOAP_ACTION_PRODUCT_ATTRIBUTE_LIST                 = 'catalog_product_attribute.list';
    const SOAP_ACTION_ATTRIBUTE_OPTION_LIST                  = 'catalog_product_attribute.options';
    const SOAP_ACTION_ATTRIBUTE_OPTION_ADD                   = 'catalog_product_attribute.addOption';
    const SOAP_ACTION_ATTRIBUTE_OPTION_REMOVE                = 'catalog_product_attribute.removeOption';
    const SOAP_ACTION_ATTRIBUTE_CREATE                       = 'product_attribute.create';
    const SOAP_ACTION_ATTRIBUTE_UPDATE                       = 'product_attribute.update';
    const SOAP_ACTION_ATTRIBUTE_REMOVE                       = 'product_attribute.remove';
    const SOAP_ACTION_STORE_LIST                             = 'store.list';
    const SOAP_ACTION_PRODUCT_MEDIA_CREATE                   = 'catalog_product_attribute_media.create';
    const SOAP_ACTION_PRODUCT_MEDIA_LIST                     = 'catalog_product_attribute_media.list';
    const SOAP_ACTION_PRODUCT_MEDIA_REMOVE                   = 'catalog_product_attribute_media.remove';
    const SOAP_ACTION_CATEGORY_TREE                          = 'catalog_category.tree';
    const SOAP_ACTION_CATEGORY_CREATE                        = 'catalog_category.create';
    const SOAP_ACTION_CATEGORY_UPDATE                        = 'catalog_category.update';
    const SOAP_ACTION_CATEGORY_DELETE                        = 'catalog_category.delete';
    const SOAP_ACTION_CATEGORY_MOVE                          = 'catalog_category.move';
    const SOAP_ACTION_LINK_LIST                              = 'catalog_product_link.list';
    const SOAP_ACTION_LINK_REMOVE                            = 'catalog_product_link.remove';
    const SOAP_ACTION_LINK_CREATE                            = 'catalog_product_link.assign';
    const SOAP_ACTION_IMPORT_ENTITIES_API_IMPORT             = 'import.importEntities';

    const SOAP_DEFAULT_STORE_VIEW                            = 'default';
    const IMAGES                                             = 'images';
    const SOAP_ATTRIBUTE_ID                                  = 'attribute_id';
    const SMALL_IMAGE                                        = 'small_image';
    const BASE_IMAGE                                         = 'image';
    const THUMBNAIL                                          = 'thumbnail';
    const SELECT                                             = 'select';
    const MULTI_SELECT                                       = 'multiselect';

    const MAXIMUM_CALLS                                      = 1;
    const CREATE_PRODUCT_SIZE                                = 5;
    const CREATE_CONFIGURABLE_SIZE                           = 4;

    const CONFIGURABLE_IDENTIFIER_PATTERN                    = 'conf-%s';

    const MAGENTO_STATUS_DISABLE                             = 2;

    const ADMIN_STOREVIEW                                    = 0;

    protected $client;

    protected $magentoAttributeSets;
    protected $magentoStoreViewList;
    protected $magentoAttributes = [];

    protected $attributeList       = [];
    protected $attributes          = [];
    protected $attributeSetList    = [];
    protected $attributeOptionList = [];
    protected $categories          = [];

    /**
     * Constructor
     * @param MagentoSoapClient $client
     */
    public function __construct(MagentoSoapClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get attribute options for all attributes
     *
     * @return array
     */
    public function getAllAttributesOptions()
    {
        $attributeList = $this->getAllAttributes();

        foreach ($attributeList as $attributeCode => $attribute) {
            if (in_array($attribute['type'], [self::SELECT, self::MULTI_SELECT])) {
                if (!isset($this->attributeOptionList[$attributeCode])) {
                    $this->attributeOptionList[$attributeCode] = $this->getAttributeOptions($attributeCode);
                }
            }
        }

        return $this->attributeOptionList;
    }

    /**
     * Get all attributes from magento
     *
     * @return array
     */
    public function getAllAttributes()
    {
        if (!$this->attributeList) {
            $attributeSetList = $this->getAttributeSetList();
            foreach (array_keys($attributeSetList) as $attributeSet) {
                $attributes = $this->getAttributeList($attributeSet);
                $this->attributeSetList[$attributeSet] = [];

                foreach ($attributes as $attribute) {
                    $this->attributeList[$attribute['code']]              = $attribute;
                    $this->attributeSetList[$attributeSet][$attributeSet] = $attribute['code'];
                }
            }
        }

        return $this->attributeList;
    }

    /**
     * Get attribute list for a given attribute set code
     *
     * @param string $attributeSetCode the attribute set code
     *
     * @return array
     */
    public function getAttributeList($attributeSetCode)
    {
        if (!isset($this->attributes[$attributeSetCode])) {
            $id = $this->getAttributeSetId($attributeSetCode);

            $this->attributes[$attributeSetCode] = $this->client->call(
                self::SOAP_ACTION_PRODUCT_ATTRIBUTE_LIST,
                $id
            );
        }

        return $this->attributes[$attributeSetCode];
    }

    /**
     * Get products status in magento (do they exist ?)
     * @param array $products the given products
     *
     * @return array
     */
    public function getProductsStatus(array $products = [])
    {
        $skus = $this->getProductsIds($products);

        return $this->getStatusForSkus($skus);
    }

    /**
     * Get configurables status in magento (do they exist ?)
     * @param array $configurables the given configurables
     *
     * @return array
     */
    public function getConfigurablesStatus(array $configurables = [])
    {
        $skus = $this->getConfigurablesIds($configurables);

        return $this->getStatusForSkus($skus);
    }

    /**
     * Get magento attributeSets from the magento api
     * @param string $code the attributeSet id
     *
     * @throws AttributeSetNotFoundException If If the attribute doesn't exist on Magento side
     *
     * @return void
     */
    public function getAttributeSetId($code)
    {
        $this->getAttributeSetList();

        if (isset($this->magentoAttributeSets[$code])) {
            return $this->magentoAttributeSets[$code];
        } else {
            throw new AttributeSetNotFoundException(
                'The attribute set for code "' . $code . '" was not found on Magento. Please create it before proceed.'
            );
        }
    }

    /**
     * Get magento storeview list from magento
     *
     * @return array
     */
    public function getStoreViewsList()
    {
        if (!$this->magentoStoreViewList) {
            $this->magentoStoreViewList = $this->client->call(
                self::SOAP_ACTION_STORE_LIST
            );
        }

        return $this->magentoStoreViewList;
    }

    /**
     * Get all images attached to a product
     *
     * @param string $sku               The product sku
     * @param string $defaultLocalStore
     *
     * @return array
     */
    public function getImages($sku, $defaultLocalStore)
    {
        try {
            $images = $this->client->call(
                self::SOAP_ACTION_PRODUCT_MEDIA_LIST,
                [
                    $sku,
                    $defaultLocalStore,
                    'sku'
                ]
            );
        } catch (\Exception $e) {
            $images = [];
        }

        return $images;
    }

    /**
     * Send all product images
     *
     * @param array $images All images to send
     */
    public function sendImages($images)
    {
        foreach ($images as $image) {
            $this->client->addCall(
                [
                    self::SOAP_ACTION_PRODUCT_MEDIA_CREATE,
                    $image
                ]
            );
        }
    }

    /**
     * Delete image for a given sku and a given filename
     * @param string $sku
     * @param string $imageFilename
     *
     * @return string
     */
    public function deleteImage($sku, $imageFilename)
    {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_MEDIA_REMOVE,
            [
                $sku,
                $imageFilename,
                'sku'
            ]
        );
    }

    /**
     * Add the call to update the given product part
     * @param array $productPart
     */
    public function updateProductPart($productPart)
    {
        $this->client->addCall(
            [self::SOAP_ACTION_CATALOG_PRODUCT_UPDATE, $productPart]
        );
    }

    /**
     * Add a call for the given product part
     * @param array $productPart
     */
    public function sendProduct($productPart)
    {
        if (count($productPart) == self::CREATE_PRODUCT_SIZE ||
            count($productPart) == self::CREATE_CONFIGURABLE_SIZE &&
            $productPart[self::CREATE_CONFIGURABLE_SIZE - 1] != 'sku'
        ) {
            $resource = self::SOAP_ACTION_CATALOG_PRODUCT_CREATE;
        } else {
            $resource = self::SOAP_ACTION_CATALOG_PRODUCT_UPDATE;
        }

        $this->client->addCall([$resource, $productPart]);
    }

    /**
     * Get categories status from Magento
     *
     * @return array
     */
    public function getCategoriesStatus()
    {
        if (!$this->categories) {
            $tree = $this->client->call(
                self::SOAP_ACTION_CATEGORY_TREE
            );

            $this->categories = $this->flattenCategoryTree($tree);
        }

        return $this->categories;
    }

    /**
     * Send new category
     * @param array $category
     *
     * @return int
     */
    public function sendNewCategory(array $category)
    {
        return $this->client->call(
            self::SOAP_ACTION_CATEGORY_CREATE,
            $category
        );
    }

    /**
     * Send update category
     * @param array $category
     *
     * @return int
     */
    public function sendUpdateCategory(array $category)
    {
        return $this->client->call(
            self::SOAP_ACTION_CATEGORY_UPDATE,
            $category
        );
    }

    /**
     * Send move category
     * @param array $category
     *
     * @return int
     */
    public function sendMoveCategory(array $category)
    {
        return $this->client->call(
            self::SOAP_ACTION_CATEGORY_MOVE,
            $category
        );
    }

    /**
     * Flatten the category tree from magento
     * @param array $tree
     *
     * @return array
     */
    protected function flattenCategoryTree(array $tree)
    {
        $result = [$tree['category_id'] => $tree];

        foreach ($tree['children'] as $children) {
            $result = $result + $this->flattenCategoryTree($children);
        }

        return $result;
    }

    /**
     * Disable the given category on Magento
     * @param string $categoryId
     *
     * @return int
     */
    public function disableCategory($categoryId)
    {
        return $this->client->call(
            self::SOAP_ACTION_CATEGORY_UPDATE,
            [
                $categoryId,
                [
                    'is_active'         => 0,
                    'available_sort_by' => 1,
                    'default_sort_by'   => 1
                ]
            ]
        );
    }

    /**
     * Delete the given category on Magento
     *
     * @param string $categoryId
     *
     * @return int
     */
    public function deleteCategory($categoryId)
    {
        return $this->client->call(
            self::SOAP_ACTION_CATEGORY_DELETE,
            [
                $categoryId
            ]
        );
    }

    /**
     * Get associations status
     * @param ProductInterface $product
     *
     * @return array
     */
    public function getAssociationsStatus(ProductInterface $product)
    {
        $associationStatus = [];
        $sku               = (string) $product->getIdentifier();

        $associationStatus['up_sell'] = $this->client->call(
            self::SOAP_ACTION_LINK_LIST,
            [
                'up_sell',
                $sku,
                'sku'
            ]
        );

        $associationStatus['cross_sell'] = $this->client->call(
            self::SOAP_ACTION_LINK_LIST,
            [
                'cross_sell',
                $sku,
                'sku'
            ]
        );

        $associationStatus['related'] = $this->client->call(
            self::SOAP_ACTION_LINK_LIST,
            [
                'related',
                $sku,
                'sku'
            ]
        );

        $associationStatus['grouped'] = $this->client->call(
            self::SOAP_ACTION_LINK_LIST,
            [
                'grouped',
                $sku,
                'sku'
            ]
        );

        return $associationStatus;
    }

    /**
     * Delete a product association
     * @param array $productAssociation
     */
    public function removeProductAssociation(array $productAssociation)
    {
        $this->client->call(
            self::SOAP_ACTION_LINK_REMOVE,
            $productAssociation
        );
    }

    /**
     * Create a product association
     * @param array $productAssociation
     */
    public function createProductAssociation(array $productAssociation)
    {
        $this->client->call(
            self::SOAP_ACTION_LINK_CREATE,
            $productAssociation
        );
    }

    /**
     * Disable a product
     * @param string $productSku
     */
    public function disableProduct($productSku)
    {
        $this->client->call(
            self::SOAP_ACTION_CATALOG_PRODUCT_UPDATE,
            [
                $productSku,
                [
                    'status' => self::MAGENTO_STATUS_DISABLE
                ]
            ]
        );
    }

    /**
     * Delete a product
     * @param string $productSku
     */
    public function deleteProduct($productSku)
    {
        $this->client->call(
            self::SOAP_ACTION_CATALOG_PRODUCT_DELETE,
            [
                $productSku
            ]
        );
    }

    /**
     * Create an option
     * @param array $option
     */
    public function createOption($option)
    {
        $this->client->call(
            self::SOAP_ACTION_ATTRIBUTE_OPTION_ADD,
            $option
        );
    }

    /**
     * Create an attribute
     * @param array $attribute
     *
     * @return integer ID of the created attribute
     */
    public function createAttribute($attribute)
    {
        $result = $this->client->call(
            self::SOAP_ACTION_ATTRIBUTE_CREATE,
            [$attribute]
        );

        return $result;
    }

    /**
     * Update an attribute
     * @param array $attribute
     *
     * @return boolean
     */
    public function updateAttribute($attribute)
    {
        $result = $this->client->call(
            self::SOAP_ACTION_ATTRIBUTE_UPDATE,
            $attribute
        );

        return $result;
    }

    /**
     * Delete an attribute
     * @param string $attributeCode
     */
    public function deleteAttribute($attributeCode)
    {
        $this->client->call(
            self::SOAP_ACTION_ATTRIBUTE_REMOVE,
            $attributeCode
        );
    }

    /**
     * Get options for the given attribute
     *
     * @param string $attributeCode Attribute code
     *
     * @return array the formated options for the given attribute
     */
    public function getAttributeOptions($attributeCode)
    {
        $options = $this->client->call(
            self::SOAP_ACTION_ATTRIBUTE_OPTION_LIST,
            [$attributeCode, self::ADMIN_STOREVIEW]
        );

        $formatedOptions = [];

        foreach ($options as $option) {
            $formatedOptions[$option['label']] = $option['value'];
        }

        return $formatedOptions;
    }

    /**
     * Delete an option
     * @param string $optionId
     * @param string $attributeCode
     */
    public function deleteOption($optionId, $attributeCode)
    {
        $this->client->call(
            self::SOAP_ACTION_ATTRIBUTE_OPTION_REMOVE,
            [
                $attributeCode,
                $optionId,
            ]
        );
    }

    /**
     * Get the magento attributeSet list from the magento platform
     *
     * @return array Array of attribute sets
     */
    public function getAttributeSetList()
    {
        // On first call we get the magento attribute set list
        // (to bind them with our product's families)
        if (!$this->magentoAttributeSets) {
            $attributeSets = $this->client->call(
                self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_LIST
            );

            $this->magentoAttributeSets = [];

            foreach ($attributeSets as $attributeSet) {
                $this->magentoAttributeSets[$attributeSet['name']] = $attributeSet['set_id'];
            }
        }

        return $this->magentoAttributeSets;
    }

    /**
     *  Add an attribute to the attribute set.
     *
     * @param integer $attributeId      Attribute ID
     * @param integer $setId            Attribute set ID
     * @param integer $attributeGroupId Group ID (optional)
     * @param boolean $sortOrder        Sort order (optional)
     *
     * @return boolean True if the attribute is added to an attribute set
     */
    public function addAttributeToAttributeSet(
        $attributeId,
        $setId,
        $attributeGroupId = null,
        $sortOrder = false
    ) {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_ATTRIBUTE_ADD,
            [
                $attributeId,
                $setId,
                $attributeGroupId,
                $sortOrder
            ]
        );
    }

    /**
     *  Allows you to remove an existing attribute from an attribute set.
     *
     * @param integer $attributeId Attribute ID
     * @param integer $setId       Attribute set ID
     *
     * @return boolean True if the attribute is removed from an attribute set
     */
    public function removeAttributeFromAttributeSet(
        $attributeId,
        $setId
    ) {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_ATTRIBUTE_REMOVE,
            [
                $attributeId,
                $setId,
            ]
        );
    }

    /**
     *  Allows you to create a new attribute set based on another attribute set.
     *
     * @param integer $attributeSetName Attribute set name
     * @param integer $skeletonSetId    Attribute set ID basing on which the new attribute set will be created
     *
     * @return integer ID of the created attribute set
     */
    public function createAttributeSet(
        $attributeSetName,
        $skeletonSetId = 4
    ) {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_CREATE,
            [
                $attributeSetName,
                $skeletonSetId,
            ]
        );
    }

    /**
     *  Allows you to add a new group for attributes to the attribute set.
     *
     * @param integer $attributeSetId Attribute set Id
     * @param string  $groupName      Group name
     *
     * @return integer ID of the created group
     */
    public function addAttributeGroupToAttributeSet(
        $attributeSetId,
        $groupName
    ) {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_GROUP_ADD,
            [
                $attributeSetId,
                $groupName,
            ]
        );
    }

    /**
     *  Allows you to remove a group from an attribute set.
     *
     * @param integer $attributeGroupId Group ID
     *
     * @return boolean true (1) if the group is removed
     */
    public function removeAttributeGroupFromAttributeSet(
        $attributeGroupId
    ) {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_GROUP_REMOVE,
            [
                $attributeGroupId,
            ]
        );
    }

    /**
     *  Allows you to rename a group in the attribute set.
     *
     * @param integer $attributeGroupId Group ID
     * @param string  $groupName        New name for the group
     *
     * @return boolean True (1) if the group is renamed
     */
    public function renameAttributeGroupInAttributeSet(
        $attributeGroupId,
        $groupName
    ) {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_GROUP_REMOVE,
            [
                $attributeGroupId,
                $groupName
            ]
        );
    }

    /**
     *  Allows you to remove an existing attribute set.
     *
     * @param integer $attributeSetId      Attribute set ID
     * @param string  $forceProductsRemove Force product remove flag (optional)
     *
     * @return boolean True (1) if the attribute set is removed
     */
    public function removeAttributeSet(
        $attributeSetId,
        $forceProductsRemove = null
    ) {
        return $this->client->call(
            self::SOAP_ACTION_PRODUCT_ATTRIBUTE_SET_REMOVE,
            [
                $attributeSetId,
                $forceProductsRemove
            ]
        );
    }

    /**
     * Send entities to ApiImport
     *
     * @var array  $entities
     * @var string $entityType
     */
    public function sendEntitiesThroughApiImport(
        $entities,
        $entityType
    ) {
        return $this->client->call(
            self::SOAP_ACTION_IMPORT_ENTITIES_API_IMPORT,
            [
                $entities,
                $entityType
            ]
        );
    }

    /**
     * Get the products status for the given skus
     * @param array $skus
     *
     * @return array
     */
    protected function getStatusForSkus($skus)
    {
        if ($skus) {
            $filters = json_decode(
                json_encode(
                    [
                        'complex_filter' => [
                            [
                                'key' => 'sku',
                                'value' => ['key' => 'in', 'value' => $skus]
                            ]
                        ]
                    ]
                ),
                false
            );
        } else {
            $filters = [];
        }

        return $this->client->call(
            self::SOAP_ACTION_CATALOG_PRODUCT_LIST,
            $filters
        );
    }

    /**
     * Serialize products id in csv
     * @param array $products The given products
     *
     * @return string The serialization result
     */
    protected function getProductsIds(array $products = [])
    {
        $ids = [];

        foreach ($products as $product) {
            $ids[] = $product->getIdentifier();
        }

        return implode(',', $ids);
    }

    /**
     * Serialize configurables id in csv
     * @param array $configurables The given configurables
     *
     * @return string The serialization result
     */
    protected function getConfigurablesIds(array $configurables = [])
    {
        $ids = [];

        foreach ($configurables as $configurable) {
            $ids[] = sprintf(
                Webservice::CONFIGURABLE_IDENTIFIER_PATTERN,
                $configurable['group']->getCode()
            );
        }

        return implode(',', $ids);
    }
}
