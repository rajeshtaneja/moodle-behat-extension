<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Moodle\BehatExtension\Tester\Cli;

use Behat\Testwork\Cli\Controller;
use Behat\Testwork\Suite\Exception\SuiteNotFoundException;
use Behat\Testwork\Suite\SuiteRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * This class will add an option --run-with-theme and update suite default value.
 * This can be considered as alias for suite.
 *
 */
final class RunWithThemeController implements Controller {

    /**
     * @var SuiteRegistry
     */
    private $registry;
    /**
     * @var array
     */
    private $suiteConfigurations = array();

    /**
     * @var string
     */
    const ALL_THEMES_TO_RUN = 'ALL';

    /**
     * Initializes controller.
     *
     * @param SuiteRegistry $registry
     * @param array         $suiteConfigurations
     */
    public function __construct(SuiteRegistry $registry, array $suiteConfigurations) {
        $this->registry = $registry;
        $this->suiteConfigurations = $suiteConfigurations;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(Command $command) {
        // Hack to alias --run-with-theme option to set suite value.
        $command->addOption('--run-with-theme', null, InputOption::VALUE_OPTIONAL,
            'Run theme features only.'
        );

        $input = new ArgvInput();

        // If suite is passed, then don't need to update any option.
        if ($input->hasParameterOption('--suite') || $input->hasParameterOption('-s')) {
            return;
        }

        if ($input->hasParameterOption('--run-with-theme')) {
            $suiteToSet = $input->getParameterOption('--run-with-theme');
            if (!empty($suiteToSet) && (strtoupper($suiteToSet) !== self::ALL_THEMES_TO_RUN)) {
                $commandDefinitions = $command->getDefinition();
                if ($commandDefinitions->hasOption('suite')) {
                    $suitedefinition = $commandDefinitions->getOption('suite');
                    $suitedefinition->setDefault($suiteToSet);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output) {
        $exerciseSuiteName = $input->getOption('run-with-theme');

        if (null !== $exerciseSuiteName && !isset($this->suiteConfigurations[$exerciseSuiteName])
            && (strtoupper($exerciseSuiteName) !== self::ALL_THEMES_TO_RUN)) {
            throw new SuiteNotFoundException(sprintf(
                '`%s` theme is not found or has not been properly configured.',
                $exerciseSuiteName
            ), $exerciseSuiteName);
        }
    }
}
