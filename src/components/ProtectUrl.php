<?php
/**
 * User: pel
 * Date: 2019-08-20
 */

namespace Converter\components;


class ProtectUrl
{
    protected $config = [];
    
    public function __construct()
    {
        $this->config = Config::getInstance()->get('protect', []);
    }
    
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
}