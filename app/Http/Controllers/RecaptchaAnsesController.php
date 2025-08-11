<?php

namespace App\Http\Controllers;

use App\Models\ChromeSimulator;
use Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Facades\Http;
use Log;

class RecaptchaAnsesController extends Controller
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
            $this->driver->get('https://servicioswww.anses.gob.ar/ooss2/');
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
            // 4. Esperar que la página cargue
            $this->driver->wait(2.5)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName('form')
                )
            );
            // 5. Simular scroll y movimiento
            $this->driver->executeScript("window.scrollTo(0, 200);");
            $this->chromeSimulator->humanDelay(0.2, 0.8);

            // 6. Buscamos elementos del formulario a simular / Boton de continuar y campo de CUIL
            $elements = array();
            $element = $this->driver->findElement(WebDriverBy::id('ContentPlaceHolder1_txtDoc'));
            if ($element->isDisplayed()) $elements['doc_field'] = $element;
            $element = $this->driver->findElement(WebDriverBy::id('ContentPlaceHolder1_Button1'));
            if ($element->isDisplayed() && $element->isEnabled()) $elements['continue_btn'] = $element;
            if (!isset($elements['doc_field']) || !isset($elements['continue_btn'])) throw new Exception('Error en la busqueda de inputs, revisar que sigan funcionando');

            // 7. Llenar formulario como humano
            $elements['doc_field']->click();
            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
            $elements['doc_field']->sendKeys(WebDriverKeys::CONTROL . 'a');
            $elements['doc_field']->sendKeys(WebDriverKeys::DELETE);
            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
            $this->chromeSimulator->typeLikeHuman($elements['doc_field'], $per_cuit);
            // 8. Hacer click en continuar
            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
            $this->driver->executeScript("arguments[0].scrollIntoView(true);", [$elements['continue_btn']]);
            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
            $elements['continue_btn']->click();
            // 9. Esperar respuesta
            $this->chromeSimulator->humanDelay(1, 5);

            // 10. Capturamos info de la pagina
            $page_source = $this->driver->getPageSource();

            // 11. Analizar resultado
            if (strpos($page_source, 'ERROR DE AUTENTICACION') !== false) throw new Exception('Error de autenticación en ANSES', 401);
            if (strpos($page_source, 'El CUIL ingresado no es válido') !== false) throw new Exception('El CUIL ingresado no es válido', 400);

            if (
                strpos($page_source, 'Obra Social') !== false ||
                strpos($page_source, 'obra social') !== false
            ) {
                $cookies = $this->chromeSimulator->getCookies($this->driver);
                $values = array(
                    '__VIEWSTATE' => $this->driver->findElement(WebDriverBy::id('__VIEWSTATE'))->getAttribute('value') ?? '',
                    '__EVENTVALIDATION' => $this->driver->findElement(WebDriverBy::id('__EVENTVALIDATION'))->getAttribute('value') ?? '',
                    '__VIEWSTATEGENERATOR' => $this->driver->findElement(WebDriverBy::id('__VIEWSTATEGENERATOR'))->getAttribute('value') ?? '',
                    'g-recaptcha-response' => $this->driver->findElement(WebDriverBy::id('g-recaptcha-response'))->getAttribute('value') ?? '',
                );
                if (stripos($page_source, '(PROFE)')) {
                    $values['BtnCODEM.x'] = 39;
                    $values['BtnCODEM.y'] = 11;
                } else {
                    $values['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$DGOOSS$ctl02$ctl00';
                    $values['__EVENTARGUMENT'] = '';
                }
                $response = Http::withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept-Language' => 'es-419,es;q=0.9',
                    'Cache-Control' => 'max-age=0',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Cookie' => $cookies,
                    'Origin' => 'http://servicioswww.anses.gob.ar',
                    'Referer' => 'http://servicioswww.anses.gob.ar/ooss2/',
                    'Sec-Ch-Ua' => '"Brave";v="117", "Not;A=Brand";v="8", "Chromium";v="117"',
                    'Sec-Ch-Ua-Mobile' => '?0',
                    'Sec-Ch-Ua-Platform' => '"Linux"',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'same-origin',
                    'Sec-Fetch-User' => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
                ])->asForm()->post('https://servicioswww.anses.gob.ar/ooss2/ConsultaOOSS.aspx', $values);

                if ($response->status() !== 200) throw new Exception('Error al realizar la consulta de descarga en CODEM ANSES', $response->status());
                if (strpos($response->body(), 'El servicio no está')) throw new Exception('Error en la respuesta de ANSES', 500);
                $base64Pdf = base64_encode($response->body());
                $this->driver->quit();
                return response()->json([
                    'success' => true,
                    'message' => 'Consulta realizada exitosamente',
                    'content_step_1' => $page_source,
                    'content_step_2' => $base64Pdf,
                ], 200);
            }
            if (strpos($page_source, 'error') !== false || strpos($page_source, 'Error') !== false) throw new Exception('Error desconocido en la respuesta de ANSES', 500);
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
