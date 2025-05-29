<?php

namespace brasstacksweb\craftbasicauth\models;

use craft\base\Model;
use craft\helpers\StringHelper;

class Settings extends Model
{
    /**
     * @var array Array of authentication rules
     */
    public array $authRules = [];

    /**
     * Initialize settings with default values
     */
    public function init(): void
    {
        parent::init();
        
        // Convert authRules to Condition models if they aren't already
        foreach ($this->authRules as $key => $rule) {
            if (!$rule instanceof Condition) {
                $this->authRules[$key] = new Condition($rule);
            }
        }
    }
    
    /**
     * Set attributes from form submission
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        parent::setAttributes($values, $safeOnly);
        
        // Handle authRules from form submissions
        if (isset($values['authRules']) && is_array($values['authRules'])) {
            $this->authRules = [];
            
            foreach ($values['authRules'] as $key => $rule) {
                // Generate a unique key if needed
                if (!is_string($key) || empty($key)) {
                    $key = 'rule-' . StringHelper::UUID();
                }
                
                $this->authRules[$key] = new Condition($rule);
            }
        }
    }
    
    /**
     * Get active rules based on current environment, domain, and site
     */
    public function getActiveRules(): array
    {
        $activeRules = [];
        $currentEnvironment = \Craft::$app->config->env;
        $currentDomain = \Craft::$app->request->getHostName();
        $currentSite = \Craft::$app->sites->getCurrentSite()->handle;
        
        foreach ($this->authRules as $key => $rule) {
            if (!$rule->enabled) {
                continue;
            }
            
            // Check environment match (case-insensitive)
            $environmentMatch = false;
            foreach ($rule->environments as $environment) {
                if (strtolower($environment) === strtolower($currentEnvironment)) {
                    $environmentMatch = true;
                    break;
                }
            }
            
            // Check domain match
            $domainMatch = false;
            foreach ($rule->domains as $domain) {
                if ($this->matchWildcardPattern($domain, $currentDomain)) {
                    $domainMatch = true;
                    break;
                }
            }
            
            // Check site match
            $siteMatch = in_array($currentSite, $rule->sites, true);
            
            // Rule is active if any condition matches
            if ($environmentMatch || $domainMatch || $siteMatch) {
                $activeRules[$key] = $rule;
            }
        }
        
        return $activeRules;
    }
    
    /**
     * Match a string against a wildcard pattern
     */
    private function matchWildcardPattern(string $pattern, string $string): bool
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        
        return (bool) preg_match('/^' . $pattern . '$/i', $string);
    }
    
    /**
     * Validate that realm names are unique
     */
    public function validateRealmUniqueness(): bool
    {
        $realms = [];
        $valid = true;
        
        foreach ($this->authRules as $key => $rule) {
            if (!$rule->enabled) {
                continue;
            }
            
            if (isset($realms[$rule->realm])) {
                $rule->addError('realm', "Realm name '{$rule->realm}' is already used by another rule.");
                $valid = false;
            } else {
                $realms[$rule->realm] = true;
            }
        }
        
        return $valid;
    }
}