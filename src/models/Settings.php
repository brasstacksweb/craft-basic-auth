<?php

namespace brasstacksweb\craftbasicauth\models;

use craft\base\Model;
use craft\helpers\StringHelper;

class Settings extends Model
{
    public array $conditions = [];

    public function setAttributes($values, $safeOnly = true): void
    {
        parent::setAttributes($values, $safeOnly);

        // Handle conditions from form submissions
        if (isset($values['conditions']) && is_array($values['conditions'])) {
            foreach ($values['conditions'] as $key => $condition) {
                if (!$condition instanceof Condition && is_array($condition)) {
                    $this->conditions[$key] = new Condition(array_merge($condition, [
                        'environments' => is_array($condition['environments'] ?? '') ? $condition['environments'] : [],
                        'domains' => is_array($condition['domains'] ?? '') ? $condition['domains'] : [],
                        'exceptedPaths' => is_array($condition['exceptedPaths'] ?? '') ? $condition['exceptedPaths'] : [],
                        'protectedPaths' => is_array($condition['protectedPaths'] ?? '') ? $condition['protectedPaths'] : [],
                    ]));
                }
            }
        }
    }

    public function rules(): array
    {
        return [
            [['conditions'], 'validateConditions'],
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

    public function getActiveConditions(): array
    {
        $activeConditions = [];
        $currentEnvironment = \Craft::$app->config->env;
        $currentDomain = \Craft::$app->request->getHostName();

        foreach ($this->conditions as $key => $condition) {
            if (!$condition->enabled) {
                continue;
            }

            // Check environment match (case-insensitive)
            $environmentMatch = false;
            foreach ($condition->environments as $environment) {
                if (strtolower($environment) === strtolower($currentEnvironment)) {
                    $environmentMatch = true;

                    break;
                }
            }

            // Check domain match
            $domainMatch = false;
            foreach ($condition->domains as $domain) {
                if (StringHelper::matchWildcard($domain, $currentDomain)) {
                    $domainMatch = true;

                    break;
                }
            }

            // Condition is active if any condition matches
            if ($environmentMatch || $domainMatch) {
                $activeConditions[$key] = $condition;
            }
        }

        return $activeConditions;
    }

    public function validateRealmUniqueness(): bool
    {
        $realms = [];
        $valid = true;

        foreach ($this->conditions as $key => $condition) {
            if (!$condition->enabled) {
                continue;
            }

            if (isset($realms[$condition->realm])) {
                $condition->addError('realm', "Realm name '{$condition->realm}' is already used by another condition.");
                $valid = false;
            } else {
                $realms[$condition->realm] = true;
            }
        }

        return $valid;
    }
}
