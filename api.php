<?php

$loader = require 'vendor/autoload.php';
$loader->add('', 'app/classes');

\Photobum\Config::bootstrap('api');
$f3 = \Base::instance();

$f3->map('/api/upload', '\Photobum\API\Upload');

$f3->map('/api/test', '\Photobum\API\Test');

$f3->route('GET /api/utilities/generateslug', '\Photobum\API\Utilities->generateSlug');
$f3->route('POST /api/utilities/collapse-menu', '\Photobum\API\Utilities->collapseMenu');
//$f3->route('POST /api/utilities/generate-thumb', '\Photobum\API\Utilities->generateThumb');

$f3->route('POST /api/utilities/fix-dir', '\Photobum\API\Utilities->fixDir');

// Aws API
$f3->route('POST /api/aws/s3/delete-object', '\Photobum\API\AwsAPI->s3_deleteObject');

// Installr API
//$f3->route('POST /api/installer/composer-get-status', '\Photobum\API\Installer->composerCheckStatus');

$f3->run();