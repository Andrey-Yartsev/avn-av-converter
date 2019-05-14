<?php
/**
 * User: pel
 * Date: 20/11/2018
 */

namespace Converter\controllers;


use Aws\Sns\Exception\InvalidSnsMessageException;
use Aws\Sns\Message;
use Aws\Sns\MessageValidator;
use Converter\components\Controller;
use Converter\components\Logger;
use Converter\exceptions\NotFoundHttpException;

class AmazonController extends Controller
{
    public function actionSns()
    {
        $message = Message::fromRawPostData();
        $validator = new MessageValidator();
        
        try {
            $validator->validate($message);
        } catch (InvalidSnsMessageException $e) {
            Logger::send('amazon.sns.validate', [
                'error' => $e->getMessage()
            ]);
            throw new NotFoundHttpException();
        }
    
        if ($message['Type'] === 'SubscriptionConfirmation') {
            file_get_contents($message['SubscribeURL']);
        } elseif ($message['Type'] === 'Notification') {
            Logger::send('amazon.sns.notification', [
                'messageId' => $message['MessageId'],
                'message' => $message['Message']
            ]);
        }
        
        return true;
    }
}