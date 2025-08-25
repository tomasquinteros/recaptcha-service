<?php

namespace App\Models;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverKeys;

class ChromeSimulator
{

    public function setupChrome(array $prefsCustom = array())
    {
        $options = new ChromeOptions();
        $options->addArguments([
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--disable-web-security',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            '--window-size=1920,1080',
            '--start-maximized'
        ]);

        // Configurar para evitar detección de bot
        $prefs = [
//            'profile.default_content_setting_values.notifications' => 2,
//            'profile.managed_default_content_settings.images' => 2
        ];
        if (count($prefsCustom) > 0) {
//            $prefs = array_merge($prefs, $prefsCustom);
        }
//        $options->setExperimentalOption('prefs', $prefs);
        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        return $capabilities;
    }

    public function humanDelay(float $min_seconds = null, float $max_seconds = null): void
    {
        $delay = ['min' => 0.0, 'max' => 0.01];
        if (!empty($min_seconds)) $delay['min'] = $min_seconds;
        if (!empty($max_seconds)) $delay['max'] = $max_seconds; // FIX: era $delay['min']

        usleep(rand($delay['min'] * 1_000_000, $delay['max'] * 1_000_000));
    }

    public function typeLikeHuman($element, $text, $split = true): void
    {
        if ($split) {
            foreach (str_split($text) as $char) {
                $element->sendKeys($char);
                // Delay aleatorio entre caracteres (50-200ms)
                usleep(rand(25000, 100000));
            }
        } else {
            $element->clear();
            $element->sendKeys($text);
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
        /**
     * @throws \Exception
     */
    public function findElements($driver, $elements): array
    {
        $elementsFound = array();
        foreach($elements as $element) {
            if (empty($element['type'])) throw new \Exception('Debe especificar el type de atributo a buscar.');
            if (empty($element['value'])) throw new \Exception('Debe especificar el value de atributo a buscar.');

            switch ($element['type']) {
                case 'id':
                    $value = $driver->findElement(WebDriverBy::id($element['value']));
                    break;
                case 'class_name':
                    $value = $driver->findElement(WebDriverBy::className($element['value']));
                    break;
                case 'tag_name':
                    $value = $driver->findElement(WebDriverBy::tagName($element['value']));
                    break;
                case 'name':
                    $value = $driver->findElement(WebDriverBy::name($element['value']));
                    break;
                case 'css_selector':
                    $value = $driver->findElement(WebDriverBy::cssSelector($element['value']));
                    break;
                case 'linktext':
                    $value = $driver->findElement(WebDriverBy::linkText($element['value']));
                    break;
                case 'partial_link_text':
                    $value = $driver->findElement(WebDriverBy::partialLinkText($element['value']));
                    break;
                case 'xpath':
                    $value = $driver->findElement(WebDriverBy::xpath($element['value']));
                    break;
            }

            if($value->isDisplayed() && $value->isEnabled()) $elementsFound[$element['value']] = $value;
        }

        if (count($elementsFound) !== count($elements)) throw new \Exception('Error en la busqueda de elementos html.');
        return $elementsFound;
    }

    public function typingElement ($chromeSimulator ,$element, $value): void
    {
        $element->click();
        $chromeSimulator->humanDelay();
        $element->clear();
        $element->sendKeys(WebDriverKeys::DELETE);
        $chromeSimulator->humanDelay();
        $chromeSimulator->typeLikeHuman($element, $value);
        $chromeSimulator->humanDelay();
    }
}
