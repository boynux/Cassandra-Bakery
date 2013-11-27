<?php
require_once "phpcassa/lib/autoload.php";
require_once "Bakery.php";

$bakery = new Bakery (null, "10.0.3.208");

$bakery->acquire ();
echo sprintf ( "Entring critical section (%s)... ", getmypid () );
usleep (10000);
echo "end.\n";
$bakery->release ();
?>
