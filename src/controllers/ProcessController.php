<?php
/**
 * User: pel
 * Date: 24/10/2018
 */

namespace Converter\controllers;


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
    
    public function actionStart()
    {
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
            $queue = Redis::getInstance()->get('queue:' . $process['id']);
            if ($queue) {
                $queue = json_decode($queue, true);
                $processIds[] = $process['id'];
                $driver = Process::getDriver($queue);
                if (!$driver) {
                    continue;
                }
                $files = [
                    FileHelper::getFileResponse($queue['filePath'], $queue['fileType'])
                ];
                switch ($queue['fileType']) {
                    case FileHelper::TYPE_VIDEO:
                        $duration = FileHelper::getVideoDuration($queue['filePath']);
                        
                        if (isset($driver->thumbs['maxCount'])) {
                            if ($duration > $driver->thumbs['maxCount']) {
                                $maxCount = $driver->thumbs['maxCount'];
                                $step = floor($duration / $driver->thumbs['maxCount']);
                            } else {
                                $maxCount = $duration;
                                $step = 1;
                            }
                        } else {
                            $maxCount = $step = 1;
                        }
                        
                        if ($duration == 0) {
                            $driver->createVideoPreview($queue['filePath'], $queue['watermark'], $duration);
                        } else {
                            for ($i = 0; $i < $maxCount; $i++) {
                                $driver->createVideoPreview($queue['filePath'], $queue['watermark'], $i * $step);
                            }
                        }
                        
                        break;
                    case FileHelper::TYPE_IMAGE:
                        $driver->createPhotoPreview($queue['filePath'], $queue['watermark']);
                        break;
                }
                $files = array_merge($files, $driver->getResult());
                $response[] = [
                    'processId' => $process['id'],
                    'files'     => $files
                ];
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
        if (isset($_SERVER['HTTP_CONTENT_RANGE'])) {
            $uploadHandler = new FileUploadHandler([
                'access_control_allow_origin' => false,
                'script_url'                  => '/actions/',
                'upload_dir'                  => PUBPATH . '/upload/',
                'upload_url'                  => '/upload/',
                'max_file_size'               => 4294967296,
                'min_file_size'               => 1,
                'max_number_of_files'         => null,
                'image_versions'              => [
                    '' => [
                        'auto_orient' => true,
                    ]
                ],
                'print_response'    => false,
                'accept_file_types' => '/\.(mp4|moo?v|m4v|mpe?g|wmv|avi|webm)$/i'
            ]);
            Logger::send('debug', $uploadHandler->get_response());
            $response = json_decode(json_encode($uploadHandler->get_response()), true);
            Logger::send('debug', $response);
            Logger::send('debug', ['temp' => isset($response['files'])]);
            if (isset($response['files'])) {
                $file = current($response['files']);
                if (isset($file['url'])) {
                    $form->filePath = PUBPATH . $file['url'];
                } else {
                    header('Range: 0-' . $file['size']);
                    Logger::send('debug', $response);
                    Logger::send('debug', $uploadHandler->get_response());
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
            $extension = FileType::getInstance()->findExtensions($_FILES['file']['type']);
            if (empty($extension)) {
                throw new BadRequestHttpException('Invalid file type');
            }
            $filePath = $form->getLocalPath() . '.' . $extension;
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
            $form->filePath = $filePath;
        } else {
            $form->preset = $request->headers->get('X-UPLOAD-PRESET');
            $form->callback = $request->headers->get('X-UPLOAD-CALLBACK');
            $form->isDelay = $request->headers->get('X-UPLOAD-DELAY');
            $filePath = $form->getLocalPath();
            file_put_contents($filePath, file_get_contents('php://input'));
            $extension = FileType::getInstance()->findExtensions(mime_content_type($filePath));
            if (empty($extension)) {
                throw new BadRequestHttpException('Invalid file type');
            }
            rename($filePath, $filePath . '.' . $extension);
            $form->filePath = $filePath . '.' . $extension;
        }
        
        $result = $form->process($request->get('id'));
        
        if ($result === false) {
            throw new BadRequestHttpException($form);
        }
        
        $response = [
            'processId' => $result
        ];
        if ($form->needThumbs) {
            $response['thumbs'] = $form->getThumbs();
        }
        return $response;
    }
}