<?php
/**
 * User: pel
 * Date: 18/09/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;
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
            $filePath = $form->getLocalPath();
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
            $form->processLocalFile($filePath);
        } else {
            $form->preset = $request->headers->get('X-UPLOAD-PRESET');
            $form->callback = $request->headers->get('X-UPLOAD-CALLBACK');
            $filePath = $form->getLocalPath();
            file_put_contents($filePath, file_get_contents('php://input'));
            $form->processLocalFile($filePath);
        }
    
        if ($processId === false) {
            throw new BadRequestHttpException($form);
        }
    
        return [
            'processId' => $processId
        ];
    }
}