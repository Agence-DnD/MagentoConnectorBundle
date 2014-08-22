<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Processor;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Pim\Bundle\CatalogBundle\Entity\Category;
use Pim\Bundle\CatalogBundle\Manager\ChannelManager;
use Pim\Bundle\MagentoConnectorBundle\Manager\LocaleManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\ConnectorMappingBundle\Mapper\MappingCollection;
use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Guesser\NormalizerGuesser;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\AbstractNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Normalizer\CategoryNormalizer;
use Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;

class CategoryProcessorSpec extends ObjectBehavior
{
    function let(
        LocaleManager $localeManager,
        MagentoMappingMerger $storeViewMappingMerger,
        MagentoMappingMerger $categoryMappingMerger,
        WebserviceGuesser $webserviceGuesser,
        NormalizerGuesser $normalizerGuesser,
        Webservice $webservice,
        CategoryNormalizer $categoryNormalizer,
        StepExecution $stepExecution,
        MagentoSoapClientParametersRegistry $clientParametersRegistry,
        MagentoSoapClientParameters $clientParameters
    ) {
        $this->beConstructedWith(
            $webserviceGuesser,
            $normalizerGuesser,
            $localeManager,
            $storeViewMappingMerger,
            $categoryMappingMerger,
            $clientParametersRegistry
        );
        $this->setStepExecution($stepExecution);

        $clientParametersRegistry->getInstance(null, null, null, '/api/soap/?wsdl', 'default', null, null)->willReturn($clientParameters);
        $webserviceGuesser->getWebservice($clientParameters)->willReturn($webservice);

        $normalizerGuesser->getCategoryNormalizer($clientParameters)->willReturn($categoryNormalizer);
    }

    function it_normalizes_categories(
        Category $category,
        Category $parentCategory,
        $webservice,
        $categoryNormalizer
    ) {
        $webservice->getCategoriesStatus()->willReturn([
            1 => [
                'category_id' => 1
            ]
        ]);

        $webservice->getStoreViewsList()->willReturn([
            [
                'store_id' => 10,
                'code'     => 'fr_fr'
            ]
        ]);

        $category->getParent()->willReturn($parentCategory);

        $categoryNormalizer->normalize(
            $category,
            AbstractNormalizer::MAGENTO_FORMAT,
            Argument::any()
        )->willReturn([
            'create'    => [],
            'update'    => [],
            'move'      => [],
            'variation' => []
        ]);

        $this->process($category)->shouldReturn([
            'create'    => [],
            'update'    => [],
            'move'      => [],
            'variation' => []
        ]);
    }

    function it_gives_category_mapping_in_json($categoryMappingMerger, MappingCollection $mappingCollection)
    {
        $categoryMappingMerger->getMapping()->willReturn($mappingCollection);
        $mappingCollection->toArray()->willReturn(['foo']);

        $this->getCategoryMapping()->shouldReturn('["foo"]');
    }

    function it_gives_a_proper_configuration_for_fields($categoryMappingMerger, $storeViewMappingMerger)
    {
        $categoryMappingMerger->getConfigurationField()->willReturn(['foo' => 'bar']);
        $storeViewMappingMerger->getConfigurationField()->willReturn(['fooo' => 'baar']);
        $this->getConfigurationFields()->shouldReturn([
            'soapUsername' => [
                'options' => [
                    'required' => true,
                    'help'     => 'pim_magento_connector.export.soapUsername.help',
                    'label'    => 'pim_magento_connector.export.soapUsername.label'
                ]
            ],
            'soapApiKey'   => [
                //Should be remplaced by a password formType but who doesn't
                //empty the field at each edit
                'type'    => 'text',
                'options' => [
                    'required' => true,
                    'help'     => 'pim_magento_connector.export.soapApiKey.help',
                    'label'    => 'pim_magento_connector.export.soapApiKey.label'
                ]
            ],
            'magentoUrl' => [
                'options' => [
                    'required' => true,
                    'help'     => 'pim_magento_connector.export.magentoUrl.help',
                    'label'    => 'pim_magento_connector.export.magentoUrl.label'
                ]
            ],
            'wsdlUrl' => [
                'options' => [
                    'required' => true,
                    'help'     => 'pim_magento_connector.export.wsdlUrl.help',
                    'label'    => 'pim_magento_connector.export.wsdlUrl.label',
                    'data'     => MagentoSoapClientParameters::SOAP_WSDL_URL
                ]
            ],
            'httpLogin' => [
                'options' => [
                    'required' => false,
                    'help'     => 'pim_magento_connector.export.httpLogin.help',
                    'label'    => 'pim_magento_connector.export.httpLogin.label'
                ]
            ],
            'httpPassword' => [
                'options' => [
                    'required' => false,
                    'help'     => 'pim_magento_connector.export.httpPassword.help',
                    'label'    => 'pim_magento_connector.export.httpPassword.label'
                ]
            ],
            'defaultStoreView' => [
                'options' => [
                    'required' => false,
                    'help'     => 'pim_magento_connector.export.defaultStoreView.help',
                    'label'    => 'pim_magento_connector.export.defaultStoreView.label',
                    'data'     => $this->getDefaultStoreView(),
                ]
            ],
            'defaultLocale' => [
                'type' => 'choice',
                'options' => [
                    'choices' => null,
                    'required' => true,
                    'attr' => ['class' => 'select2'],
                    'help'     => 'pim_magento_connector.export.defaultLocale.help',
                    'label'    => 'pim_magento_connector.export.defaultLocale.label'
                ]
            ],
            'website' => [
                'type' => 'text',
                'options' => [
                    'required' => true,
                    'help'     => 'pim_magento_connector.export.website.help',
                    'label'    => 'pim_magento_connector.export.website.label'
                ]
            ],
            'fooo' => 'baar',
            'foo' => 'bar',
        ]);
    }

    function it_sets_storeview_mapping($storeViewMappingMerger, MappingCollection $mappingCollection)
    {
        $storeViewMappingMerger->setParameters(Argument::cetera())->shouldBeCalled();
        $storeViewMappingMerger->setMapping(json_decode('{"fr_FR":{"source":"fr_FR","target":"fr_fr"}}', true))->willReturn(['fr_FR' => ['source' => 'fr_FR', 'target' => 'fr_fr']]);
        $storeViewMappingMerger->getMapping()->shouldBeCalled()->willReturn($mappingCollection);
        $mappingCollection->toArray()->shouldBeCalled()->willReturn(['fr_FR' => ['source' => 'fr_FR', 'target' => 'fr_fr', 'deletable' => 'true']]);

        $this->setStoreviewMapping('{"fr_FR":{"source":"fr_FR","target":"fr_fr"}}')->shouldReturn($this);
    }

    function it_is_configurable()
    {
        $this->setDefaultLocale('en_US');
        $this->setWebsite('http://mywebsite.com');

        $this->getDefaultLocale()->shouldReturn('en_US');
        $this->getWebsite()->shouldReturn('http://mywebsite.com');
    }
}
