<?php

namespace brasstacksweb\craftbasicauth;

use brasstacksweb\craftbasicauth\models\Condition;
use brasstacksweb\craftbasicauth\models\Settings;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\App;
use craft\helpers\StringHelper;
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
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        $request = \Craft::$app->getRequest();

        if ($request->getIsCpRequest()) {
            $this->controllerNamespace = 'brasstacksweb\craftbasicauth\controllers';

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function (RegisterUrlRulesEvent $event) {
                    $event->rules['craft-basic-auth/conditions'] = 'craft-basic-auth/conditions';
                }
            );
        }

        $activeConditions = $this->getSettings()->getActiveConditions();

        if ($request->getIsSiteRequest() && count($activeConditions) > 0) {
            $this->processAuthentication($activeConditions);
        }

        // \Craft::$app->onInit(function () {
        // });
    }

    protected function processAuthentication(array $activeConditions): void
    {
        $request = \Craft::$app->getRequest();
        $currentDomain = $request->getHostName();
        $currentPath = $request->getPathInfo();

        foreach ($activeConditions as $key => $condition) {
            // Check environments
            if (!in_array(\Craft::$app->config->env, $condition->environments, true)) {
                continue; // Skip to next condition
            }

            // Check domains
            foreach ($condition->domains as $domain) {
                if (StringHelper::matchWildcard($domain, '/'.$currentDomain)) {
                    continue 2; // Skip to next condition
                }
            }

            // Check excepted paths
            foreach ($condition->exceptedPaths as $path) {
                if (StringHelper::matchWildcard($path, '/'.$currentPath)) {
                    continue 2; // Skip to next condition
                }
            }

            // Check protected paths
            $requiresAuth = false;
            foreach ($condition->protectedPaths as $path) {
                if (StringHelper::matchWildcard($path, '/'.$currentPath)) {
                    $requiresAuth = true;

                    break;
                }
            }

            // If authentication is required, check credentials
            if ($requiresAuth) {
                $username = $_SERVER['PHP_AUTH_USER'] ?? null;
                $password = $_SERVER['PHP_AUTH_PW'] ?? null;

                if ($username === $condition->username && $password === App::parseEnv($condition->password)) {
                    return;
                }

                header('WWW-Authenticate: Basic realm="'.$condition->realm.'"');
                header('HTTP/1.0 401 Unauthorized');
                echo $condition->customFailureMessage ?: 'Authentication required';

                exit;
            }
        }
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
