<?php
/**
 * User: pel
 * Date: 06/11/2018
 */

namespace Converter\components\storages;


use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Converter\components\Logger;
use Converter\components\ProtectUrl;

class S3Storage extends FileStorage
{
    protected $region;
    public $bucket;
    protected $key;
    protected $secret;
    public $url;
    /** @var S3Client */
    protected $client;
    
    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->client = new S3Client([
            'version' => 'latest',
            'region'  => $this->region,
            'credentials' => [
                'key' => $this->key,
                'secret' => $this->secret
            ]
        ]);
    }
    
    public function upload($sourcePath, $savedPath)
    {
        $savedPath = preg_replace("@%[\dA-F]{2}@", '', $savedPath);
        try {
            $response = $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $savedPath,
                'SourceFile' => $sourcePath,
            ]);
            (new ProtectUrl())->updateInfo("$this->url/$savedPath");
        } catch (S3Exception $e) {
            Logger::send('converter.storage.error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
        return "$this->url/$savedPath";
    }
    
    public function delete($hash)
    {
        $hash = str_replace($this->url, '', $hash);
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $hash,
            ]);
        } catch (S3Exception $e) {
            Logger::send('converter.storage.error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
        return true;
    }
    
    public function generatePath($fileName)
    {
        $fileName = basename($fileName);
        $hash = md5($fileName);
        return 'files/' . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) . '/' . $hash . '/' . $fileName;
    }
}