<?php

namespace Pim\Bundle\MagentoConnectorBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Pim\Bundle\CatalogBundle\Model\CategoryInterface;
use Pim\Bundle\ConnectorMappingBundle\Mapper\MappingCollection;

/**
 * Category mapping manager
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CategoryMappingManager
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectManager
     */
    protected $objectManager;

    /**
     * @var string
     */
    protected $className;

    /**
     * Constructor
     * @param ObjectManager $objectManager
     * @param string        $className
     */
    public function __construct(ObjectManager $objectManager, $className)
    {
        $this->objectManager = $objectManager;
        $this->className     = $className;
    }

    /**
     * Get category from id and Magento url
     * @param int    $id
     * @param string $magentoUrl
     *
     * @return CategoryInterface
     */
    public function getCategoryFromId($id, $magentoUrl)
    {
        $magentoCategoryMapping = $this->getEntityRepository()->findOneBy(
            [
                'magentoCategoryId' => $id,
                'magentoUrl'        => $magentoUrl
            ]
        );

        return $magentoCategoryMapping ? $magentoCategoryMapping->getCategory() : null;
    }

    /**
     * Get id from category and Magento url
     * @param CategoryInterface $category
     * @param string            $magentoUrl
     * @param MappingCollection $categoryMapping
     *
     * @return int
     */
    public function getIdFromCategory(
        CategoryInterface $category,
        $magentoUrl,
        MappingCollection $categoryMapping = null
    ) {
        if ($categoryMapping &&
            ($categoryId = $categoryMapping->getTarget($category->getCode())) != $category->getCode()
        ) {
            return $categoryId;
        } else {
            $categoryMapping = $this->getEntityRepository()->findOneBy(
                [
                    'category'   => $category,
                    'magentoUrl' => $magentoUrl
                ]
            );

            return $categoryMapping ? $categoryMapping->getMagentoCategoryId() : null;
        }
    }

    /**
     * Give the mapping between pim categories id and magento categories id by pim category root and magento url
     * Return [ akeneo_category_id => [magento_category_ids, ..], .. ]
     *
     * @param CategoryInterface $root
     * @param string            $magentoUrl
     *
     * @return array
     */
    public function getMappingByRootAndMagentoUrl(CategoryInterface $root, $magentoUrl)
    {
        $unsortedMapping = $this->getEntityRepository()->getMappingByRootAndMagentoUrl($root, $magentoUrl);
        $mapping = [];

        foreach ($unsortedMapping as $categoryMapping) {
            $mapping[$categoryMapping['id']] = $categoryMapping['magentoCategoryId'];
        }

        return $mapping;
    }

    /**
     * Register a new category mapping
     * @param CategoryInterface $pimCategory
     * @param int               $magentoCategoryId
     * @param string            $magentoUrl
     */
    public function registerCategoryMapping(
        CategoryInterface $pimCategory,
        $magentoCategoryId,
        $magentoUrl
    ) {
        $categoryMapping = $this->getEntityRepository()->findOneBy(['category' => $pimCategory]);
        $magentoCategoryMapping = new $this->className();

        if ($categoryMapping) {
            $magentoCategoryMapping = $categoryMapping;
        }

        $magentoCategoryMapping->setCategory($pimCategory);
        $magentoCategoryMapping->setMagentoCategoryId($magentoCategoryId);
        $magentoCategoryMapping->setMagentoUrl($magentoUrl);

        $this->objectManager->persist($magentoCategoryMapping);
        $this->objectManager->flush();
    }

    /**
     * Does the given magento category exist in pim ?
     * @param string $categoryId
     * @param string $magentoUrl
     *
     * @return boolean
     */
    public function magentoCategoryExists($categoryId, $magentoUrl)
    {
        return $this->getCategoryFromId($categoryId, $magentoUrl) !== null;
    }

    /**
     * Get the entity manager
     * @return EntityRepository
     */
    protected function getEntityRepository()
    {
        return $this->objectManager->getRepository($this->className);
    }
}
