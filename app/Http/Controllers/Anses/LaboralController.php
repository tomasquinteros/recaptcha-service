<?php

namespace App\Http\Controllers\Anses;

use App\Http\Controllers\Controller;
use App\Models\ChromeSimulator;
use Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class LaboralController extends Controller
{
    private $driver;
    private string $selenium_url;
    private ChromeSimulator $chromeSimulator;

    public function __construct()
    {
        $this->selenium_url = config('app.selenium_url');
        $this->chromeSimulator = new ChromeSimulator();

    }

    public function VL_CO($per_cuit)
    {
        $human_speed = ['min' => 0.1, 'max' => 0.3];
        try {
            if (empty($per_cuit)) throw new Exception('Debe ingresar un CUIL', 400);

            // 1. Configurar y crear driver
            $capabilities = $this->chromeSimulator->setupChrome();

            $this->driver = RemoteWebDriver::create($this->selenium_url, $capabilities);
            // 2. Le decimos que URL debe ir

            $this->driver->get('https://tramites.renaper.gob.ar/mi_ejemplar/');
            // 3. Remover detectores de WebDriver
            $this->driver->executeScript("
                 Object.defineProperty(navigator, 'webdriver', {
                     get: () => undefined,
                 });
                 delete navigator.__webdriver_script_fn;
                 window.chrome = {
                     runtime: {}
                 };
             ");
            $cookies = $this->driver->manage()->getCookies();
            $cookieString = '';
            if (!empty($cookies)) {
                foreach ($cookies as $c) {
                    $cookieString .= $c['name'] . '=' . $c['value'] . ';';
                }
                $cookieString = rtrim($cookieString, ';');
            }

            // 1. Esperar al iframe de reCAPTCHA
            $iframe = $this->driver->wait(1)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::cssSelector("iframe[src*='recaptcha']")
                )
            );
            // 2. Cambiar el contexto al iframe
            $this->driver->switchTo()->frame($iframe);
            // 3. Buscar el input hidden con el token
            $tokenInput = $this->driver->wait(1)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::cssSelector("#recaptcha-token")
                )
            );
            $tokenValue = $tokenInput->getAttribute("value");
            $this->driver->switchTo()->defaultContent();
            if (empty($tokenValue)) throw new \Exception('No se encontro el captcha');
            $this->driver->quit();
            return response()->json([
                'success' => true,
                'token' => $tokenValue,
                'cookies' => $cookieString
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'EXCEPTION',
                'message' => $e->getMessage(),
                'content' => null
            ], $e->getCode() > 200 && $e->getCode() < 500 ?: 500);
        } finally {
            if (isset($this->driver)) {
                $this->driver->quit();
            }
        }
    }
}
