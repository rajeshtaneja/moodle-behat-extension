moodle-behat-extension
======================

Behat extension for Moodle to get features and steps definitions from different moodle components; it basically allows multiple features folders and helps with the contexts spreads across components of an external app.

Contributing
============

http://docs.moodle.org/dev/Acceptance_testing/Contributing_to_Moodle_behat_extension

Upgrade from moodle-behat-extension 1.31.x to 3.31.0
====================================================
* Chained steps are not natively supported by behat 3.
  * You should either replace Behat\Behat\Context\Step\Given with Behat\Behat\Context\Step\Given;
  * or use behat_context_helper::get('BEHAT_CONTEXT_CLASS'); and call api to execute the step.
* named selectors are deprecated, use named_exact or named_partial instead.
