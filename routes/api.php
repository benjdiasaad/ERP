<?php

foreach (glob(__DIR__ . '/*/index.php') as $domain) {
    require $domain;
}
