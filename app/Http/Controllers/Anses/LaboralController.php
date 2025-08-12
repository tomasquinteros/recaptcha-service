<?php
//
//namespace App\Http\Controllers\Anses;
//
//use App\Http\Controllers\Controller;
//use App\Models\ChromeSimulator;
//use Exception;
//use Facebook\WebDriver\WebDriverBy;
//use Facebook\WebDriver\WebDriverExpectedCondition;
//use Facebook\WebDriver\WebDriverKeys;
//use Illuminate\Support\Facades\Http;
//
//class LaboralController extends Controller
//{
//    private $driver;
//    private ChromeSimulator $chromeSimulator;
//
//    public function __construct()
//    {
//        $this->chromeSimulator = new ChromeSimulator();
//    }
//
//    public function VL_CO($per_cuit)
//    {
//        $human_speed = ['min' => 0.0, 'max' => 0.01];
//
//        try {
//            if (empty($per_cuit)) throw new Exception('Debe ingresar un CUIL', 400);
//
//            // 1. Crear driver usando tu ChromeSimulator (que internamente usa selenium_url)
//            $this->driver = $this->chromeSimulator->createDriver();
//
//            // 2. Ir a la URL
//            $this->driver->get('https://servicioswww.anses.gob.ar/ooss2/');
//
//            // 3. Remover detectores WebDriver
//            $this->driver->executeScript("
//                Object.defineProperty(navigator, 'webdriver', {
//                    get: () => undefined,
//                });
//                delete navigator.__webdriver_script_fn;
//                window.chrome = {
//                    runtime: {}
//                };
//            ");
//
//            // 4. Esperar carga del formulario
//            $this->driver->wait(0.15)->until(
//                WebDriverExpectedCondition::presenceOfElementLocated(
//                    WebDriverBy::tagName('form')
//                )
//            );
//
//            // 5. Scroll y delay humano
//            $this->driver->executeScript("window.scrollTo(0, 200);");
//            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
//
//            // 6. Buscar elementos del formulario
//            $elements = [];
//            $element = $this->driver->findElement(WebDriverBy::id('ContentPlaceHolder1_txtDoc'));
//            if ($element->isDisplayed()) $elements['doc_field'] = $element;
//            $element = $this->driver->findElement(WebDriverBy::id('ContentPlaceHolder1_Button1'));
//            if ($element->isDisplayed() && $element->isEnabled()) $elements['continue_btn'] = $element;
//            if (!isset($elements['doc_field']) || !isset($elements['continue_btn'])) throw new Exception('Error en la busqueda de inputs, revisar que sigan funcionando');
//
//            // 7. Completar formulario con tipeo humano
//            $elements['doc_field']->click();
//            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
//            $elements['doc_field']->sendKeys(WebDriverKeys::DELETE);
//            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
//            $this->chromeSimulator->typeLikeHuman($elements['doc_field'], $per_cuit);
//
//            // 8. Click en continuar
//            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
//            $this->driver->executeScript("arguments[0].scrollIntoView(true);", [$elements['continue_btn']]);
//            $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
//            $elements['continue_btn']->click();
//
//            // 9. Esperar respuesta
//            $this->chromeSimulator->humanDelay(0.1, 0.15);
//
//            // 10. Obtener fuente de la página resultante
//            $page_source = $this->driver->getPageSource();
//
//            // 11. Validar errores
//            if (strpos($page_source, 'ERROR DE AUTENTICACION') !== false) throw new Exception('Error de autenticación en Anses', 401);
//            if (strpos($page_source, 'El CUIL ingresado no es válido') !== false) throw new Exception('El CUIL ingresado no es válido', 400);
//
//            if (
//                strpos($page_source, 'Obra Social') !== false ||
//                strpos($page_source, 'obra social') !== false
//            ) {
//                $cookies = $this->chromeSimulator->getCookies($this->driver);
//                $values = [
//                    '__VIEWSTATE' => $this->driver->findElement(WebDriverBy::id('__VIEWSTATE'))->getAttribute('value') ?? '',
//                    '__EVENTVALIDATION' => $this->driver->findElement(WebDriverBy::id('__EVENTVALIDATION'))->getAttribute('value') ?? '',
//                    '__VIEWSTATEGENERATOR' => $this->driver->findElement(WebDriverBy::id('__VIEWSTATEGENERATOR'))->getAttribute('value') ?? '',
//                    'g-recaptcha-response' => $this->driver->findElement(WebDriverBy::id('g-recaptcha-response'))->getAttribute('value') ?? '',
//                ];
//
//                $this->chromeSimulator->closeDriver();
//                if (stripos($page_source, '(PROFE)') !== false) {
//                    $values['BtnCODEM.x'] = 39;
//                    $values['BtnCODEM.y'] = 11;
//                } else {
//                    $values['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$DGOOSS$ctl02$ctl00';
//                    $values['__EVENTARGUMENT'] = '';
//                }
//
//                $response = Http::withHeaders([
//                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
//                    'Accept-Encoding' => 'gzip, deflate, br',
//                    'Accept-Language' => 'es-419,es;q=0.9',
//                    'Cache-Control' => 'max-age=0',
//                    'Content-Type' => 'application/x-www-form-urlencoded',
//                    'Cookie' => $cookies,
//                    'Origin' => 'http://servicioswww.anses.gob.ar',
//                    'Referer' => 'http://servicioswww.anses.gob.ar/ooss2/',
//                    'Sec-Ch-Ua' => '"Brave";v="117", "Not;A=Brand";v="8", "Chromium";v="117"',
//                    'Sec-Ch-Ua-Mobile' => '?0',
//                    'Sec-Ch-Ua-Platform' => '"Linux"',
//                    'Sec-Fetch-Dest' => 'document',
//                    'Sec-Fetch-Mode' => 'navigate',
//                    'Sec-Fetch-Site' => 'same-origin',
//                    'Sec-Fetch-User' => '?1',
//                    'Upgrade-Insecure-Requests' => '1',
//                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
//                ])->asForm()->post('https://servicioswww.anses.gob.ar/ooss2/ConsultaOOSS.aspx', $values);
//
//                if ($response->status() !== 200) throw new Exception('Error al realizar la consulta de descarga en CODEM Anses', $response->status());
//                if (strpos($response->body(), 'El servicio no está')) throw new Exception('Error en la respuesta de Anses', 500);
//                $base64Pdf = base64_encode($response->body());
//
//                return response()->json([
//                    'success' => true,
//                    'message' => 'Consulta realizada exitosamente',
//                    'content_step_1' => $page_source,
//                    'content_step_2' => $base64Pdf,
//                ], 200);
//            }
//
//            if (strpos($page_source, 'error') !== false || strpos($page_source, 'Error') !== false) {
//                throw new Exception('Error desconocido en la respuesta de Anses', 500);
//            }
//        } catch (Exception $e) {
//            return response()->json([
//                'success' => false,
//                'error' => 'EXCEPTION',
//                'message' => $e->getMessage(),
//                'content' => null
//            ], ($e->getCode() > 200 && $e->getCode() < 500) ? $e->getCode() : 500);
//        } finally {
//            $this->chromeSimulator->closeDriver();
//        }
//    }
//}

namespace App\Http\Controllers\Anses;

use App\Http\Controllers\Controller;
use App\Models\ChromeSimulator;
use Exception;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Illuminate\Support\Facades\Http;

class LaboralController extends Controller
{
    private ChromeSimulator $chromeSimulator;

    public function __construct()
    {
        $this->chromeSimulator = new ChromeSimulator();
    }

    public function VL_CO($per_cuit)
    {
        $human_speed = ['min' => 0.0, 'max' => 0.01];

        try {
            if (empty($per_cuit)) throw new Exception('Debe ingresar un CUIL', 400);

            $result = $this->chromeSimulator->run(function($driver) use ($per_cuit, $human_speed) {
                $driver->get('https://servicioswww.anses.gob.ar/ooss2/');

                $driver->executeScript("
                    Object.defineProperty(navigator, 'webdriver', {
                        get: () => undefined,
                    });
                    delete navigator.__webdriver_script_fn;
                    window.chrome = { runtime: {} };
                ");

                $driver->wait(0.15)->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(
                        WebDriverBy::tagName('form')
                    )
                );

                $driver->executeScript("window.scrollTo(0, 200);");
                $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);

                $elements = [];
                $element = $driver->findElement(WebDriverBy::id('ContentPlaceHolder1_txtDoc'));
                if ($element->isDisplayed()) $elements['doc_field'] = $element;
                $element = $driver->findElement(WebDriverBy::id('ContentPlaceHolder1_Button1'));
                if ($element->isDisplayed() && $element->isEnabled()) $elements['continue_btn'] = $element;
                if (!isset($elements['doc_field']) || !isset($elements['continue_btn'])) throw new Exception('Error en la busqueda de inputs');

                $elements['doc_field']->click();
                $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
                $elements['doc_field']->sendKeys(WebDriverKeys::DELETE);
                $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
                $this->chromeSimulator->typeLikeHuman($elements['doc_field'], $per_cuit);

                $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
                $driver->executeScript("arguments[0].scrollIntoView(true);", [$elements['continue_btn']]);
                $this->chromeSimulator->humanDelay($human_speed['min'], $human_speed['max']);
                $elements['continue_btn']->click();

                $this->chromeSimulator->humanDelay(0.1, 0.15);

                $page_source = $driver->getPageSource();

                if (strpos($page_source, 'ERROR DE AUTENTICACION') !== false) throw new Exception('Error de autenticación en Anses', 401);
                if (strpos($page_source, 'El CUIL ingresado no es válido') !== false) throw new Exception('El CUIL ingresado no es válido', 400);

                if (
                    strpos($page_source, 'Obra Social') !== false ||
                    strpos($page_source, 'obra social') !== false
                ) {
                    $cookies = $this->chromeSimulator->getCookies($driver);
                    $values = [
                        '__VIEWSTATE' => $driver->findElement(WebDriverBy::id('__VIEWSTATE'))->getAttribute('value') ?? '',
                        '__EVENTVALIDATION' => $driver->findElement(WebDriverBy::id('__EVENTVALIDATION'))->getAttribute('value') ?? '',
                        '__VIEWSTATEGENERATOR' => $driver->findElement(WebDriverBy::id('__VIEWSTATEGENERATOR'))->getAttribute('value') ?? '',
                        'g-recaptcha-response' => $driver->findElement(WebDriverBy::id('g-recaptcha-response'))->getAttribute('value') ?? '',
                    ];

                    if (stripos($page_source, '(PROFE)') !== false) {
                        $values['BtnCODEM.x'] = 39;
                        $values['BtnCODEM.y'] = 11;
                    } else {
                        $values['__EVENTTARGET'] = 'ctl00$ContentPlaceHolder1$DGOOSS$ctl02$ctl00';
                        $values['__EVENTARGUMENT'] = '';
                    }

                    $response = Http::withHeaders([
                        // Tus headers acá
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Cookie' => $cookies,
                        'Origin' => 'http://servicioswww.anses.gob.ar',
                        'Referer' => 'http://servicioswww.anses.gob.ar/ooss2/',
                        'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
                    ])->asForm()->post('https://servicioswww.anses.gob.ar/ooss2/ConsultaOOSS.aspx', $values);

                    if ($response->status() !== 200) throw new Exception('Error al realizar la consulta', $response->status());
                    if (strpos($response->body(), 'El servicio no está')) throw new Exception('Error en la respuesta de Anses', 500);
                    $base64Pdf = base64_encode($response->body());

                    return response()->json([
                        'success' => true,
                        'message' => 'Consulta realizada exitosamente',
                        'content_step_1' => $page_source,
                        'content_step_2' => $base64Pdf,
                    ], 200);
                }

                if (stripos($page_source, 'error') !== false) {
                    throw new Exception('Error desconocido en la respuesta de Anses', 500);
                }
            });

            return $result;
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'EXCEPTION',
                'message' => $e->getMessage(),
                'content' => null
            ], ($e->getCode() > 200 && $e->getCode() < 500) ? $e->getCode() : 500);
        }
    }
}
