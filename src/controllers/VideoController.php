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
        $formData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        $form = new VideoForm();
        $form->setAttributes($formData);
        $processId = $form->process();
        if ($processId === false) {
            throw new BadRequestHttpException($form);
        }
        
        return [
            'processId' => $processId
        ];
    }
}