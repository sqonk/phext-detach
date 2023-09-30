<?php
$neon = __DIR__."/phpstan.neon";
passthru("vendor/bin/phpstan analyse -c $neon");