<?php
/**
 * User: pel
 * Date: 24/10/2018
 */

namespace Converter\controllers;


use Converter\components\Config;
use Converter\components\Controller;
use Converter\components\FileType;
use Converter\components\FileUploadHandler;
use Converter\components\Logger;
use Converter\components\Process;
use Converter\components\Redis;
use Converter\exceptions\BadRequestHttpException;
use Converter\forms\UploadForm;
use Converter\helpers\FileHelper;

class ProcessController extends Controller
{
    public function actionStatus($processId)
    {
        return Process::status($processId);
    }
    
    public function actionExists()
    {
        $request = $this->getRequest();
        $postData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        $failedIds = [];
        if (empty($postData['processId']) && empty($postData['processIds'])) {
            throw new BadRequestHttpException('ProcessId is required');
        }
        if (isset($postData['processIds']) && is_array($postData['processIds'])) {
            foreach ($postData['processIds'] as $processId) {
                if (!Process::exists($processId)) {
                    $failedIds[] = $processId;
                }
            }
        } else {
            if (!Process::exists($postData['processId'])) {
                $failedIds[] = $postData['processId'];
            }
        }
        
        return [
            'failedIds' => $failedIds
        ];
    }
    
    public function actionRestart()
    {
        set_time_limit(300);
        ini_set('memory_limit', '1G');
        $request = $this->getRequest();
        $postData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        if (empty($postData['processes'])) {
            throw new BadRequestHttpException('Processes is required');
        }
        $response = [];
        $processIds = [];
        $presets = Config::getInstance()->get('presets');
        foreach ($postData['processes'] as $process) {
            if (empty($process['id']) || empty($process['preset'])) {
                continue;
            }
            if (empty($presets[$process['preset']])) {
                continue;
            }
            Logger::send('process', ['processId' => $process['id'], 'step' => 'Init re-start']);
            $queue = Redis::getInstance()->get('queue:' . $process['id']);
            if ($queue) {
                $processIds[$process['id']] = $process['preset'];
                $response[] = [
                    'processId' => $process['id'],
                    'success'   => true
                ];
            } else {
                Logger::send('process', ['processId' => $process['id'], 'step' => 'Data not found. Skip']);
            }
        }
    
        $content = json_encode($response);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($content));
        echo $content;
        fastcgi_finish_request();
        foreach ($processIds as $processId => $presetName) {
            Process::restart($processId, $presetName);
        }
    }
    
    public function actionStart()
    {
        set_time_limit(300);
        ini_set('memory_limit', '1G');
        $request = $this->getRequest();
        $postData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        if (empty($postData['processes'])) {
            throw new BadRequestHttpException('Processes is required');
        }
        $response = [];
        $processIds = [];
        foreach ($postData['processes'] as $process) {
            if (empty($process['id'])) {
                continue;
            }
            Logger::send('process', ['processId' => $process['id'], 'step' => 'Init start']);
            $processModel = Process::find($process['id']);
            if ($processModel) {
                Logger::send('process', ['processId' => $process['id'], 'step' => 'Init start (find process)']);
                $queue = $processModel->getData();
                $processIds[] = $processModel->getId();
                $driver = $processModel->getDriver();
                if (!$driver) {
                    continue;
                }
                
                $files = [];
                $sourceResponse = FileHelper::getFileResponse($queue['filePath'], $queue['fileType']);
                if (isset($process['thumbsCount'])) {
                    $processModel->set('thumbsCount', $process['thumbsCount']);
                }
                
                if ($driver->needPreviewOnStart) {
                    switch ($queue['fileType']) {
                        case FileHelper::TYPE_VIDEO:
                            Logger::send('process', ['processId' => $process['id'], 'step' => 'Is video']);
                            $duration = FileHelper::getVideoDuration($queue['filePath']);
                            list($width, $height) = FileHelper::getVideoDimensions($queue['filePath']);
            
                            $sourceResponse->duration = $duration;
                            $sourceResponse->width = $width;
                            $sourceResponse->height = $height;
                            
                            $maxCount = false;
                            if (isset($process['thumbsCount'])) {
                                $maxCount = (int) $process['thumbsCount'];
                            } elseif (isset($driver->thumbs['maxCount'])) {
                                $maxCount = (int) $driver->thumbs['maxCount'];
                            }
            
                            if ($maxCount) {
                                if ($duration > $maxCount) {
                                    $step = floor($duration / $maxCount);
                                } else {
                                    $maxCount = $duration;
                                    $step = 1;
                                }
                            } else {
                                $maxCount = $step = 1;
                            }
                            Logger::send('process', ['processId' => $process['id'], 'step' => 'generated video info']);
                            if ($duration == 0) {
                                $driver->createVideoPreview($queue['filePath'], $queue['watermark'], $duration);
                            } else {
                                for ($i = 0; $i < $maxCount; $i++) {
                                    $driver->createVideoPreview($queue['filePath'], $queue['watermark'], $i * $step);
                                }
                            }
            
                            break;
                        case FileHelper::TYPE_AUDIO:
                            Logger::send('process', ['processId' => $process['id'], 'step' => 'Is audio']);
                            $sourceResponse->duration = FileHelper::getAudioDuration($queue['filePath']);
                            break;
                        case FileHelper::TYPE_IMAGE:
                            Logger::send('process', ['processId' => $process['id'], 'step' => 'Is photo', 'filePath' => $queue['filePath']]);
                            $driver->createPhotoPreview($queue['filePath'], $queue['watermark']);
                            Logger::send('process', ['processId' => $process['id'], 'step' => 'End createPhotoPreview()']);
                            break;
                    }
                }
                $files[] = $sourceResponse;
                $files = array_merge($files, $driver->getResult());
                $response[] = [
                    'processId' => $process['id'],
                    'files'     => $files
                ];
                Logger::send('process', ['processId' => $process['id'], 'step' => 'Send preview files', 'data' => $files]);
            }
        }
        $content = json_encode($response);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($content));
        echo $content;
        fastcgi_finish_request();
        foreach ($processIds as $processId) {
            Process::start($processId);
        }
    }
    
    public function actionUpload()
    {
        $request = $this->getRequest();
        $form = new UploadForm();
        Logger::send('debug', $_FILES);
        if (isset($_SERVER['HTTP_CONTENT_RANGE'])) {
            $uploadPath = '';
            if (isset($_POST['additional']) && is_array($_POST['additional'])) {
                $uploadPath = implode('/', array_map(function ($value) {
                    return preg_replace('/[^a-z0-9\.]/i', '', $value);
                }, $_POST['additional'])) . '/';
            }
            $uploadHandler = new FileUploadHandler([
                'access_control_allow_origin' => false,
                'script_url'                  => '/actions/',
                'upload_dir'                  => PUBPATH . '/upload/' . $uploadPath,
                'upload_url'                  => '/upload/' . $uploadPath,
                'max_file_size'               => 4294967296,
                'min_file_size'               => 1,
                'max_number_of_files'         => null,
                'image_versions'              => [
                    '' => [
                        'auto_orient' => true,
                    ]
                ],
                'print_response'    => false,
                'accept_file_types' => '/\.(mp4|moo?v|m4v|mpe?g|wmv|avi|webm|gif|jpe?g|gif|png|stream|wav|ogg|mp3)$/i'
            ]);
            $response = json_decode(json_encode($uploadHandler->get_response()), true);
            Logger::send('debug', ['response' => $response]);
            if (isset($response['files'])) {
                $file = current($response['files']);
                if (isset($file['url'])) {
                    Logger::send('process', ['step' => 'debug', 'data' => PUBPATH . rawurldecode($file['url'])]);
                    $form->setAttributes($_POST);
                    $newName = PUBPATH . '/upload/' . $uploadPath . md5(time()) . rand(0, 999999) . uniqid() . '.' . pathinfo(PUBPATH . $file['url'], PATHINFO_EXTENSION);
                    rename(PUBPATH . rawurldecode($file['url']), $newName);
                    $form->filePath = $newName;
                } else {
                    header('Range: 0-' . ($file['size'] - 1));
                    header('Pragma: no-cache');
                    header('Cache-Control: no-store, no-cache, must-revalidate');
                    header('Content-Disposition: inline; filename="files.json"');
                    header('X-Content-Type-Options: nosniff');
                    return $response;
                }
            }
        } elseif ($request->getContentType() == 'json') {
            $form->setAttributes(json_decode($request->getContent(), true));
        } elseif (isset($_FILES['file'])) {
            if ($_FILES['file']['error']) {
                throw new BadRequestHttpException('Error upload', $_FILES['file']['error']);
            }
            
            $form->setAttributes($_POST);
            $filePath = $form->getLocalPath() . '.' . pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
            $form->filePath = $filePath;
        } else {
            $form->setAttributes($_POST);
        }
        
        $result = $form->process($request->get('id'));
        
        if ($result === false) {
            throw new BadRequestHttpException($form);
        }
        
        $response = [
            'processId' => $result,
            'host'      => parse_url(Config::getInstance()->get('baseUrl'), PHP_URL_HOST),
        ];
        if (isset($_POST['additional']) && is_array($_POST['additional'])) {
            $response['additional'] = $_POST['additional'];
        }
        if ($form->needThumbs) {
            $response['thumbs'] = $form->getThumbs();
        }
        return $response;
    }
}