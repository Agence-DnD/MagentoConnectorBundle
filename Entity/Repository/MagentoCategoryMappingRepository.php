<?php

namespace Pim\Bundle\MagentoConnectorBundle\Entity\Repository;

use Pim\Bundle\CatalogBundle\Model\CategoryInterface;
use Doctrine\ORM\EntityRepository;

class MagentoCategoryMappingRepository extends EntityRepository
{

    /**
     * Give the mapping between pim categories ids and magento categories ids by pim category root
     * Return [ [akeneo_category_id => id , magento_category_ids => id], .. ]
     *
     * @param CategoryInterface $root
     * @param string            $magentoUrl
     *
     * @return array
     */
    public function getMappingByRootAndMagentoUrl(CategoryInterface $root, $magentoUrl)
    {
        $queryBuilder = $this
            ->createQueryBuilder('mapping')
            ->select('cat_pim.id', 'mapping.magentoCategoryId')
            ->join(
                'mapping.category',
                'cat_pim',
                'WITH',
                'mapping.category = cat_pim.id'
            )
            ->where('mapping.magentoUrl = :magentoUrl')
            ->andWhere('cat_pim.root = :rootId')
            ->setParameters(
                [
                    'magentoUrl' => $magentoUrl,
                    'rootId'     => $root->getId()
                ]
            );

        return $queryBuilder->getQuery()->getArrayResult();
    }
}
