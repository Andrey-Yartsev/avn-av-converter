<?php
/**
 * User: pel
 * Date: 25/10/2018
 */

namespace Converter\helpers;


use Aws\S3\S3Client;
use Converter\components\Config;
use Converter\components\Logger;
use Converter\response\AudioResponse;
use Converter\response\ImageResponse;
use Converter\response\VideoResponse;
use FFMpeg\FFProbe;

class FileHelper
{
    const TYPE_VIDEO = 'video';
    const TYPE_IMAGE = 'image';
    const TYPE_AUDIO = 'audio';
    
    protected static $firstVideoStreams = [];
    protected static $firstAudioStreams = [];
    /**
     * @return array
     */
    public static function getAllowedTypes()
    {
        return [
            self::TYPE_AUDIO,
            self::TYPE_VIDEO,
            self::TYPE_IMAGE,
        ];
    }
    
    /**
     * @param $filePath
     * @return bool|string
     */
    public static function getTypeFile($filePath)
    {
        $output = $return = null;
        exec(sprintf('file --mime-type -b %s 2>/dev/null', escapeshellarg($filePath)), $output, $return);
        $mimeType = $return === 0 && $output ? $output[0] : null;
        if (strpos($mimeType, 'application') === 0) {
            $mimeType = mime_content_type($filePath);
        }
        
        return self::getTypeByMimeType($mimeType, $filePath);
    }
    
    /**
     * @param $mimeType
     * @param null $filePath
     * @return bool|string
     */
    public static function getTypeByMimeType($mimeType, $filePath = null)
    {
        $mimeType = preg_replace('#^([^;]+).*$#i', '$1', $mimeType);
        if (preg_match('/video\/*/', $mimeType) || $mimeType == 'image/gif' || strpos($mimeType, 'stream')) {
            if ($filePath) {
                if (self::isVideo($filePath)) {
                    return self::TYPE_VIDEO;
                }
            } else {
                return self::TYPE_VIDEO;
            }
        } elseif (preg_match('/image\/*/', $mimeType)) {
            return self::TYPE_IMAGE;
        } elseif (preg_match('/audio\/*/', $mimeType)) {
            if ($filePath) {
                return self::isVideo($filePath) ? self::TYPE_VIDEO : self::TYPE_AUDIO;
            } else {
                return self::TYPE_AUDIO;
            }
        }
        Logger::send('wrong.ext', [
            'mimeType' => $mimeType,
            'filePath' => $filePath
        ]);
        return false;
    }
    
    /**
     * @param $fileUrl
     * @param $fileType
     * @return ImageResponse|VideoResponse|null
     */
    public static function getFileResponse($fileUrl, $fileType)
    {
        if ($fileType == self::TYPE_VIDEO) {
            $response = new VideoResponse();
        } elseif ($fileType == self::TYPE_IMAGE) {
            $response = new ImageResponse();
        } elseif ($fileType == self::TYPE_AUDIO) {
            $response = new AudioResponse();
        } else {
            return null;
        }
        $response->url = $fileUrl;
        $response->name = 'source';
        return $response;
    }
    
    /**
     * @param string $filePath
     * @return string
     * @throws \Exception
     */
    public static function getLocalPath($filePath)
    {
        $localPath = str_replace(Config::getInstance()->get('baseUrl'), PUBPATH, $filePath);
        if (!file_exists($localPath)) {
            if (strpos($filePath, Config::getInstance()->get('baseUrl')) != false) {
                throw new \Exception('File not found');
            } else {
                $localPath = PUBPATH . '/upload/' . md5($filePath . uniqid()) . '.' . pathinfo($filePath, PATHINFO_EXTENSION);
                Logger::send('debug', ['localPath' => $localPath, 'filePath' => $filePath]);
                $userAgent = static::getUserAgent($filePath);
                $client = new \GuzzleHttp\Client();
                $response = $client->get($filePath, ['headers' => ['User-Agent' => $userAgent]]);
                $bytes = file_put_contents($localPath, $response->getBody());
                Logger::send('debug', ['localPath' => $localPath, 'filePath' => $filePath, 'downloaded' => true, 'bytes' => $bytes]);
            }
        }
        
        return $localPath;
    }

    /**
     * @param string $path
     * @return string|null
     */
    public static function getUserAgent($path)
    {
        $host = @parse_url($path, PHP_URL_HOST);
        if (!$host || strpos($host, 'amazonaws.com') === false) {
            return null;
        }
        // @todo move to local configs before remove
        $defaults = [
            'avnstars' => 'SecretCacheFlyUserAgent',
            'of2transcoder' => 'SecretCacheFlyUserAgent',
            'of2media' => 'j/S%/qyd+_RP^tAgEjC6RZVU96(*b5#',
        ];
        foreach (Config::getInstance()->get('s3_secret_user_agent', $defaults) as $key => $value) {
            if (strpos($host, $key) !== false) {
                return $value;
            }
        }
        return null;
    }

    /**
     * @param string $path
     * @return string|null
     */
    public static function getSignedUrl($path)
    {
        $host = @parse_url($path, PHP_URL_HOST);
        if (!$host || strpos($host, 'amazonaws.com') === false) {
            return $path;
        }
        foreach (Config::getInstance()->get('s3_url_sign', []) as $key => $settings) {
            if (strpos($host, $key) === false) {
                continue;
            }
            try {
                $client = new S3Client([
                    'version' => 'latest',
                    'region'  => $settings['region'],
                    'credentials' => [
                        'key' => $settings['key'],
                        'secret' => $settings['secret'],
                    ],
                ]);
                $command = $client->getCommand(
                    'GetObject',
                    [
                        'Bucket' => $settings['bucket'],
                        'Key' => ltrim(parse_url($path, PHP_URL_PATH), '/'),
                    ]
                );
                $request = $client->createPresignedRequest($command, $settings['expires']);
                return (string) $request->getUri();
            } catch (\Throwable $e) {
                Logger::send('s3_url_sign', ['error' => $e->getMessage()] + compact('path'));
            }
        }
        return $path;
    }
    
    /**
     * @param $filePath
     * @return bool
     */
    public static function isVideo($filePath)
    {
        try {
            return (bool) count(self::getFFProbe()->streams($filePath)->videos());
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * @param $filePath
     * @return float
     */
    public static function getVideoDuration($filePath)
    {
        return floor((float)self::getFirstVideoStream($filePath)->get('duration'));
    }
    
    /**
     * @param $filePath
     * @return float
     */
    public static function getAudioDuration($filePath)
    {
        return ceil((float)self::getFirstAudioStream($filePath)->get('duration'));
    }
    
    /**
     * @param $filePath
     * @return array
     */
    public static function getVideoDimensions($filePath)
    {
        $firstStream = self::getFirstVideoStream($filePath);
        $dimensions = $firstStream->getDimensions();
        return [$dimensions->getWidth(), $dimensions->getHeight()];
    }
    
    /**
     * @param $filePath
     * @return FFProbe\DataMapping\Stream
     */
    protected static function getFirstVideoStream($filePath)
    {
        if (empty(self::$firstVideoStreams[$filePath])) {
            self::$firstVideoStreams[$filePath] = self::getFFProbe()->streams($filePath)
                ->videos()
                ->first();
        }
        return self::$firstVideoStreams[$filePath];
    }
    
    /**
     * @param $filePath
     * @return FFProbe\DataMapping\Stream
     */
    protected static function getFirstAudioStream($filePath)
    {
        if (empty(self::$firstAudioStreams[$filePath])) {
            self::$firstAudioStreams[$filePath] = self::getFFProbe()->streams($filePath)
                ->audios()
                ->first();
        }
        return self::$firstAudioStreams[$filePath];
    }
    
    /**
     * @return FFProbe
     */
    protected static function getFFProbe()
    {
        return FFProbe::create([
            'ffmpeg.binaries'  => exec('which ffmpeg'),
            'ffprobe.binaries' => exec('which ffprobe')
        ]);
    }
    
    /**
     * @param string $path
     * @return string
     */
    public static function getExt($path)
    {
        return strtolower(pathinfo(parse_url($path, PHP_URL_PATH), PATHINFO_EXTENSION));
    }
    
    /**
     * @param $filePath
     * @return array
     */
    public static function getFileID3($filePath)
    {
        $json = shell_exec(sprintf("exiftool -json %s", escapeshellarg(self::getLocalPath($filePath))));
        $array = json_decode($json, true);
        return is_array($array) ? current($array) : [];
    }
}
