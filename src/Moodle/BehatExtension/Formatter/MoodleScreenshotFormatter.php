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
 * Screenshot saving formatter.
 *
 * Use it with --out path where screenshots will be saved.
 *
 * @copyright  2016 Rajesh Taneja
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moodle\BehatExtension\Formatter;

use \Behat\Behat\Formatter\FormatterInterface;

use Behat\Behat\Event\EventInterface,
    Behat\Behat\Event\ScenarioEvent,
    Behat\Behat\Event\StepEvent;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag,
    Symfony\Component\EventDispatcher\EventDispatcher,
    Symfony\Component\EventDispatcher\Event,
    Symfony\Component\Translation\Translator,
    Symfony\Component\Console\Output\StreamOutput,
    Symfony\Component\Console\Formatter\OutputFormatterStyle;

use Behat\Behat\Exception\FormatterException;

class MoodleScreenshotFormatter implements FormatterInterface {
    /**
     * @var int The scenario count.
     */
    protected static $currentscenariocount = 0;

    /**
     * @var int The step count within the current scenario.
     */
    protected static $currentscenariostepcount = 0;

    /**
     * If we are saving any kind of dump on failure we should use the same parent dir during a run.
     *
     * @var The parent dir name
     */
    protected static $faildumpdirname = false;

    /**
     * Formatter parameters.
     *
     * @var ParameterBag
     */
    protected $parameters;

    /**
     * Initialize formatter.
     *
     * @uses getDefaultParameters()
     */
    public function __construct()
    {
        $defaultLanguage = null;
        if (($locale = getenv('LANG')) && preg_match('/^([a-z]{2})/', $locale, $matches)) {
            $defaultLanguage = $matches[1];
        }

        $this->parameters = new ParameterBag(array(
            'language'        => $defaultLanguage,
            'output_path'     => null,
            'dir_permissions' => 02777
        ));
    }

    /**
     * Set formatter translator.
     *
     * @param Translator $translator
     */
    final public function setTranslator(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Checks if current formatter has parameter.
     *
     * @param string $name
     *
     * @return bool
     */
    final public function hasParameter($name)
    {
        return $this->parameters->has($name);
    }

    /**
     * Sets formatter parameter.
     *
     * @param string $name
     * @param mixed  $value
     */
    final public function setParameter($name, $value)
    {
        $this->parameters->set($name, $value);
    }

    /**
     * Returns parameter value.
     *
     * @param string $name
     *
     * @return mixed
     */
    final public function getParameter($name)
    {
        return $this->parameters->get($name);
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset$this->parameters->has($name);
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        $events = array('beforeScenario', 'beforeStep', 'afterStep');
        return array_combine($events, $events);
    }

    /**
     * Reset currentscenariostepcount
     *
     * @param ScenarioEvent $event
     */
    public function beforeScenario(ScenarioEvent $event)
    {
        self::$currentscenariostepcount = 0;
        self::$currentscenariocount++;
    }

    /**
     * Increment currentscenariostepcount
     *
     * @param StepEvent $event
     */
    public function beforeStep(StepEvent $event)
    {
        self::$currentscenariostepcount++;
    }

    /**
     * Take screenshot after step is executed.    Behat\Behat\Event\html
     *
     * @param StepEvent $event
     */
    public function afterStep(StepEvent $event)
    {
        // Mink Context is needed for screenshots.
        $moodlecontext = $event->getContext()->getSubcontext('moodle');
        $behathookcontext = $moodlecontext->getSubcontext('behat_hooks');

        // Take screenshot.
        $this->take_screenshot($event, $behathookcontext);

        // Save html content.
        $this->take_contentdump($event, $behathookcontext);
    }

    /**
     * Return screenshot directory where all screenshots will be saved.
     *
     * @return string
     */
    protected function get_run_screenshot_dir()
    {
        global $CFG;

        if (self::$faildumpdirname) {
            return self::$faildumpdirname;
        }

        // If output_path is set then use output_path else use faildump_path.
        if ($this->parameters->get('output_path')) {
            $screenshotpath = $this->parameters->get('output_path');
        } else if ($CFG->behat_faildump_path) {
            $screenshotpath = $CFG->behat_faildump_path;
        } else {
            // It should never reach here.
            throw new FormatterException('You should specify --out switch for moodle_screenshot format');
        }

        $dirpermissions = $this->parameters->get('dir_permissions');

        // All the screenshot dumps should be in the same parent dir.
        self::$faildumpdirname = $screenshotpath . DIRECTORY_SEPARATOR . date('Ymd_His');

        if (!is_dir(self::$faildumpdirname) && !mkdir(self::$faildumpdirname, $dirpermissions, true)) {
            // It shouldn't, we already checked that the directory is writable.
            throw new FormatterException(sprintf(
                'No directories can be created inside %s, check the directory permissions.', $screenshotpath));
        }

        return self::$faildumpdirname;
    }

    /**
     * Take screenshot when a step fails.
     *
     * @throws Exception
     * @param StepEvent $event
     */
    protected function take_screenshot(StepEvent $event, $context)
    {
        // Goutte can't save screenshots.
        if (get_class($context->getMink()->getSession()->getDriver()) === 'Behat\Mink\Driver\GoutteDriver') {
            return false;
        }
        list ($dir, $filename) = $this->get_faildump_filename($event, 'png');
        $context->saveScreenshot($filename, $dir);
    }

    /**
     * Take a dump of the page content when a step fails.
     *
     * @throws Exception
     * @param StepEvent $event
     */
    protected function take_contentdump(StepEvent $event, $context)
    {
        list ($dir, $filename) = $this->get_faildump_filename($event, 'html');
        $fh = fopen($dir . DIRECTORY_SEPARATOR . $filename, 'w');
        fwrite($fh, $context->getMink()->getSession()->getPage()->getContent());
        fclose($fh);
    }

    /**
     * Determine the full pathname to store a failure-related dump.
     *
     * This is used for content such as the DOM, and screenshots.
     *if ($this->parameters->has('dir_permissions')) {00 4201
            $dirpermissions = $this->parameters->get('dir_permissions');
        } else {
            $dirpermissions = 0777;
        }
     * @param StepEvent $event
     * @param String $filetype The file suffix to use. Limited to 4 chars.
     */
    protected function get_faildump_filename(StepEvent $event, $filetype)
    {
        // Make a directory for the scenario.
        $scenarioname = $event->getLogicalParent()->getTitle();
        $scenarioname = preg_replace('/([^a-zA-Z0-9\_]+)/', '-', $scenarioname);
        if ($this->parameters->has('dir_permissions')) {
            $dirpermissions = $this->parameters->get('dir_permissions');
        } else {
            $dirpermissions = 0777;
        }

        $dir = $this->get_run_screenshot_dir();

        // We want a i-am-the-scenario-title format.
        $dir = $dir . DIRECTORY_SEPARATOR . self::$currentscenariocount . '-' . $scenarioname;
        if (!is_dir($dir) && !mkdir($dir, $dirpermissions, true)) {
            // We already checked that the directory is writable. This should not fail.
            throw new FormatterException(sprintf(
                'No directories can be created inside %s, check the directory permissions.', $dir));
        }

        // The failed step text.
        // We want a stepno-i-am-the-failed-step.$filetype format.
        $filename = $event->getStep()->getText();
        $filename = preg_replace('/([^a-zA-Z0-9\_]+)/', '-', $filename);
        $filename = self::$currentscenariostepcount . '-' . $filename;

        // File name limited to 255 characters. Leaving 4 chars for the file
        // extension as we allow .png for images and .html for DOM contents.
        $filename = substr($filename, 0, 250) . '.' . $filetype;
        return array($dir, $filename);
    }
}
