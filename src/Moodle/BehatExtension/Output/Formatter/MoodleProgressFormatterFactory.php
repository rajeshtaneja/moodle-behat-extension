<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Moodle behat context class resolver.
 *
 * @package    behat
 * @copyright  2104 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moodle\BehatExtension\Output\Formatter;

use Behat\Testwork\Exception\ServiceContainer\ExceptionExtension;
use Behat\Testwork\Output\ServiceContainer\OutputExtension;
use Behat\Testwork\ServiceContainer\ServiceProcessor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Behat\Behat\Output\ServiceContainer\Formatter\ProgressFormatterFactory;
use Behat\Behat\EventDispatcher\Event\OutlineTested;

class MoodleProgressFormatterFactory extends ProgressFormatterFactory {
    /**
     * @var ServiceProcessor
     */
    private $processor;

    /**
     * Initializes extension.
     *
     * @param null|ServiceProcessor $processor
     */
    public function __construct(ServiceProcessor $processor = null) {
        $this->processor = $processor ? : new ServiceProcessor();
    }

    /**
     * Loads formatter itself.
     *
     * @param ContainerBuilder $container
     */
    protected function loadFormatter(ContainerBuilder $container) {

        $definition = new Definition('Behat\Behat\Output\Statistics\TotalStatistics');
        $container->setDefinition('output.progress.statistics', $definition);

        $moodleconfig = $container->getParameter('behat.moodle.parameters');

        $definition = new Definition('Moodle\BehatExtension\Output\Printer\MoodleProgressPrinter',
            array($moodleconfig['moodledirroot']));
        $container->setDefinition('moodle.output.node.printer.progress.moodleprinter', $definition);

        $definition = new Definition('Behat\Testwork\Output\NodeEventListeningFormatter', array(
            'moodle_progress',
            'Prints information about then run followed by one character per step.',
            array(
                'timer' => true
            ),
            $this->createOutputPrinterDefinition(),
            new Definition('Behat\Testwork\Output\Node\EventListener\ChainEventListener', array(
                    array(
                        new Reference(self::ROOT_LISTENER_ID),
                        new Definition('Behat\Behat\Output\Node\EventListener\Statistics\StatisticsListener', array(
                            new Reference('output.progress.statistics'),
                            new Reference('output.node.printer.progress.statistics')
                        )),
                        new Definition('Behat\Behat\Output\Node\EventListener\Statistics\ScenarioStatsListener', array(
                            new Reference('output.progress.statistics')
                        )),
                        new Definition('Behat\Behat\Output\Node\EventListener\Statistics\StepStatsListener', array(
                            new Reference('output.progress.statistics'),
                            new Reference(ExceptionExtension::PRESENTER_ID)
                        )),
                        new Definition('Behat\Behat\Output\Node\EventListener\Statistics\HookStatsListener', array(
                            new Reference('output.progress.statistics'),
                            new Reference(ExceptionExtension::PRESENTER_ID)
                        )),
                        new Definition('Behat\Behat\Output\Node\EventListener\AST\SuiteListener', array(
                            new Reference('moodle.output.node.printer.progress.moodleprinter')
                        ))
                    )
                )
            )
        ));
        $definition->addTag(OutputExtension::FORMATTER_TAG, array('priority' => 100));
        $container->setDefinition(OutputExtension::FORMATTER_TAG . '.moodleprogress', $definition);
    }
}
