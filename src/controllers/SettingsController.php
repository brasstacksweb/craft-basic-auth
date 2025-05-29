<?php

namespace brasstacksweb\craftbasicauth\controllers;

use brasstacksweb\craftbasicauth\BasicAuth;
use brasstacksweb\craftbasicauth\models\Settings;
use Craft;
use craft\web\Controller;
use yii\web\Response;

/**
 * Settings controller for the Craft Basic Auth plugin
 */
class SettingsController extends Controller
{
    /**
     * Save plugin settings
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();
        
        $request = Craft::$app->getRequest();
        $settings = BasicAuth::getInstance()->getSettings();
        
        // Get values from POST
        $postValues = $request->getBodyParams();
        $authRules = $postValues['authRules'] ?? [];
        
        // Process and validate authRules
        $processedRules = [];
        $hasErrors = false;
        
        foreach ($authRules as $key => $ruleData) {
            // Handle form data
            $ruleData['enabled'] = (bool)($ruleData['enabled'] ?? false);
            $ruleData['bypassForLoggedInUsers'] = (bool)($ruleData['bypassForLoggedInUsers'] ?? false);
            
            // Convert array data
            foreach (['environments', 'domains', 'sites', 'ipWhitelist', 'userAgentExceptions', 'exceptedPaths', 'protectedPaths'] as $arrayField) {
                if (isset($ruleData[$arrayField]) && is_string($ruleData[$arrayField])) {
                    // Handle tag input values
                    $ruleData[$arrayField] = array_filter(
                        array_map('trim', explode(',', $ruleData[$arrayField])), 
                        function($value) { return $value !== ''; }
                    );
                } elseif (!isset($ruleData[$arrayField])) {
                    $ruleData[$arrayField] = [];
                }
            }
            
            $processedRules[$key] = $ruleData;
        }
        
        // Set values and validate
        $postValues['authRules'] = $processedRules;
        $settings->setAttributes($postValues);
        
        // Validate uniqueness of realm names
        $settings->validateRealmUniqueness();
        
        // Check for validation errors
        $hasErrors = false;
        foreach ($settings->authRules as $rule) {
            if ($rule->hasErrors()) {
                $hasErrors = true;
                break;
            }
        }
        
        // Save if no errors
        if (!$hasErrors) {
            Craft::$app->getPlugins()->savePluginSettings(BasicAuth::getInstance(), $postValues);
            Craft::$app->getSession()->setNotice(Craft::t('craft-basic-auth', 'Settings saved.'));
            
            return $this->redirectToPostedUrl();
        }
        
        // If there are errors, return them
        Craft::$app->getSession()->setError(Craft::t('craft-basic-auth', 'Couldn't save settings.'));
        
        return $this->renderTemplate('craft-basic-auth/_settings', [
            'settings' => $settings,
        ]);
    }
}