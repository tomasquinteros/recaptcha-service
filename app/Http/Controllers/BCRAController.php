<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChromeSimulator;
use Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BCRAController extends Controller
{
    private $driver;
    private string $selenium_url;
    private ChromeSimulator $chromeSimulator;

    public function __construct()
    {
        $this->selenium_url = config('app.selenium_url');
        $this->chromeSimulator = new ChromeSimulator();

    }

    public function __invoke(Request $request)
    {
        $human_speed = ['min' => 0.1, 'max' => 0.3];
        try {
            // 1. Configurar y crear driver
            $capabilities = $this->chromeSimulator->setupChrome();
            $this->driver = RemoteWebDriver::create($this->selenium_url, $capabilities);
            // 2. Le decimos que URL debe ir
            $this->driver->get('https://www.bcra.gob.ar/BCRAyVos/Situacion_Crediticia.asp');
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

            return response()->json([
                'success' => true,
                'image' => $image,
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
