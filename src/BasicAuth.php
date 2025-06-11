<?php

namespace brasstacksweb\craftbasicauth;

use brasstacksweb\craftbasicauth\models\Condition;
use brasstacksweb\craftbasicauth\models\Settings;
use brasstacksweb\craftbasicauth\services\Auth;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
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
    public string $schemaVersion = '2.0.6';
    public bool $hasCpSettings = true;
    public Auth $auth;

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

        $env = \Craft::$app->config->env;
        $request = \Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return;
        }

        $this->auth = $this->get('auth');

        if ($request->getIsCpRequest()) {
            $this->controllerNamespace = 'brasstacksweb\craftbasicauth\controllers';

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function(RegisterUrlRulesEvent $event) {
                    $event->rules['craft-basic-auth/conditions'] = 'craft-basic-auth/conditions';
                }
            );
        }

        $conditions = $this->getSettings()->conditions;

        if (count($conditions) === 0) {
            return;
        }

        $this->auth->checkRequest($env, $request, $conditions, [$this, 'sendChallenge']);
    }

    public function sendChallenge(Condition $condition): void
    {
        header('WWW-Authenticate: Basic realm="' . $condition->realm . '"');
        header('HTTP/1.0 401 Unauthorized');
        echo $condition->customFailureMessage ?: 'Authentication required';

        exit;
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
}
