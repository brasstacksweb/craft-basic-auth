<?php

namespace brasstacksweb\craftbasicauth\models;

use craft\base\Model;
use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;

class Settings extends Model
{
    public array $conditions = [];

    public function setAttributes($values, $safeOnly = true): void
    {
        parent::setAttributes($values, $safeOnly);

        // Handle conditions from form submissions
        // Hacky array check and flattening thanks for how editable tables force associative arrays
        if (isset($values['conditions']) && is_array($values['conditions'])) {
            foreach ($values['conditions'] as $key => $condition) {
                if (!$condition instanceof Condition && is_array($condition)) {
                    $this->conditions[$key] = new Condition(array_merge($condition, [
                        'environments' => $this->flatten($condition, 'environments'),
                        'domains' => $this->flatten($condition, 'domains'),
                        'exceptedPaths' => $this->flatten($condition, 'exceptedPaths'),
                        'protectedPaths' => $this->flatten($condition, 'protectedPaths'),
                    ]));
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            [['conditions'], 'validateConditions'],
            [['conditions'], 'validateConditionRealms'],
        ];
    }

    public function validateConditions($attribute, $params): void
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->validate()) {
                $this->addErrors($condition->getErrors());
            }
        }
    }

    public function validateConditionRealms($attribute, $params): void
    {
        $realms = [];

        foreach ($this->conditions as $key => $condition) {
            if (isset($realms[$condition->realm])) {
                $condition->addError('realm', "Realm '{$condition->realm}' is used by another condition.");
            } else {
                $realms[$condition->realm] = true;
            }
        }
    }

    public function getActiveConditions(): array
    {
        $currentEnvironment = \Craft::$app->config->env;
        $currentDomain = \Craft::$app->request->getHostName();

        return array_reduce($this->conditions, function ($carry, $condition) use ($currentEnvironment, $currentDomain) {
            if (!$condition->enabled) {
                return $carry;
            }

            // Check environment match (case-insensitive)
            foreach ($condition->environments as $environment) {
                if (strtolower($environment) === strtolower($currentEnvironment)) {
                    return [...$carry, $condition];
                }
            }

            // Check domain match
            foreach ($condition->domains as $domain) {
                if (StringHelper::matchWildcard($domain, $currentDomain)) {
                    return [...$carry, $condition];
                }
            }

            return $carry;
        }, []);
    }

    private function flatten(array $attrs, string $key): array
    {
        return is_array($attrs[$key] ?? '') ? ArrayHelper::flatten($attrs[$key]) : [];
    }
}
