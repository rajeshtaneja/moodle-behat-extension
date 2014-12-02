<?php

namespace Moodle\BehatExtension\ServiceContainer;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Behat\Testwork\ServiceContainer\ServiceProcessor;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Moodle\BehatExtension\Output\ServiceContainer\Formatter\MoodleProgressFormatterFactory;
use Behat\Behat\Tester\ServiceContainer\TesterExtension;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Behat\Behat\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use Moodle\BehatExtension\Driver\MoodleSelenium2Factory;
use Behat\Testwork\Suite\ServiceContainer\SuiteExtension;
use Behat\Behat\Definition\ServiceContainer\DefinitionExtension;
use Behat\Testwork\Cli\ServiceContainer\CliExtension;
use Behat\Behat\Definition\Printer\ConsoleDefinitionListPrinter;
use Behat\Behat\Gherkin\ServiceContainer\GherkinExtension;

/**
 * Behat extension for moodle
 *
 * Provides multiple features directory loading (Gherkin\Loader\MoodleFeaturesSuiteLoader
 */
class BehatExtension implements ExtensionInterface {
    /**
     * Extension configuration ID.
     */
    const MOODLE_ID = 'moodle';

    /**
     * @var ServiceProcessor
     */
    private $processor;

    /**
     * Initializes compiler pass.
     *
     * @param null|ServiceProcessor $processor
     */
    public function __construct(ServiceProcessor $processor = null) {
        $this->processor = $processor ? : new ServiceProcessor();
    }

    /**
     * Loads moodle specific configuration.
     *
     * @param array            $config    Extension configuration hash (from behat.yml)
     * @param ContainerBuilder $container ContainerBuilder instance
     */
    public function load(ContainerBuilder $container, array $config) {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/services'));
        $loader->load('core.xml');

        // Getting the extension parameters.
        $container->setParameter('behat.moodle.parameters', $config);

        // Load moodle progress formatter.
        $moodleprogressformatter = new MoodleProgressFormatterFactory();
        $moodleprogressformatter->buildFormatter($container);

        // Load custom step tester event dispatcher.
        $this->loadEventDispatchingStepTester($container);

        // Load chained step tester.
        $this->loadChainedStepTester($container);
    }

    /**
     * Loads definition printers.
     *
     * @param ContainerBuilder $container
     */
    private function loadDefinitionPrinters(ContainerBuilder $container) {
        $definition = new Definition('Moodle\BehatExtension\Definition\Printer\ConsoleDefinitionInformationPrinter', array(
            new Reference(CliExtension::OUTPUT_ID),
            new Reference(DefinitionExtension::PATTERN_TRANSFORMER_ID),
            new Reference(DefinitionExtension::DEFINITION_TRANSLATOR_ID),
            new Reference(GherkinExtension::KEYWORDS_ID)
        ));
        $container->removeDefinition('definition.information_printer');
        $container->setDefinition('definition.information_printer', $definition);

    }

    /**
     * Loads definition controller.
     *
     * @param ContainerBuilder $container
     */
    private function loadController(ContainerBuilder $container) {
        $definition = new Definition('Moodle\BehatExtension\Definition\Cli\AvailableDefinitionsController', array(
                new Reference(SuiteExtension::REGISTRY_ID),
                new Reference(DefinitionExtension::WRITER_ID),
                new Reference('definition.list_printer'),
            new Reference('definition.information_printer'))
        );
        $container->removeDefinition(CliExtension::CONTROLLER_TAG . '.available_definitions');
        $container->setDefinition(CliExtension::CONTROLLER_TAG . '.available_definitions', $definition);
    }

    /**
     * Loads chained step tester.
     *
     * @param ContainerBuilder $container
     */
    protected function loadChainedStepTester(ContainerBuilder $container) {
        // Chained steps.
        $definition = new Definition('Moodle\BehatExtension\EventDispatcher\Tester\ChainedStepTester', array(
            new Reference(TesterExtension::STEP_TESTER_ID),
        ));
        $definition->addTag(TesterExtension::STEP_TESTER_WRAPPER_TAG, array('priority' => 100));
        $container->setDefinition(TesterExtension::STEP_TESTER_WRAPPER_TAG . '.substep', $definition);
    }

    /**
     * Loads event-dispatching step tester.
     *
     * @param ContainerBuilder $container
     */
    protected function loadEventDispatchingStepTester(ContainerBuilder $container) {
        $definition = new Definition('Moodle\BehatExtension\EventDispatcher\Tester\MoodleEventDispatchingStepTester', array(
            new Reference(TesterExtension::STEP_TESTER_ID),
            new Reference(EventDispatcherExtension::DISPATCHER_ID)
        ));
        $definition->addTag(TesterExtension::STEP_TESTER_WRAPPER_TAG, array('priority' => -9999));
        $container->setDefinition(TesterExtension::STEP_TESTER_WRAPPER_TAG . '.event_dispatching', $definition);
    }

    /**
     * Setups configuration for current extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder) {
        $builder->
                children()->
                    arrayNode('capabilities')->
                    useAttributeAsKey('key')->
                    prototype('variable')->end()->
                end()->
                arrayNode('steps_definitions')->
                    useAttributeAsKey('key')->
                    prototype('variable')->end()->
                end()->
                scalarNode('moodledirroot')->
                    defaultNull()->
                    end()->
            end()->
        end();
    }

    /**
     * {@inheritDoc}
     */
    public function getConfigKey() {
        return self::MOODLE_ID;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(ExtensionManager $extensionManager) {
        if (null !== $minkExtension = $extensionManager->getExtension('mink')) {
            $minkExtension->registerDriverFactory(new MoodleSelenium2Factory());
        }
    }

    public function process(ContainerBuilder $container) {
        // Load controller for definition printing.
        $this->loadDefinitionPrinters($container);
        $this->loadController($container);
    }
}
