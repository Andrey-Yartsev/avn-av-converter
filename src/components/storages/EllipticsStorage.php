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

    public function __construct($url, $bucket)
    {
        $this->httpClient = new Client();
        $this->url = $url;
        $this->bucket = $bucket;
    }

    public function hasError()
    {
        return (bool) $this->error;
    }

    public function getError()
    {
        return $this->error;
    }

    public function upload($sourcePath, $savedPath)
    {
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
}