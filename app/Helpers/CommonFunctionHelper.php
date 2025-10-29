<?php

namespace App\Helpers;

use App\Models\Setting;

class CommonFunctionHelper
{
    public static function settingAttributes() : array
    {
        $setting = Setting::first();
        return $setting ? $setting->setting_attribute : [];
    }
}