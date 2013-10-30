<?php

/**
 * This script scans the network to identify all hosts.
 */

require_once 'bootstrap.php';

require_lib('netmon');

netmon_collect();

