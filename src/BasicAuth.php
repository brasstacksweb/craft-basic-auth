<?php

namespace brasstacksweb\craftbasicauth;

use brasstacksweb\craftbasicauth\models\Condition;
use brasstacksweb\craftbasicauth\models\Settings;
use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\web\Request;
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
    public string $schemaVersion = '2.0.1';
    public bool $hasCpSettings = true;

    public function init(): void
    {
        parent::init();

        $request = \Craft::$app->getRequest();

        if ($request->getIsConsoleRequest()) {
            return;
        }

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

        $activeConditions = $this->getSettings()->getActiveConditions();

        if (count($activeConditions) > 0) {
            foreach ($activeConditions as $condition) {
                if ($this->matchCondition($request, $condition)) {
                    $this->requireAuthentication($condition);
                }
            }
        }

        // \Craft::$app->onInit(function () {
        // });
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

    private function matchCondition(Request $request, Condition $condition): bool
    {
        $currentDomain = $request->getHostName();
        $currentPath = $request->getPathInfo();
        $environmentMatches = in_array(\Craft::$app->config->env, $condition->environments, true);
        $domainMatches = count(array_filter(
            $condition->domains,
            fn($d) => StringHelper::matchWildcard($d, '/' . $currentDomain)
        )) > 0;
        $triggered = $environmentMatches || $domainMatches;
        $pathProtected = count(array_filter(
            $condition->protectedPaths,
            fn($p) => StringHelper::matchWildcard($p, '/' . $currentPath)
        )) > 0;
        $pathExcepted = count(array_filter(
            $condition->exceptedPaths,
            fn($p) => StringHelper::matchWildcard($p, '/' . $currentPath)
        )) > 0;

        return ($triggered || $pathProtected) && !$pathExcepted;
    }

    private function requireAuthentication(Condition $condition): void
    {
        $username = $_SERVER['PHP_AUTH_USER'] ?? null;
        $password = $_SERVER['PHP_AUTH_PW'] ?? null;

        if ($username === $condition->username && $password === App::parseEnv($condition->password)) {
            return;
        }

        header('WWW-Authenticate: Basic realm="' . $condition->realm . '"');
        header('HTTP/1.0 401 Unauthorized');
        echo $condition->customFailureMessage ?: 'Authentication required';

        exit;
    }
}
