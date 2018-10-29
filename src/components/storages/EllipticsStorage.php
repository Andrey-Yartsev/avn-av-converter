<?php
/**
 * User: pel
 * Date: 08/10/2018
 */

namespace Converter\components\storages;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class EllipticsStorage extends FileStorage
{
    /** @var Client */
    protected $httpClient;
    protected $url;
    protected $bucket;
    protected $error;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->httpClient = new Client();
        if (empty($this->url) || empty($this->bucket)) {
            throw new \InvalidArgumentException();
        }
    }

    public function hasError()
    {
        return (bool) $this->error;
    }

    public function getError()
    {
        return $this->error;
    }
    
    /**
     * @param $sourcePath
     * @param $savedPath
     * @return bool|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function upload($sourcePath, $savedPath)
    {
        if (strpos($sourcePath, '//') === 0) {
            $sourcePath = 'https:' . $sourcePath;
        }
        $this->error = null;
        try {
            $response = $this->httpClient->request('POST', "$this->url/upload/$this->bucket/$savedPath", [
                'body' => file_get_contents($sourcePath, true)
            ]);
        } catch (RequestException $e) {
            $this->error = $e->getMessage();
            return false;
        }

        return "$this->url/get/$this->bucket/$savedPath";
    }

    public function delete($hash)
    {
        return;
    }
    
    /**
     * @param $fileName
     * @return string
     */
    public function generatePath($fileName)
    {
        $fileName = basename($fileName);
        $hash = md5($fileName);
        return 'files/' . substr($hash, 0, 1) . '/' . substr($hash, 0, 2) . '/' . $hash . '/' . $fileName;
    }
}