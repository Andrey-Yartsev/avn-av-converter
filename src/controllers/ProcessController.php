<?php
/**
 * User: pel
 * Date: 24/10/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;
use Converter\components\FileType;
use Converter\components\Process;
use Converter\components\Redis;
use Converter\exceptions\BadRequestHttpException;
use Converter\forms\UploadForm;

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
        if (empty($postData['processId']) && empty($postData['processIds'])) {
            throw new BadRequestHttpException('ProcessId is required');
        }
        if (isset($postData['processIds']) && is_array($postData['processIds'])) {
            $processIds = $postData['processIds'];
        } else {
            $processIds[] = $postData['processId'];
        }
        $response = [];
        foreach ($processIds as $processId) {
            $queue = Redis::getInstance()->get('queue:' . $processId);
            if ($queue) {
                $queue = json_decode($queue, true);
                if (isset($queue['file'])) {
                    $response[] = [
                        'processId' => $processId,
                        'files'     => $queue['files']
                    ];
                }
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
        if ($request->getContentType() == 'json') {
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
        
        Redis::getInstance()->incr('status.requests');
        return [
            'processId' => $result
        ];
    }
}