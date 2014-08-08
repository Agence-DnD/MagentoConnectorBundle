<?php

namespace Pim\Bundle\MagentoConnectorBundle\Webservice;

use Pim\Bundle\CatalogBundle\Model\CategoryInterface;
use Pim\Bundle\ConnectorMappingBundle\Mapper\MappingCollection;

/**
 * Resolver for categories path
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CategoryPathResolver
{
    /**
     * Gives the path with label from the magento category root (not inclusive) to the given
     * category taking into account user category mapping.
     * Return 'parentName' . delimiter . 'parentName' . delimiter . ... . 'categoryName'
     *
     * @param CategoryInterface      $category
     * @param MappingCollection      $userCategoryMapping
     * @param array                  $magentoCategoryMapping
     * @param array                  $magentoCategoryTree
     * @param string                 $defaultLocale
     * @param string                 $delimiter               Optional
     *
     * @return string                $categoryPath
     */
    public function getPath(
        CategoryInterface $category,
        MappingCollection $userCategoryMapping,
        $magentoCategoryMapping,
        $magentoCategoryTree,
        $defaultLocale,
        $delimiter = '/'
    ) {
        $categoryPath = '';

        if (!$category->isRoot()) {
            $categoryLabel = $this->getCategoryLabel($category, $defaultLocale);
            $categoryId = $category->getId();

            if (is_numeric($userCategoryMapping->getTarget($categoryLabel, false))) {
                $magentoCategoryId = $userCategoryMapping->getTarget($categoryLabel);
            } else {
                $magentoCategoryId = $magentoCategoryMapping[$categoryId];
            }

            $categoryPath = $this->getCategoryPath($magentoCategoryTree, $magentoCategoryId, $delimiter);
        }

        return $categoryPath;
    }

    /**
     * Get category label
     *
     * @param CategoryInterface $category
     * @param string            $localeCode
     *
     * @return string
     */
    protected function getCategoryLabel(CategoryInterface $category, $localeCode)
    {
        $category->setLocale($localeCode);

        return $category->getLabel();
    }

    /**
     * Gets the magento specific path of a category
     * Return 'parentName' . delimiter . 'parentName' . delimiter . ... . 'categoryName'
     *
     * @param string $magentoCategoryTree The string array element to search for
     * @param array  $magentoCategoryId   The stack to search within for the child
     * @param string $delimiter           Optional
     *
     * @return mixed                      A string containing path to the category if found, false otherwise
     */
    protected function getCategoryPath($magentoCategoryTree, $magentoCategoryId, $delimiter = '/')
    {
        if (!empty($magentoCategoryTree['children'])) {
            $children = $magentoCategoryTree['children'];
        } else {
            $children = $magentoCategoryTree;
        }

        foreach ($children as $category) {
            if (!empty($category['children'])) {
                $lastResult = $this->getCategoryPath($category['children'], $magentoCategoryId);

                if (is_string($lastResult)) {
                    return $category['name'] . $delimiter . $lastResult;
                } else if ($category['category_id'] == $magentoCategoryId) {
                    return $category['name'];
                }
            } else {
                if ($category['category_id'] == $magentoCategoryId) {
                    return $category['name'];
                }
            }
        }

        return false;
    }
} 