<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;
use Converter\components\FileType;
use Converter\components\Process;
use Converter\exceptions\BadRequestHttpException;
use Converter\forms\VideoForm;

class VideoController extends Controller
{
    public function actionProcess()
    {
        $request = $this->getRequest();
        $form = new VideoForm();
        if ($request->getContentType() == 'json') {
            $form->setAttributes(json_decode($request->getContent(), true));
            $processId = $form->processExternalFile();
        } elseif (isset($_FILES['file'])) {
            $form->setAttributes($_POST);
            $extensions = FileType::getInstance()->findExtensions($_FILES['file']['type']);
            if (empty($extensions)) {
                throw new BadRequestHttpException('Invalid file type');
            }
            $filePath = $form->getLocalPath() . '.' . end($extensions);
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
            $processId = $form->processLocalFile($filePath);
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
            $processId = $form->processLocalFile($filePath . '.' . end($extensions));
        }
    
        if ($processId === false) {
            throw new BadRequestHttpException($form);
        }
    
        return [
            'processId' => $processId
        ];
    }
    
    public function actionStart()
    {
        $request = $this->getRequest();
        $postData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        if (empty($postData['processId'])) {
            throw new BadRequestHttpException('ProcessId is required');
        }
        Process::start($postData['processId']);
        return ['success' => true];
    }
}