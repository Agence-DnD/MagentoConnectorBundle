<?php

namespace Pim\Bundle\MagentoConnectorBundle\Entity\Repository;

use Pim\Bundle\CatalogBundle\Entity\Repository\CurrencyRepository as BaseCurrencyRepository;

//TODO: REMOVE IT
/**
 * Custom currency repository
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CurrencyRepository extends BaseCurrencyRepository
{
    /**
     * Get all categories for the given criterias
     * @param array $criterias
     *
     * @return array
     */
    public function getCategories(array $criterias)
    {
        return $this->findBy($criterias);
    }
}
