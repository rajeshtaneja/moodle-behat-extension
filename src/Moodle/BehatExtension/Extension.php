<?php

namespace Moodle\BehatExtension;

use Behat\Testwork\ServiceContainer\Extension as ExtensionInterface;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Behat\Behat\Context\ServiceContainer\ContextExtension;
use Behat\MinkExtension\ServiceContainer\Driver\DriverFactory;
use Behat\MinkExtension\ServiceContainer\Driver\GoutteFactory;
use Behat\MinkExtension\ServiceContainer\Driver\SahiFactory;
use Behat\MinkExtension\ServiceContainer\Driver\SaucelabsFactory;
use Behat\MinkExtension\ServiceContainer\Driver\Selenium2Factory;
use Behat\MinkExtension\ServiceContainer\Driver\SeleniumFactory;
use Behat\MinkExtension\ServiceContainer\Driver\ZombieFactory;
use Behat\Testwork\EventDispatcher\ServiceContainer\EventDispatcherExtension;
use Behat\Testwork\ServiceContainer\Exception\ProcessingException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Behat extension for moodle
 *
 * Provides multiple features directory loading (Gherkin\Loader\MoodleFeaturesSuiteLoader
 */
class Extension implements ExtensionInterface
{

    /**
     * Loads moodle specific configuration.
     *
     * @param ContainerBuilder $container ContainerBuilder instance
     * @param array            $config    Extension configuration hash (from behat.yml)
     */
    public function load(ContainerBuilder $container, array $config)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/services'));
        $loader->load('core.xml');

        // Getting the extension parameters.
        $container->setParameter('behat.moodle.parameters', $config);

        // Adding moodle formatters to the list of supported formatted.
        if (isset($config['formatters'])) {
            $container->setParameter('behat.formatter.classes', $config['formatters']);
        }
    }

    /**
     * Setups configuration for current extension.
     *
     * @param ArrayNodeDefinition $builder
     */
    public function configure(ArrayNodeDefinition $builder)
    {
        $builder->
            children()->
                arrayNode('capabilities')->
                    useAttributeAsKey('key')->
                    prototype('variable')->end()->
                end()->
                arrayNode('features')->
                    useAttributeAsKey('key')->
                    prototype('variable')->end()->
                end()->
                arrayNode('steps_definitions')->
                    useAttributeAsKey('key')->
                    prototype('variable')->end()->
                end()->
                arrayNode('formatters')->
                    useAttributeAsKey('key')->
                    prototype('variable')->end()->
                end()->

            end()->
        end();
    }

    /**
     * Returns compiler passes used by this extension.
     *
     * @return array
     */
    public function getCompilerPasses()
    {
        return array();
    }

    /**
     * Returns the extension config key.
     *
     * @return string
     */
    public function getConfigKey()
    {
        return 'behat';
    }

    /**
     * Initializes other extensions.de
     *
     * This method is called immediately after all extensions are activated but
     * before any extension `configure()` method is called. This allows extensions
     * to hook into the configuration of other extensions providing such an
     * extension point.
     *
     * @param ExtensionManager $extensionManager
     */
    public function initialize(ExtensionManager $extensionManager)
    {

    }

    /**
     * Processes shared container after all extensions loaded.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {

    }
}
