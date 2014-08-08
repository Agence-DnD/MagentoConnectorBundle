<?php

namespace Pim\Bundle\MagentoConnectorBundle\Writer;

use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Akeneo\Bundle\BatchBundle\Item\InvalidItemException;

/**
 * Product to Api Import writer
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2014 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductToApiImportWriter extends AbstractWriter
{
    /** @var array */
    protected $items = [];

    /**
     * @param WebserviceGuesser                   $webserviceGuesser
     * @param MagentoSoapClientParametersRegistry $clientParametersRegistry
     */
    public function __construct(
        WebserviceGuesser $webserviceGuesser,
        MagentoSoapClientParametersRegistry $clientParametersRegistry
    ) {
        parent::__construct($webserviceGuesser, $clientParametersRegistry);
    }

    /**
     * {@inheritDoc}
     */
    public function write(array $items)
    {
        $this->beforeExecute();

        foreach ($items as $item) {
            $this->items = array_merge($this->items, $item);
        }
    }

    /**
     * Send all product to Magento
     */
    public function flush()
    {
//        $bulks = array_chunk($this->items, 2500);
        // arrayIterator ?

        $this->stepExecution->incrementSummaryInfo('Write');
        try {
            $this->webservice->sendEntitiesThroughApiImport($this->items, 'catalog_product');
        } catch (\Exception $e) {
            throw new InvalidItemException($e->getMessage(), []);
        }
    }
}
