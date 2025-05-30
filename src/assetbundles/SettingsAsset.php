<?php

namespace brasstacksweb\craftbasicauth\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SettingsAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@brasstacksweb/craftbasicauth/resources';
        $this->depends = [
            CpAsset::class,
        ];
        $this->js = [
            'scripts/settings.js',
        ];
        $this->css = [];

        parent::init();
    }
}
