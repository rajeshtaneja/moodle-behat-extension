<?php

/**
 * This file is part of the Behat
 *
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

spl_autoload_register(function($class) {
    if (false !== strpos($class, 'Moodle\\BehatExtension')) {
        require_once(__DIR__.'/src/'.str_replace('\\', '/', $class).'.php');
        return true;
    }
    if (false !== strpos($class, 'Behat\\Mink\\Driver\\Selenium2Driver')) {
        require_once(__DIR__.'/src/Moodle/BehatExtension/Driver/MoodleSelenium2Driver.php');
        return true;
    }
    if (false !== strpos($class, 'Behat\\Behat\\Context\\Step\\Given')) {
        require_once(__DIR__.'/src/Moodle/BehatExtension/Context/Step/Given.php');
        class_alias('Moodle\\BehatExtension\\Context\\Step\\Given', 'Behat\\Behat\\Context\\Step\\Given');
        return true;
    }
    if (false !== strpos($class, 'Behat\\Behat\\Context\\Step\\When')) {
        require_once(__DIR__.'/src/Moodle/BehatExtension/Context/Step/When.php');
        return true;
    }
    if (false !== strpos($class, 'Behat\\Behat\\Context\\Step\\Then')) {
        require_once(__DIR__.'/src/Moodle/BehatExtension/Context/Step/Then.php');
        return true;
    }
}, true, false);

return new Moodle\BehatExtension\ServiceContainer\MoodleExtension;
