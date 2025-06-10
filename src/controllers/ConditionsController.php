<?php

namespace brasstacksweb\craftbasicauth\controllers;

use brasstacksweb\craftbasicauth\BasicAuth;
use brasstacksweb\craftbasicauth\models\Condition;
use craft\helpers\Cp;
use craft\web\Controller;
use yii\web\Response;

/**
 *  * Stats controller.
 *   */
class ConditionsController extends Controller
{
    public $defaultAction = 'index';
    protected array|bool|int $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;
    private bool $readOnly = false;

    public function init(): void
    {
        parent::init();

        $this->readOnly = !\Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
    }

    public function actionIndex(): Response
    {
        $condition = \Craft::$app->getUrlManager()->getRouteParams()['condition']
            ?? new Condition();
        $response = $this->asCpScreen()
            ->title('New Authentication Condition')
            ->action('craft-basic-auth/conditions/save')
            ->redirectUrl('settings/plugins/craft-basic-auth')
            ->addCrumb('All Conditions', 'settings/plugins/craft-basic-auth')
            ->contentTemplate('craft-basic-auth/_condition.twig', [
                'condition' => $condition,
            ]);

        if ($this->readOnly) {
            $response
                ->noticeHtml(Cp::readOnlyNoticeHtml())
                ->action(null)
                ->redirectUrl(null);
        }

        return $response;
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        if ($this->readOnly) {
            return $this->asFailure(\Craft::t('app', 'Admin changes not allowed.'));
        }

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
