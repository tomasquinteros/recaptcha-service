<?php

namespace App\Http\Controllers;

use App\Models\ChromeSimulator;
use Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverKeys;
use Log;

class RecaptchaAnsesController extends Controller
{
    private $driver;
    private string $selenium_url;
    private ChromeSimulator $chromeSimulator;
    private string $downloadDir;

    public function __construct()
    {
        $this->selenium_url = config('app.selenium_url');
        $this->chromeSimulator = new ChromeSimulator();
        $this->downloadDir = storage_path('app/tmp/anses_downloads');

        if (!file_exists($this->downloadDir)) {
            mkdir($this->downloadDir, 0755, true);
        }
    }

    public function VL_CO($per_cuit)
    {
        if ($per_cuit == '') {
            return response()->json([
                'success' => false,
                'error' => 'BAD REQUEST',
                'message' => 'Debe ingresar un CUIL',
                'content' => null
            ], 400);
        }

        try {
            /*$prefsCustom = [
                'download.default_directory' => $this->downloadDir,
                'download.prompt_for_download' => false,
                'download.directory_upgrade' => true,
                'safebrowsing.enabled' => true
            ];*/
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
            $this->driver->wait(5)->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::tagName('form')
                )
            );
            // 5. Simular scroll y movimiento
            $this->driver->executeScript("window.scrollTo(0, 200);");
            $this->chromeSimulator->humanDelay(0.2, 0.8);

            // 6. Buscamos elementos del formulario a simular
            $elements = array();
            $doc_selectors = array(
                ['id', 'ContentPlaceHolder1_txtDoc'],
                ['name', 'ctl00$ContentPlaceHolder1$txtDoc'],
                ['xpath', '//input[@type="text" and contains(@name, "txtDoc")]'],
                ['xpath', '//input[@placeholder="" and @type="text"]'],
                ['css', 'input[type="text"][id*="txtDoc"]'],
                ['xpath', '//input[@onkeypress="javascript:return solonumeros(event)"]']
            );
            foreach ($doc_selectors as $selector) {
                try {
                    $method = $selector[0];
                    $value = $selector[1];
                    switch ($method) {
                        case 'id':
                            $element = $this->driver->findElement(WebDriverBy::id($value));
                            break;
                        case 'name':
                            $element = $this->driver->findElement(WebDriverBy::name($value));
                            break;
                        case 'xpath':
                            $element = $this->driver->findElement(WebDriverBy::xpath($value));
                            break;
                        case 'css':
                            $element = $this->driver->findElement(WebDriverBy::cssSelector($value));
                            break;
                    }

                    if ($element->isDisplayed()) {
                        $elements['doc_field'] = $element;
                        break;
                    }
                } catch (Exception $e) {
                    continue; // Probar siguiente selector
                }
            }
            // 6.1. Buscamos el boton continuar del formulario a simular
            $button_selectors = [
                ['id', 'ContentPlaceHolder1_Button1'],
                ['xpath', '//input[@value="Continuar"]'],
                ['xpath', '//input[@type="submit" and contains(@name, "Button1")]'],
                ['css', 'input[value="Continuar"]'],
                ['xpath', '//input[@class="btn btn-default"]']
            ];
            foreach ($button_selectors as $selector) {
                try {
                    $method = $selector[0];
                    $value = $selector[1];

                    switch ($method) {
                        case 'id':
                            $element = $this->driver->findElement(WebDriverBy::id($value));
                            break;
                        case 'xpath':
                            $element = $this->driver->findElement(WebDriverBy::xpath($value));
                            break;
                        case 'css':
                            $element = $this->driver->findElement(WebDriverBy::cssSelector($value));
                            break;
                    }

                    if ($element->isDisplayed() && $element->isEnabled()) {
                        $elements['continue_btn'] = $element;
                        break;
                    }
                } catch (Exception $e) {
                    continue;
                }
            }

            if (!isset($elements['doc_field'])) {
                throw new Exception("No se pudo encontrar el campo de documento");
            }

            if (!isset($elements['continue_btn'])) {
                throw new Exception("No se pudo encontrar el botón continuar");
            }

            // 7. Llenar formulario como humano
            // Click en el campo
            $elements['doc_field']->click();
            $this->chromeSimulator->humanDelay(0.2, 0.5);
            // 7.1. Limpiar campo
            $elements['doc_field']->sendKeys(WebDriverKeys::CONTROL . 'a');
            $elements['doc_field']->sendKeys(WebDriverKeys::DELETE);
            $this->chromeSimulator->humanDelay(0.1, 0.5);
            // 7.2. Escribir CUIL como humano
            $this->chromeSimulator->typeLikeHuman($elements['doc_field'], $per_cuit);
            $this->chromeSimulator->humanDelay(0.25, 0.5);
            // 8. Esperar reCAPTCHA v3 (se ejecuta automáticamente)
            $this->chromeSimulator->humanDelay(1, 1.5);
            // 9. Hacer click en continuar
            $this->driver->executeScript("arguments[0].scrollIntoView(true);", [$elements['continue_btn']]);
            $this->chromeSimulator->humanDelay(0.25, 0.5);
            $elements['continue_btn']->click();
            // 10. Esperar respuesta
            $this->chromeSimulator->humanDelay(1, 3);

            // 11. Capturar resultado
            $page_source = $this->driver->getPageSource();
            $current_url = $this->driver->getCurrentURL();
            $data = array();
            $cookies = $this->driver->manage()->getCookies();
            $cookieStrings = array();
            foreach ($cookies as $cookie) {
                $cookieStrings[] = $cookie['name'] . '=' . $cookie['value'];
            }
            $data['cookies'] = implode('; ', $cookieStrings);
            $data['recaptcha'] = $this->driver->findElement(WebDriverBy::name('g-recaptcha-response'))->getAttribute('value');
            $data['__VIEWSTATE'] = $this->driver->findElement(WebDriverBy::id('__VIEWSTATE'))->getAttribute('value') ?? '';
            $data['__EVENTTARGET'] = $this->driver->findElement(WebDriverBy::id('__EVENTTARGET'))->getAttribute('value') ?? 'ctl00$ContentPlaceHolder1$DGOOSS$ctl02$ctl00';
            $data['__EVENTARGUMENT'] = $this->driver->findElement(WebDriverBy::id('__EVENTARGUMENT'))->getAttribute('value') ?? '';
            $data['__VIEWSTATEGENERATOR'] = $this->driver->findElement(WebDriverBy::id('__VIEWSTATEGENERATOR'))->getAttribute('value') ?? '';
            $data['__EVENTVALIDATION'] = $this->driver->findElement(WebDriverBy::id('__EVENTVALIDATION'))->getAttribute('value') ?? '';
            // 12. Analizar resultado
            if (strpos($page_source, 'ERROR DE AUTENTICACION') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'AUTH_ERROR',
                    'message' => 'Error de autenticación en ANSES',
                    'content' => $page_source,
                    'url' => $current_url
                ], 401);
            }
            if (strpos($page_source, 'El CUIL ingresado no es válido') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'ERROR_CUIL',
                    'message' => 'El CUIL ingresado no es válido.',
                    'content' => $page_source,
                    'url' => $current_url
                ], 500);
            }
            if (strpos($page_source, 'Obra Social') !== false ||
                strpos($page_source, 'obra social') !== false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Consulta realizada exitosamente',
                    'content' => $page_source,
                    'meta_data' => $data,
                    'url' => $current_url,
                    'download' => null
                ], 200);

            }

            if (strpos($page_source, 'error') !== false ||
                strpos($page_source, 'Error') !== false) {
                return response()->json([
                    'success' => false,
                    'error' => 'UNKNOWN_ERROR',
                    'message' => 'Error desconocido en la respuesta',
                    'content' => $page_source,
                    'url' => $current_url
                ], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'EXCEPTION',
                'message' => $e->getMessage(),
                'content' => null
            ], 500);
        } finally {
            if (isset($this->driver)) {
                $this->driver->quit();
            }
        }
    }

    function informe_seccion_VL_CO_ANSESAUTOMATIZATION_procesar_CODEM(string $per_cuit, array $params)
    {
        $url = 'http://servicioswww.anses.gob.ar/ooss2/ConsultaOOSS.aspx';
        $postFields = http_build_query([
            'g-recaptcha-response' => $params['recaptcha'],
            '__VIEWSTATE' => $params['__VIEWSTATE'],
            '__EVENTVALIDATION' => $params['__EVENTVALIDATION'],
            '__VIEWSTATEGENERATOR' => $params['__VIEWSTATEGENERATOR'],
            '__EVENTTARGET' => 'ctl00$ContentPlaceHolder1$DGOOSS$ctl02$ctl00',
            '__EVENTARGUMENT' => $params['__EVENTARGUMENT'],
            'txtCUIT' => $per_cuit,
            'btnContinuar' => 'Continuar'
        ]);

        $headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'Accept-Language: es-419,es;q=0.9',
            'Cache-Control: max-age=0',
            'Content-Type: application/x-www-form-urlencoded',
            'Origin: http://servicioswww.anses.gob.ar',
            'Referer: http://servicioswww.anses.gob.ar/ooss2/',
            'Sec-Ch-Ua: "Brave";v="117", "Not;A=Brand";v="8", "Chromium";v="117"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Linux"',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-User: ?1',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36',
            'Cookie: ' . $params['cookies'],
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
        ]);

        $response = curl_exec($ch);
        $this->driver->quit();
        curl_close($ch);
        dd($response);
        return $response;
    }
}
