<?php

namespace brasstacksweb\craftbasicauth;

use brasstacksweb\craftbasicauth\models\Condition;
use brasstacksweb\craftbasicauth\models\Settings;
use brasstacksweb\craftbasicauth\services\Auth;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\Response;
use craft\web\UrlManager;
use yii\base\Event;

/**
 * Craft Basic Auth plugin.
 *
 * @method static BasicAuth getInstance()
 * @method        Settings  getSettings()
 *
 * @author Brass Tacks Web <help@brasstacksweb.com>
 * @copyright Brass Tacks Web
 * @license https://craftcms.github.io/license/ Craft License
 */
class BasicAuth extends Plugin
{
    public string $schemaVersion = '1.0.6';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => [
                'auth' => Auth::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $request = \Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return;
        }

        if ($request->getIsCpRequest()) {
            $this->controllerNamespace = 'brasstacksweb\craftbasicauth\controllers';

            $this->attachCpEventHandlers();
        }

        $conditions = $this->getSettings()->conditions;

        if (count($conditions) === 0) {
            return;
        }

        $env = \Craft::$app->config->env;
        $activeCondition = $this->auth->getActiveCondition($env, $request, $conditions);

        if ($activeCondition === null) {
            return;
        }

        $response = \Craft::$app->getResponse();

        $this->sendChallenge($response, $activeCondition);
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return \Craft::$app->view->renderTemplate('craft-basic-auth/_settings.twig', [
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachCpEventHandlers(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['craft-basic-auth/conditions'] = 'craft-basic-auth/conditions';
            }
        );
    }

    private function sendChallenge(Response $response, Condition $condition): void
    {
        $response->headers->set('WWW-Authenticate', 'Basic realm="' . $condition->realm . '"');
        $response->statusCode = 401;
        $response->content = $condition->customFailureMessage ?: 'Authentication required';
        $response->send();

        \Craft::$app->end();
    }
}
