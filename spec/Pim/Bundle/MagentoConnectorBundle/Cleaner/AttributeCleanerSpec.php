<?php

namespace spec\Pim\Bundle\MagentoConnectorBundle\Cleaner;

use Pim\Bundle\MagentoConnectorBundle\Guesser\WebserviceGuesser;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParameters;
use Pim\Bundle\MagentoConnectorBundle\Webservice\MagentoSoapClientParametersRegistry;
use Pim\Bundle\MagentoConnectorBundle\Webservice\Webservice;
use Doctrine\ORM\EntityManager;
use Pim\Bundle\MagentoConnectorBundle\Merger\MagentoMappingMerger;
use Pim\Bundle\ConnectorMappingBundle\Mapper\MappingCollection;
use Doctrine\ORM\EntityRepository;
use Pim\Bundle\CatalogBundle\Entity\Attribute;
use Akeneo\Bundle\BatchBundle\Entity\StepExecution;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class AttributeCleanerSpec extends ObjectBehavior
{
    function let(
        WebserviceGuesser $webserviceGuesser,
        MagentoMappingMerger $attributeMappingMerger,
        EntityManager $em,
        Webservice $webservice,
        EntityRepository $entityRepository,
        MappingCollection $mappingCollection,
        StepExecution $stepExecution,
        MagentoSoapClientParametersRegistry $clientParametersRegistry,
        MagentoSoapClientParameters $clientParameters
    ) {
        $this->beConstructedWith($webserviceGuesser, $attributeMappingMerger, $em, 'attribute_class', $clientParametersRegistry);
        $this->setStepExecution($stepExecution);

        $clientParametersRegistry->getInstance(null, null, null, '/api/soap/?wsdl', 'default', null, null)->willReturn($clientParameters);
        $webserviceGuesser->getWebservice($clientParameters)->willReturn($webservice);

        $em->getRepository('attribute_class')->willReturn($entityRepository);
        $attributeMappingMerger->getMapping()->willReturn($mappingCollection);
    }

    function it_deletes_attribute_not_in_pim_anymore($webservice, $entityRepository, $mappingCollection)
    {
        $this->setNotInPimAnymoreAction('delete');

        $webservice->getAllAttributes()->willReturn([['code' => 'foo']]);
        $entityRepository->findOneBy(['code' => 'foo'])->willReturn(null);
        $mappingCollection->getSource('foo')->willReturn('foo');

        $webservice->deleteAttribute('foo')->shouldBeCalled();

        $this->execute();
    }

    function it_not_deletes_attribute_not_in_pim_anymore_if_parameters_doesnt_say_to_do_so(
        $webservice,
        $entityRepository,
        $mappingCollection
    ) {
        $this->setNotInPimAnymoreAction('do_nothing');

        $webservice->getAllAttributes()->willReturn([['code' => 'foo']]);
        $entityRepository->findOneBy(['code' => 'foo'])->willReturn(null);
        $mappingCollection->getSource('foo')->willReturn('foo');

        $webservice->deleteAttribute('foo')->shouldNotBeCalled();

        $this->execute();
    }

    function it_deletes_attribute_not_in_family_anymore(
        $webservice,
        $entityRepository,
        $mappingCollection,
        Attribute $attribute
    ) {
        $this->setNotInPimAnymoreAction('delete');

        $webservice->getAllAttributes()->willReturn([['code' => 'foo']]);
        $entityRepository->findOneBy(['code' => 'foo'])->willReturn($attribute);
        $attribute->getFamilies()->willReturn(null);
        $mappingCollection->getSource('foo')->willReturn('foo');

        $webservice->deleteAttribute('foo')->shouldBeCalled();

        $this->execute();
    }

    function it_deletes_attribute_which_got_renamed(
        $webservice,
        $entityRepository,
        $mappingCollection,
        Attribute $attribute
    ) {
        $this->setNotInPimAnymoreAction('delete');

        $webservice->getAllAttributes()->willReturn([['code' => 'foo']]);
        $entityRepository->findOneBy(['code' => null])->willReturn($attribute);
        $attribute->getFamilies()->willReturn(false);
        $mappingCollection->getSource('foo')->willReturn(null);

        $webservice->deleteAttribute('foo')->shouldBeCalled();

        $this->execute();
    }

    function it_raises_an_invalid_item_exception_when_something_goes_wrong_with_the_sopa_api(
        $webservice,
        $entityRepository,
        $mappingCollection,
        Attribute $attribute
    ) {
        $this->setNotInPimAnymoreAction('delete');

        $webservice->getAllAttributes()->willReturn([['code' => 'foo']]);
        $entityRepository->findOneBy(['code' => null])->willReturn($attribute);
        $attribute->getFamilies()->willReturn(false);
        $mappingCollection->getSource('foo')->willReturn(null);

        $webservice->deleteAttribute('foo')->willThrow('Pim\Bundle\MagentoConnectorBundle\Webservice\SoapCallException');

        $this->shouldThrow('Akeneo\Bundle\BatchBundle\Item\InvalidItemException')->during('execute');
    }

    function it_get_attribute_mapping_from_attribute_mapping_merger(
        $attributeMappingMerger,
        MappingCollection $mappingCollection
    ) {
        $attributeMappingMerger->getMapping()->willReturn($mappingCollection);
        $mappingCollection->toArray()->willReturn([]);

        $this->getAttributeCodeMapping()->shouldReturn('[]');
    }

    function it_sets_attribute_mapping_to_the_attribute_mapping_merger($attributeMappingMerger)
    {
        $attributeMappingMerger->setParameters(Argument::cetera())->shouldBeCalled();
        $attributeMappingMerger->setMapping([])->shouldBeCalled();

        $this->setAttributeCodeMapping('[]');
    }

    function it_gives_configuration_fields($attributeMappingMerger)
    {
        $attributeMappingMerger->getConfigurationField()->willReturn(['attributeMapping' => []]);

        $this->getConfigurationFields()->shouldReturn(
            [
                'soapUsername' => [
                    'options' => [
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.soapUsername.help',
                        'label'    => 'pim_magento_connector.export.soapUsername.label'
                    ]
                ],
                'soapApiKey'   => [
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
                'notInPimAnymoreAction' => [
                    'type'    => 'choice',
                    'options' => [
                        'choices'  => [
                            'do_nothing' => 'pim_magento_connector.export.do_nothing.label',
                            'delete'     => 'pim_magento_connector.export.delete.label'
                        ],
                        'required' => true,
                        'help'     => 'pim_magento_connector.export.notInPimAnymoreAction.help',
                        'label'    => 'pim_magento_connector.export.notInPimAnymoreAction.label',
                        'attr' => [
                            'class' => 'select2'
                        ]
                    ]
                ],
                'attributeMapping' => []
            ]
        );
    }
}
