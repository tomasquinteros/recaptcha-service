<?php

namespace App\Models;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;

class ChromeSimulator
{

    public function setupChrome(array $prefsCustom = array())
    {
        $options = new ChromeOptions();
        $options->addArguments([
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--disable-web-security',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            '--window-size=1920,1080',
            '--start-maximized'
        ]);

        // Configurar para evitar detección de bot
        $prefs = [
            'profile.default_content_setting_values.notifications' => 2,
            'profile.managed_default_content_settings.images' => 2
        ];
        if (count($prefsCustom) > 0) {
            $prefs = array_merge($prefs, $prefsCustom);
        }
        $options->setExperimentalOption('prefs', $prefs);
        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        return $capabilities;
    }

    public function humanDelay($min_seconds, $max_seconds)
    {
        $delay = rand($min_seconds * 1000000, $max_seconds * 1000000);
        usleep($delay);
    }

    public function typeLikeHuman($element, $text)
    {
        foreach (str_split($text) as $char) {
            $element->sendKeys($char);
            // Delay aleatorio entre caracteres (50-200ms)
            usleep(rand(25000, 100000));
        }
    }

    public function getCookies($driver): string
    {
        $cookies = $driver->manage()->getCookies();
        $cookieStrings = array();
        foreach ($cookies as $cookie) {
            $cookieStrings[] = $cookie['name'] . '=' . $cookie['value'];
        }

        return implode('; ', $cookieStrings);
    }

}
