<?php

namespace brasstacksweb\craftbasicauth\controllers;

use brasstacksweb\craftbasicauth\BasicAuth;
use brasstacksweb\craftbasicauth\models\Condition;
use craft\web\Controller;
use yii\web\Response;

/**
 *  * Stats controller.
 *   */
class ConditionsController extends Controller
{
    public $defaultAction = 'index';
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    public function actionIndex(): Response
    {
        $condition = \Craft::$app->getUrlManager()->getRouteParams()['condition']
            ?? new Condition();

        return $this->renderTemplate('craft-basic-auth/_condition', [
            'condition' => $condition,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();
        $condition = $this->request->getBodyParam('condition', []);

        $plugin = BasicAuth::getInstance();
        $settings = $plugin->getSettings()->getAttributes();
        $settings['conditions'][] = $condition;

        if (!\Craft::$app->getPlugins()->savePluginSettings($plugin, $settings)) {
            $message = \Craft::t('app', 'Couldn’t save plugin settings.');
            $condition = end($plugin->getSettings()->conditions);

            return $this->asFailure($message, [], ['condition' => $condition]);
        }

        return $this->asSuccess(\Craft::t('app', 'Plugin settings saved.'));
    }
}
