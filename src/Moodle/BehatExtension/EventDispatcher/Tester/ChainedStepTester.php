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
 * Override step tester to ensure chained steps gets executed.
 *
 * @package    behat
 * @copyright  2104 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moodle\BehatExtension\EventDispatcher\Tester;

use Behat\Behat\Tester\Result\ExecutedStepResult;
use Behat\Behat\Tester\Result\SkippedStepResult;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Behat\Tester\StepTester;
use Moodle\BehatExtension\Context\Step\SubStep;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Call\CallResult;
use Behat\Testwork\Environment\Environment;
use Behat\Behat\EventDispatcher\Event\AfterStepSetup;
use Behat\Behat\EventDispatcher\Event\AfterStepTested;
use Behat\Behat\EventDispatcher\Event\BeforeStepTeardown;
use Behat\Behat\EventDispatcher\Event\BeforeStepTested;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Override step tester to ensure chained steps gets executed.
 *
 * @package    behat
 * @copyright  2104 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ChainedStepTester implements StepTester {
    /**
     * @var StepTester Base step tester.
     */
    private $singlesteptester;

    /**
     * @var EventDispatcher keep step event dispatcher.
     */
    private $eventDispatcher;

    /**
     * Constructor.
     *
     * @param StepTester $steptester single step tester.
     */
    public function __construct(StepTester $steptester) {
        $this->singlesteptester = $steptester;
    }

    /**
     * Set event dispatcher to use for events.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispacher(EventDispatcherInterface $eventDispatcher) {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function setUp(Environment $env, FeatureNode $feature, StepNode $step, $skip) {
        return $this->singlesteptester->setUp($env, $feature, $step, $skip);
    }

    /**
     * {@inheritdoc}
     */
    public function test(Environment $env, FeatureNode $feature, StepNode $step, $skip) {
        $result = $this->singlesteptester->test($env, $feature, $step, $skip);

        if (!$result instanceof ExecutedStepResult || !$this->supportsResult($result->getCallResult())) {
            return $this->checkSkipResult($result);
        }

        return $this->runChainedSteps($env, $feature, $result, $skip);
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown(Environment $env, FeatureNode $feature, StepNode $step, $skip, StepResult $result) {
        return $this->singlesteptester->tearDown($env, $feature, $step, $skip, $result);
    }

    /**
     * Check if results supported.
     *
     * @param CallResult $result
     * @return bool
     */
    private function supportsResult(CallResult $result) {
        $return = $result->getReturn();
        if ($return instanceof SubStep) {
            return true;
        }
        if (!is_array($return) || empty($return)) {
            return false;
        }
        foreach ($return as $value) {
            if (!$value instanceof SubStep) {
                return false;
            }
        }
        return true;
    }

    /**
     * Run chained steps.
     *
     * @param Environment $env
     * @param FeatureNode $feature
     * @param ExecutedStepResult $result
     * @param $skip
     *
     * @return ExecutedStepResult|StepResult
     */
    private function runChainedSteps(Environment $env, FeatureNode $feature, ExecutedStepResult $result, $skip) {
        $callResult = $result->getCallResult();
        $steps = $callResult->getReturn();
        if (!is_array($steps)) {
            // Test it, no need to dispatch events for single chain.
            $stepResult = $this->test($env, $feature, $steps, $skip);
            return $this->checkSkipResult($stepResult);
        }

        // Test all steps.
        foreach ($steps as $step) {
            // Setup new step.
            $event = new BeforeStepTested($env, $feature, $step);
            $this->eventDispatcher->dispatch($event::BEFORE, $event);

            $setup = $this->setUp($env, $feature, $step, $skip);

            $event = new AfterStepSetup($env, $feature, $step, $setup);
            $this->eventDispatcher->dispatch($event::AFTER_SETUP, $event);

            // Test it.
            $stepResult = $this->test($env, $feature, $step, $skip);

            // Tear down.
            $event = new BeforeStepTeardown($env, $feature, $step, $result);
            $this->eventDispatcher->dispatch($event::BEFORE_TEARDOWN, $event);

            $teardown = $this->tearDown($env, $feature, $step, $skip, $result);

            $event = new AfterStepTested($env, $feature, $step, $result, $teardown);
            $this->eventDispatcher->dispatch($event::AFTER, $event);

            //
            if (!$stepResult->isPassed()) {
                return $this->checkSkipResult($stepResult);
            }
        }
        return $this->checkSkipResult($stepResult);
    }

    /**
     * Handle skip exception.
     *
     * @param StepResult $result
     *
     * @return ExecutedStepResult|SkippedStepResult
     */
    private function checkSkipResult(StepResult $result) {
        if ((method_exists($result, 'getException')) && ($result->getException() instanceof SkippedException)) {
            return new SkippedStepResult($result->getSearchResult());
        } else {
            return $result;
        }
    }
}