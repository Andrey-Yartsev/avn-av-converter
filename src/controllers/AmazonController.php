<?php
/**
 * User: pel
 * Date: 17/09/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;
use Converter\exceptions\BadRequestHttpException;
use Converter\forms\AmazonForm;

class AmazonController extends Controller
{
    public function actionProcess()
    {
        $request = $this->getRequest();
        $formData = $request->getContentType() == 'json' ? json_decode($request->getContent(), true) : [];
        $form = new AmazonForm();
        $form->setAttributes($formData);
        $processId = $form->addQueue();
        if ($processId === false) {
            throw new BadRequestHttpException($form);
        }
    
        return [
            'processId' => $processId
        ];
    }
}