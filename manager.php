<?php

/* Require plug-in files */
require_once __DIR__ . '/includes/distributor.php';
require_once __DIR__ . '/includes/clone-fix.php';
require_once __DIR__ . '/helpers.php';

/* Call the setup functions */
\DT\NbAddon\DTInBackground\Distributor\setup();
\DT\NbAddon\DTInBackground\CloneFix\setup();
