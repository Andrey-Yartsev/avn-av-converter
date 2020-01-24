<?php
/**
 * User: pel
 * Date: 2019-08-20
 */

namespace Converter\components;


use Aws\CloudFront\UrlSigner;
use GuzzleHttp\Client;

class ProtectUrl
{
    const TYPE_CACHEFLY = 'cachefly';
    const TYPE_CLOUDFRONT = 'cloudfront';

    /** @var array */
    protected $config = [];

    /** @var UrlSigner */
    protected $cloudFrontUrlSigner;

    public function __construct()
    {
        $this->config = Config::getInstance()->get('protect', []);
    }

    /**
     * @param string $url
     * @return string
     */
    public function getProtectedUrl($url)
    {
        $type = $this->config['type'] ?? self::TYPE_CACHEFLY;
        if ($type == self::TYPE_CACHEFLY) {
            return $this->getProtectServeUrl($url);
        }
        if ($type == self::TYPE_CLOUDFRONT) {
            return $this->getCloudFrontSignedUrl($url, [
                'expires' => strtotime($this->config['expires']),
            ]);
        }
        return $url;
    }

    /**
     * @param string $url
     * @param array $rules
     * @return string
     */
    public function getProtectServeUrl($url, $rules = [])
    {
        $rules = array_merge($this->config['rules'] ?? [], $rules);
        if ($rules['badurl'] ?? '') {
            $rules['badurl'] = base64_encode($rules['badurl']);
        }
        if ($rules['expiretime'] ?? 0) {
            if (is_string($rules['expiretime'])) {
                $rules['expiretime'] = strtotime($rules['expiretime']);
            } elseif ($rules['expiretime'] < ($time = time())) {
                $rules['expiretime'] = $time + $rules['expiretime'];
            }
        }
        $rules = array_filter($rules);
        foreach ($rules as $key => &$value) {
            $value = "{$key}={$value}";
        }
        unset($value);
        $rules = implode(';', $rules);
    
        $path = trim(parse_url($url, PHP_URL_PATH), '/');
        $secret = $this->config['secret'] ?? '';
        $hash = hash_hmac('sha256', $rules . $path, $secret, false);
        $protectServeUrl = trim($this->config['url'] ?? 'Protected', '/');
        $protectedUrl = $this->config['baseUrl'] . '/' . $protectServeUrl . '/' . $rules . '/' . $hash . '/' . $path;
        return $protectedUrl;
    }

    /**
     * @param string $url
     */
    public function updateInfo($url)
    {
        if ($this->config !== []) {
            try {
                $client = new Client();
                $client->head($this->getProtectServeUrl($url));
            } catch (\Throwable $e) {
            
            }
        }
    }

    /**
     * @return UrlSigner
     */
    protected function getCloudFrontUrlSigner()
    {
        if (!$this->cloudFrontUrlSigner) {
            $this->cloudFrontUrlSigner = new UrlSigner(
                $this->config['key_pair_id'],
                $this->config['private_key']
            );
        }
        return $this->cloudFrontUrlSigner;
    }

    /**
     * @param string $url
     * @param array $options
     * @return string
     */
    protected function getCloudFrontSignedUrl($url, $options = [])
    {
        return $this->getCloudFrontUrlSigner()
            ->getSignedUrl(
                $url,
                $options['expires'] ?? null,
                $options['policy'] ?? null
            );
    }
}