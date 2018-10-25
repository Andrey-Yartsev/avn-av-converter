<?php
/**
 * User: pel
 * Date: 24/10/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;
use Converter\components\FileType;
use Converter\components\Process;
use Converter\exceptions\BadRequestHttpException;
use Converter\forms\UploadForm;

class ProcessController extends Controller
{
    public function actionStart()
    {
        $request = $this->getRequest();
        $postData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        if (empty($postData['processId']) && empty($postData['processIds'])) {
            throw new BadRequestHttpException('ProcessId is required');
        }
        $content = json_encode(['success' => true]);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($content));
        echo $content;
        fastcgi_finish_request();
        if (isset($postData['processIds']) && is_array($postData['processIds'])) {
            foreach ($postData['processIds'] as $processId) {
                Process::start($processId);
            }
        } else {
            Process::start($postData['processId']);
        }
    }
    
    public function actionUpload()
    {
        $request = $this->getRequest();
        $form = new UploadForm();
        if ($request->getContentType() == 'json') {
            $form->setAttributes(json_decode($request->getContent(), true));
            $form->process();
        } elseif (isset($_FILES['file'])) {
            $form->setAttributes($_POST);
            $extensions = FileType::getInstance()->findExtensions($_FILES['file']['type']);
            if (empty($extensions)) {
                throw new BadRequestHttpException('Invalid file type');
            }
            $filePath = $form->getLocalPath() . '.' . end($extensions);
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
            $form->filePath = $filePath;
        } else {
            $form->preset = $request->headers->get('X-UPLOAD-PRESET');
            $form->callback = $request->headers->get('X-UPLOAD-CALLBACK');
            $form->isDelay = $request->headers->get('X-UPLOAD-DELAY');
            $filePath = $form->getLocalPath();
            file_put_contents($filePath, file_get_contents('php://input'));
            $extensions = FileType::getInstance()->findExtensions(mime_content_type($filePath));
            if (empty($extensions)) {
                throw new BadRequestHttpException('Invalid file type');
            }
            rename($filePath, $filePath . '.' . end($extensions));
            $form->filePath = $filePath . '.' . end($extensions);
        }
    
        $processId = $form->process();
        
        if ($processId === false) {
            throw new BadRequestHttpException($form);
        }
    
        return [
            'processId' => $processId
        ];
    }
}