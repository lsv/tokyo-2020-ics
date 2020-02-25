<?php

use Tokyo2020Ics\Generate;

require 'vendor/autoload.php';

$ics = Generate::ics();
file_put_contents(__DIR__ . '/tokyo2020.ics', $ics);

#Generate::parseEventPage('en/games/schedule/olympic/20200730_SRF.html');
#Generate::parseEventPage('en/games/schedule/olympic/20200729_HBL.html');
