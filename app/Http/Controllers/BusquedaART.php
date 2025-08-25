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

class BusquedaART extends Controller
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

            $this->driver->get('https://www.srt.gob.ar/arg/art_busqueda_art-08.php');
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

            sleep(10);
            $this->driver->quit();
            $cookies = $this->driver->manage()->getCookies();
            $cookieString = '';
            if (!empty($cookies)) {
                foreach ($cookies as $c) {
                    $cookieString .= $c['name'] . '=' . $c['value'] . ';';
                }
                $cookieString = rtrim($cookieString, ';');
            }
            $tokenCRSF = $this->driver->findElement(WebDriverBy::name("token-crsf"));

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
            dd($this->driver->getPageSource());
            if (empty($tokenValue)) throw new \Exception('No se encontro el captcha');
            $this->driver->quit();

            $response = Http::withHeaders([
                'Accept' => '*/*',
                'Origin' => 'https://tramites.renaper.gob.ar',
                'Referer' => 'https://tramites.renaper.gob.ar/mi_ejemplar/',
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36'
            ])
                ->timeout(30)
                ->asMultipart()
                ->post('https://tramites.renaper.gob.ar/mi_ejemplar/busqueda.php', [
                    'dni' => $request->dni,
                    'tipodoc' => $request->tipoDoc,
                    'fecha' => $request->fecha,
                    'token' => $tokenValue,
                    'action' => 'submit_tramite'
            ]);

            dd($response->body());

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
