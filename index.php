<?php

define('CONJUR_ROOT', getcwd());
require_once(CONJUR_ROOT . '/app/Conjur.php');
Conjur::run();