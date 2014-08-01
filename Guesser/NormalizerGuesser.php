<?php

namespace Pim\Bundle\MagentoConnectorBundle\Guesser;

use Pim\Bundle\MagentoConnectorBundle\Normalizer\AbstractNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductNormalizer16;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ConfigurableNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientFactory;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\CatalogBundle\Manager\MediaManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\PriceMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductNormalizerInterface;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductValueNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Manager\CategoryMappingManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\AssociationTypeManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\ProductValueManager;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\CategoryNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\FamilyNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\OptionNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\AttributeNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\ProductMagentoCsvNormalizer;

/**
 * A magento guesser to get the proper normalizer
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class NormalizerGuesser extends AbstractGuesser
{
    /**
     * @var MagentoSoapClientFactory
     */
    protected $magentoSoapClientFactory;

    /**
     * @var ChannelManager
     */
    protected $channelManager;

    /**
     * @var MediaManager
     */
    protected $mediaManager;

    /**
     * @var ProductValueNormalizer
     */
    protected $productValueNormalizer;

    /**
     * @var AssociationTypeManager
     */
    protected $associationTypeManager;

    /**
     * @var ProductValueManager
     */
    protected $productValueManager;

    /**
     * Constructor
     * @param MagentoSoapClientFactory $magentoSoapClientFactory
     * @param ChannelManager           $channelManager
     * @param MediaManager             $mediaManager
     * @param ProductValueNormalizer   $productValueNormalizer
     * @param CategoryMappingManager   $categoryMappingManager
     * @param AssociationTypeManager   $associationTypeManager
     * @param ProductValueManager      $productValueManager
     */
    public function __construct(
        MagentoSoapClientFactory $magentoSoapClientFactory,
        ChannelManager $channelManager,
        MediaManager $mediaManager,
        ProductValueNormalizer $productValueNormalizer,
        CategoryMappingManager $categoryMappingManager,
        AssociationTypeManager $associationTypeManager,
        ProductValueManager $productValueManager
    ) {
        $this->magentoSoapClientFactory = $magentoSoapClientFactory;
        $this->channelManager           = $channelManager;
        $this->mediaManager             = $mediaManager;
        $this->productValueNormalizer   = $productValueNormalizer;
        $this->categoryMappingManager   = $categoryMappingManager;
        $this->associationTypeManager   = $associationTypeManager;
        $this->productValueManager      = $productValueManager;
    }

    /**
     * Get the product normalizer corresponding to the given Magento parameters
     * @param MagentoSoapClientParameters $clientParameters
     * @param boolean                     $enabled
     * @param boolean                     $visibility
     * @param string                      $currencyCode
     *
     * @throws NotSupportedVersionException If the magento version is not supported
     * @return AbstractNormalizer
     */
    public function getProductNormalizer(
        MagentoSoapClientParameters $clientParameters,
        $enabled,
        $visibility,
        $currencyCode
    ) {
        $client         = $this->magentoSoapClientFactory->getMagentoSoapClient($clientParameters);
        $magentoVersion = $this->getMagentoVersion($client);

        switch ($magentoVersion) {
            case AbstractGuesser::MAGENTO_VERSION_1_14:
            case AbstractGuesser::MAGENTO_VERSION_1_13:
            case AbstractGuesser::MAGENTO_VERSION_1_9:
            case AbstractGuesser::MAGENTO_VERSION_1_8:
            case AbstractGuesser::MAGENTO_VERSION_1_7:
                return new ProductNormalizer(
                    $this->channelManager,
                    $this->mediaManager,
                    $this->productValueNormalizer,
                    $this->categoryMappingManager,
                    $this->associationTypeManager,
                    $enabled,
                    $visibility,
                    $currencyCode,
                    $clientParameters->getSoapUrl()
                );
            case AbstractGuesser::MAGENTO_VERSION_1_6:
                return new ProductNormalizer16(
                    $this->channelManager,
                    $this->mediaManager,
                    $this->productValueNormalizer,
                    $this->categoryMappingManager,
                    $this->associationTypeManager,
                    $enabled,
                    $visibility,
                    $currencyCode,
                    $clientParameters->getSoapUrl()
                );
            default:
                throw new NotSupportedVersionException(AbstractGuesser::MAGENTO_VERSION_NOT_SUPPORTED_MESSAGE);
        }
    }

    /**
     * Get the configurable normalizer corresponding to the given Magento parameters
     * @param MagentoSoapClientParameters $clientParameters
     * @param ProductNormalizerInterface  $productNormalizer
     * @param PriceMappingManager         $priceMappingManager
     *
     * @return AbstractNormalizer
     */
    public function getConfigurableNormalizer(
        MagentoSoapClientParameters $clientParameters,
        ProductNormalizerInterface $productNormalizer,
        PriceMappingManager $priceMappingManager
    ) {
        $client         = $this->magentoSoapClientFactory->getMagentoSoapClient($clientParameters);
        $magentoVersion = $this->getMagentoVersion($client);

        switch ($magentoVersion) {
            case AbstractGuesser::MAGENTO_VERSION_1_13:
            case AbstractGuesser::MAGENTO_VERSION_1_8:
            case AbstractGuesser::MAGENTO_VERSION_1_7:
            case AbstractGuesser::MAGENTO_VERSION_1_6:
                return new ConfigurableNormalizer(
                    $this->channelManager,
                    $productNormalizer,
                    $priceMappingManager
                );
            default:
                throw new NotSupportedVersionException(AbstractGuesser::MAGENTO_VERSION_NOT_SUPPORTED_MESSAGE);
        }
    }

    /**
     * Get the Category normalizer corresponding to the given Magento parameters
     * @param MagentoSoapClientParameters $clientParameters
     *
     * @return AbstractNormalizer
     */
    public function getCategoryNormalizer(MagentoSoapClientParameters $clientParameters)
    {
        $client         = $this->magentoSoapClientFactory->getMagentoSoapClient($clientParameters);
        $magentoVersion = $this->getMagentoVersion($client);

        switch ($magentoVersion) {
            case AbstractGuesser::MAGENTO_VERSION_1_13:
            case AbstractGuesser::MAGENTO_VERSION_1_8:
            case AbstractGuesser::MAGENTO_VERSION_1_7:
            case AbstractGuesser::MAGENTO_VERSION_1_6:
                return new CategoryNormalizer(
                    $this->channelManager,
                    $this->categoryMappingManager
                );
            default:
                throw new NotSupportedVersionException(AbstractGuesser::MAGENTO_VERSION_NOT_SUPPORTED_MESSAGE);
        }
    }

    /**
     * Get the option normalizer corresponding to the given Magento parameters
     * @param MagentoSoapClientParameters $clientParameters
     *
     * @return AbstractNormalizer
     */
    public function getOptionNormalizer(MagentoSoapClientParameters $clientParameters)
    {
        $client         = $this->magentoSoapClientFactory->getMagentoSoapClient($clientParameters);
        $magentoVersion = $this->getMagentoVersion($client);

        switch ($magentoVersion) {
            case AbstractGuesser::MAGENTO_VERSION_1_13:
            case AbstractGuesser::MAGENTO_VERSION_1_8:
            case AbstractGuesser::MAGENTO_VERSION_1_7:
            case AbstractGuesser::MAGENTO_VERSION_1_6:
                return new OptionNormalizer($this->channelManager);
            default:
                throw new NotSupportedVersionException(AbstractGuesser::MAGENTO_VERSION_NOT_SUPPORTED_MESSAGE);
        }
    }

    /**
     * Get the attribute normalizer corresponding to the given Magento parameters
     * @param MagentoSoapClientParameters $clientParameters
     *
     * @return AbstractNormalizer
     */
    public function getAttributeNormalizer(MagentoSoapClientParameters $clientParameters)
    {
        $client         = $this->magentoSoapClientFactory->getMagentoSoapClient($clientParameters);
        $magentoVersion = $this->getMagentoVersion($client);

        switch ($magentoVersion) {
            case AbstractGuesser::MAGENTO_VERSION_1_13:
            case AbstractGuesser::MAGENTO_VERSION_1_8:
            case AbstractGuesser::MAGENTO_VERSION_1_7:
            case AbstractGuesser::MAGENTO_VERSION_1_6:
                return new AttributeNormalizer($this->productValueNormalizer, $this->productValueManager);
            default:
                throw new NotSupportedVersionException(AbstractGuesser::MAGENTO_VERSION_NOT_SUPPORTED_MESSAGE);
        }
    }

    /**
     * Get the family normalizer corresponding to the given Magento parameters
     * @param MagentoSoapClientParameters $clientParameters
     *
     * @throws NotSupportedVersionException
     * @return FamilyNormalizer
     */
    public function getFamilyNormalizer(MagentoSoapClientParameters $clientParameters)
    {
        $client         = $this->magentoSoapClientFactory->getMagentoSoapClient($clientParameters);
        $magentoVersion = $this->getMagentoVersion($client);

        switch ($magentoVersion) {
            case AbstractGuesser::MAGENTO_VERSION_1_13:
            case AbstractGuesser::MAGENTO_VERSION_1_8:
            case AbstractGuesser::MAGENTO_VERSION_1_7:
            case AbstractGuesser::MAGENTO_VERSION_1_6:
                return new FamilyNormalizer();
            default:
                throw new NotSupportedVersionException(AbstractGuesser::MAGENTO_VERSION_NOT_SUPPORTED_MESSAGE);
        }
    }

    /**
     * Get the normalize for Magento Csv format
     *
     * @param MagentoSoapClientParameters $clientParameters
     *
     * @throws ApiImportNotSupportedException
     *
     */
    public function getProductMagentoCsvNormalizer(MagentoSoapClientParameters $clientParameters, $currencyCode)
    {
        $client = $this->magentoSoapClientFactory->getMagentoSoapClient($clientParameters);
        $apiImportStatus = $this->getApiImportStatus($client);

        if ($apiImportStatus['isEnable']) {
            return new ProductMagentoCsvNormalizer(
                $this->channelManager,
                $this->mediaManager,
                $this->productValueNormalizer,
                $currencyCode
            );
        } else {
            throw new ApiImportNotSupportedException($apiImportStatus['message']);
        }
    }
}
