<?php

foreach (glob(__DIR__ . '/*/index.php') as $module) {
    require $module;
}
