<?php
namespace Photobum\Utilities\Aws\S3;

use Aws\S3\S3Client;
use Photobum\Config;
use Photobum\Utilities\Aws\Aws;

class Copy extends Aws
{

    public function __construct() {
        parent::__construct();
        $this->s3 = $this->getS3();
    }

    public function copyObject($src, $key)
    {
        //return $src.' + '.$key;
        $bucket = $this->bucket;

        $this->s3->copyObject([
            'ACL' => 'public-read',
            'Bucket' => $bucket,
            'Key' => $key,
            'CopySource' => "{$bucket}/{$src}"
        ]);

    }

}