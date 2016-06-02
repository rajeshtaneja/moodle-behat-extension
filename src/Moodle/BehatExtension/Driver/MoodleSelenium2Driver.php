<?php

namespace Moodle\BehatExtension\Driver;

use Behat\Mink\Driver\Selenium2Driver as Selenium2Driver;
use WebDriver\Key as key;
use WebDriver\WebDriver;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Selector\Xpath\Escaper;
use WebDriver\Element;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\UnknownError;
use WebDriver\Exception;

/**
 * Selenium2 driver extension to allow extra selenium capabilities restricted by behat/mink-extension.
 */
class MoodleSelenium2Driver extends Selenium2Driver {

    /**
     * Dirty attribute to get the browser name; $browserName is private
     * @var string
     */
    protected static $browser;

    /**
     * @var Escaper
     */
    private $xpathEscaper;

    /**
     * Instantiates the driver.
     *
     * @param string    $browser Browser name
     * @param array     $desiredCapabilities The desired capabilities
     * @param string    $wdHost The WebDriver host
     * @param array     $moodleParameters Moodle parameters including our non-behat-friendly selenium capabilities
     */
    public function __construct($browserName = 'firefox', $desiredCapabilities = null, $wdHost = 'http://localhost:4444/wd/hub', $moodleParameters = array()) {
        // If they are set add them overridding if it's the case (not likely).
        if (!empty($moodleParameters) && !empty($moodleParameters['capabilities'])) {
            foreach ($moodleParameters['capabilities'] as $key => $capability) {
                $desiredCapabilities[$key] = $capability;
            }
        }

        parent::__construct($browserName, $desiredCapabilities, $wdHost);

        // This class is instantiated by the dependencies injection system so
        // prior to all of beforeSuite subscribers which will call getBrowser*()
        self::$browser = $browserName;

        $this->xpathEscaper = new Escaper();
    }

    /**
     * Forwards to getBrowser() so we keep compatibility with both static and non-static accesses.
     *
     * @deprecated
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments) {
        if ($name == 'getBrowserName') {
            return self::getBrowser();
        }

        // Fallbacks calling the requested static method, we don't
        // even know if it exists or not.
        return call_user_func(array(self, $name), $arguments);
    }

    /**
     * Forwards to getBrowser() so we keep compatibility with both static and non-static accesses.
     *
     * @deprecated
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments) {
        if ($name == 'getBrowserName') {
            return self::getBrowser();
        }

        // Fallbacks calling the requested instance method, we don't
        // even know if it exists or not.
        return call_user_func(array($this, $name), $arguments);
    }

    /**
     * Returns the browser being used.
     *
     * We need to know it:
     * - To show info about the run.
     * - In case there are differences between browsers in the steps.
     *
     * @static
     * @return string
     */
    public static function getBrowser() {
        return self::$browser;
    }

    /**
     * Drag one element onto another.
     *
     * Override the original one to give YUI drag & drop
     * time to consider it a valid drag & drop. It will need
     * more changes in future to properly adapt to how YUI dd
     * component behaves.
     *
     * @param   string  $sourceXpath
     * @param   string  $destinationXpath
     */
    public function dragTo($sourceXpath, $destinationXpath) {
        $source      = $this->getWebDriverSession()->element('xpath', $sourceXpath);
        $destination = $this->getWebDriverSession()->element('xpath', $destinationXpath);

        // TODO: MDL-39727 This method requires improvements according to the YUI drag and drop component.

        $this->getWebDriverSession()->moveto(array(
            'element' => $source->getID()
        ));

        $script = <<<JS
(function (element) {
    var event = document.createEvent("HTMLEvents");

    event.initEvent("dragstart", true, true);
    event.dataTransfer = {};

    element.dispatchEvent(event);
}({{ELEMENT}}));
JS;
        $this->withSyn()->executeJsOnXpath($sourceXpath, $script);

        $this->getWebDriverSession()->buttondown();
        $this->getWebDriverSession()->moveto(array(
            'element' => $destination->getID()
        ));

        // We add a 2 seconds wait to make YUI dd happy.
        $this->wait(2 * 1000, false);

        $this->getWebDriverSession()->buttonup();

        $script = <<<JS
(function (element) {
    var event = document.createEvent("HTMLEvents");

    event.initEvent("drop", true, true);
    event.dataTransfer = {};

    element.dispatchEvent(event);
}({{ELEMENT}}));
JS;
        $this->withSyn()->executeJsOnXpath($destinationXpath, $script);
    }

    /**
     * Overwriten method to use our custom Syn library.
     *
     * Makes sure that the Syn event library has been injected into the current page,
     * and return $this for a fluid interface,
     *
     *     $this->withSyn()->executeJsOnXpath($xpath, $script);
     *
     * @return Selenium2Driver
     */
    protected function withSyn() {
        $hasSyn = $this->getWebDriverSession()->execute(array(
            'script' => 'return typeof window["Syn"]!=="undefined"',
            'args'   => array()
        ));

        if (!$hasSyn) {
            $synJs = file_get_contents(__DIR__.'/Selenium2/moodle_syn-min.js');
            $this->getWebDriverSession()->execute(array(
                'script' => $synJs,
                'args'   => array()
            ));
        }

        return $this;
    }

    /**
     * Public interface to run Syn scripts.
     *
     * @see self::executeJsOnXpath()
     *
     * @param  string   $xpath  the xpath to search with
     * @param  string   $script the script to execute
     * @param  Boolean  $sync   whether to run the script synchronously (default is TRUE)
     *
     * @return mixed
     */
    public function triggerSynScript($xpath, $script, $sync = true) {
        return $this->withSyn()->executeJsOnXpath($xpath, $script, $sync);
    }

    /**
     * Overriding this as key::TAB is causing page scroll and rubrics scenarios are failing.
     * https://github.com/minkphp/MinkSelenium2Driver/issues/194
     * {@inheritdoc}
     */
    public function setValue($xpath, $value) {
        $value = strval($value);
        $element = $this->getWebDriverSession()->element('xpath', $xpath);
        $elementName = strtolower($element->name());

        if ('select' === $elementName) {
            if (is_array($value)) {
                $this->deselectAllOptions($element);

                foreach ($value as $option) {
                    $this->selectOptionOnElement($element, $option, true);
                }

                return;
            }

            $this->selectOption($element, $value);

            return;
        }

        if ('input' === $elementName) {
            $elementType = strtolower($element->attribute('type'));

            if (in_array($elementType, array('submit', 'image', 'button', 'reset'))) {
                throw new DriverException(sprintf('Impossible to set value an element with XPath "%s" as it is not a select, textarea or textbox', $xpath));
            }

            if ('checkbox' === $elementType) {
                if ($element->selected() xor (bool) $value) {
                    $this->clickOnElement($element);
                }

                return;
            }

            if ('radio' === $elementType) {
                $this->selectRadioValue($element, $value);

                return;
            }

            if ('file' === $elementType) {
                $element->postValue(array('value' => array(strval($value))));

                return;
            }
        }

        $value = strval($value);

        if (in_array($elementName, array('input', 'textarea'))) {
            $existingValueLength = strlen($element->attribute('value'));

            // Add the TAB key to ensure we unfocus the field as browsers are triggering the change event only
            // after leaving the field.
            $value = str_repeat(Key::BACKSPACE . Key::DELETE, $existingValueLength) . $value;
        }

        $element->postValue(array('value' => array($value)));
    }

    /**
     * {@inheritdoc}
     */
    private function clickOnElement(Element $element) {
        $this->getWebDriverSession()->moveto(array('element' => $element->getID()));
        $element->click();
    }

    /**
     * {@inheritdoc}
     */
    public function moodle_move_to_and_click_on_element($xpath) {
        $this->moodle_move_to_element($xpath);

        $this->findElement($xpath)->click();
    }

    /**
     * {@inheritdoc}
     */
    public function moodle_click_on_element($xpath) {
        $this->findElement($xpath)->click();
    }

    /**
     * Move using JsonWireProtocol can fail on few OS/Browsers.
     * Use JS to scroll to the element, before clicking.
     *
     * @param ELEMENT $element element to move to.
     */
    public function moodle_move_to_element($xpath) {
        $element = $this->findElement($xpath);
        $script = <<<JS
var node = {{ELEMENT}};
node.scrollIntoView(true);
JS;
        $this->executeJsOnElement($element, $script);
    }

    /**
     * @param string $xpath
     *
     * @return Element
     */
    private function findElement($xpath) {
        return $this->getWebDriverSession()->element('xpath', $xpath);
    }

    /**
     * Executes JS on a given element - pass in a js script string and {{ELEMENT}} will
     * be replaced with a reference to the element
     *
     * @example $this->executeJsOnXpath($xpath, 'return {{ELEMENT}}.childNodes.length');
     *
     * @param Element $element the webdriver element
     * @param string  $script  the script to execute
     * @param Boolean $sync    whether to run the script synchronously (default is TRUE)
     *
     * @return mixed
     */
    private function executeJsOnElement(Element $element, $script, $sync = true) {
        $script  = str_replace('{{ELEMENT}}', 'arguments[0]', $script);

        $options = array(
            'script' => $script,
            'args'   => array(array('ELEMENT' => $element->getID())),
        );

        if ($sync) {
            return $this->getWebDriverSession()->execute($options);
        }

        return $this->getWebDriverSession()->execute_async($options);
    }

    /**
     * Selects a value in a radio button group
     *
     * @param Element $element An element referencing one of the radio buttons of the group
     * @param string  $value   The value to select
     *
     * @throws DriverException when the value cannot be found
     */
    private function selectRadioValue(Element $element, $value) {
        // short-circuit when we already have the right button of the group to avoid XPath queries
        if ($element->attribute('value') === $value) {
            $element->click();

            return;
        }

        $name = $element->attribute('name');

        if (!$name) {
            throw new DriverException(sprintf('The radio button does not have the value "%s"', $value));
        }

        $formId = $element->attribute('form');

        try {
            if (null !== $formId) {
                $xpath = <<<'XPATH'
//form[@id=%1$s]//input[@type="radio" and not(@form) and @name=%2$s and @value = %3$s]
|
//input[@type="radio" and @form=%1$s and @name=%2$s and @value = %3$s]
XPATH;

                $xpath = sprintf(
                    $xpath,
                    $this->xpathEscaper->escapeLiteral($formId),
                    $this->xpathEscaper->escapeLiteral($name),
                    $this->xpathEscaper->escapeLiteral($value)
                );
                $input = $this->getWebDriverSession()->element('xpath', $xpath);
            } else {
                $xpath = sprintf(
                    './ancestor::form//input[@type="radio" and not(@form) and @name=%s and @value = %s]',
                    $this->xpathEscaper->escapeLiteral($name),
                    $this->xpathEscaper->escapeLiteral($value)
                );
                $input = $element->element('xpath', $xpath);
            }
        } catch (NoSuchElement $e) {
            $message = sprintf('The radio group "%s" does not have an option "%s"', $name, $value);

            throw new DriverException($message, 0, $e);
        }

        $input->click();
    }

    /**
     * @param Element $element
     * @param string  $value
     * @param bool    $multiple
     */
    private function selectOptionOnElement(Element $element, $value, $multiple = false)
    {
        $escapedValue = $this->xpathEscaper->escapeLiteral($value);
        // The value of an option is the normalized version of its text when it has no value attribute
        $optionQuery = sprintf('.//option[@value = %s or (not(@value) and normalize-space(.) = %s)]', $escapedValue, $escapedValue);
        $option = $element->element('xpath', $optionQuery);

        if ($multiple || !$element->attribute('multiple')) {
            // Moodle specific fix. Don't use selected check, as it may fail on Mac/FF.
            // This check is done in moodle code.
            $option->click();

            return;
        }

        // Deselect all options before selecting the new one
        $this->deselectAllOptions($element);
        $option->click();
    }

    /**
     * Deselects all options of a multiple select
     *
     * Note: this implementation does not trigger a change event after deselecting the elements.
     *
     * @param Element $element
     */
    private function deselectAllOptions(Element $element) {
        $script = <<<JS
var node = {{ELEMENT}};
var i, l = node.options.length;
for (i = 0; i < l; i++) {
    node.options[i].selected = false;
}
JS;

        $this->executeJsOnElement($element, $script);
    }

    /**
     * Ensures the element is a checkbox
     *
     * @param Element $element
     * @param string  $xpath
     * @param string  $type
     * @param string  $action
     *
     * @throws DriverException
     */
    private function ensureInputType(Element $element, $xpath, $type, $action) {
        if ('input' !== strtolower($element->name()) || $type !== strtolower($element->attribute('type'))) {
            $message = 'Impossible to %s the element with XPath "%s" as it is not a %s input';

            throw new DriverException(sprintf($message, $action, $xpath, $type));
        }
    }
}
