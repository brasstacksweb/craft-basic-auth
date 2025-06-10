<?php

namespace brasstacksweb\craftbasicauth\services;

use brasstacksweb\craftbasicauth\models\Condition;
use craft\helpers\App;
use craft\helpers\StringHelper;
use craft\web\Request;
use yii\base\Component;

class Auth extends Component
{
    public function checkRequest(string $env, Request $request, array $conditions, callable $challenge): void
    {
        $activeConditions = array_filter($conditions, fn($c) => $this->matchCondition($env, $request, $c));

        if (count($activeConditions) === 0) {
            return;
        }

        $passedConditions = array_filter(
            $activeConditions,
            fn($c) => $request->authUser === $c->username && $request->authPassword === App::parseEnv($c->password)
        );

        // If any condition passes authentication, allow access
        if (count($passedConditions) > 0) {
            return;
        }

        $failedConditions = array_diff_key($activeConditions, $passedConditions);

        // If authentication failed, challenge with first failed condition
        if (count($failedConditions) > 0) {
            $challenge(reset($failedConditions));
        }
    }

    private function matchCondition(string $env, Request $request, Condition $condition): bool
    {
        $domain = $request->getHostName();
        $path = $request->getPathInfo();

        $envMatches = in_array($env, $condition->environments, true);
        $domainMatches = count(array_filter(
            $condition->domains,
            fn($d) => StringHelper::matchWildcard($d, $domain)
        )) > 0;
        $pathProtected = count(array_filter(
            $condition->protectedPaths,
            fn($p) => StringHelper::matchWildcard($p, '/' . $path)
        )) > 0;
        $pathExcepted = count(array_filter(
            $condition->exceptedPaths,
            fn($p) => StringHelper::matchWildcard($p, '/' . $path)
        )) > 0;

        return $condition->enabled
            && ($envMatches || $domainMatches || $pathProtected)
            && !$pathExcepted;
    }
}
