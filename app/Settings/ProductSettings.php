<?php

namespace App\Settings;

use App\Models\CmsSiteSetting;

class ProductSettings
{
    public function __construct(private readonly CmsSiteSetting $setting)
    {
    }

    public static function from(?CmsSiteSetting $setting): self
    {
        return new self($setting ?? new CmsSiteSetting());
    }

    public function registrationOpen(): bool
    {
        return (bool) $this->setting->getAttribute('register_status');
    }

    

    

    

    

    

    
}
