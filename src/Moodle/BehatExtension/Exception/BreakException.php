<?php

namespace Moodle\BehatExtension\Exception;

use Behat\Behat\Exception;

/**
 * Break exception (throw this to stop execution till user press return key.).
 *
 * @author Rajesh Taneja
 */
class BreakException extends Exception\BehaviorException{}
