<?php
/**
 * User: pel
 * Date: 07/09/2018
 */

namespace Converter\controllers;


use Converter\components\Controller;

class TestController extends Controller
{
    public function actionTest($userId)
    {
        echo $userId;
    }
}