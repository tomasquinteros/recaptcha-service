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
                /*$print_button = null;
                $print_selectors = [
                    // Selector específico del botón de imprimir
                    ['xpath', "//a[contains(@href, \"__doPostBack('ctl00\$ContentPlaceHolder1\$DGOOSS\$ctl02\$ctl00',''))\"]"],
                    ['xpath', "//a[contains(@href, '__doPostBack') and contains(@href, 'DGOOSS')]"],
                    ['xpath', "//img[@src='App_Themes/Imagenes/imprimir2.gif']/parent::a"],
                    ['css', "a[href*='__doPostBack'][href*='DGOOSS']"],
                    ['xpath', "//td[@align='center']//a[contains(@href, '__doPostBack')]"],
                    ['xpath', "//img[contains(@src, 'imprimir')]/parent::a"]
                ];

                foreach ($print_selectors as $selector) {
                    try {
                        $method = $selector[0];
                        $value = $selector[1];

                        switch ($method) {
                            case 'xpath':
                                $print_button = $this->driver->findElement(WebDriverBy::xpath($value));
                                break;
                            case 'css':
                                $print_button = $this->driver->findElement(WebDriverBy::cssSelector($value));
                                break;
                        }

                        if ($print_button && $print_button->isDisplayed()) {
                            break;
                        }
                    } catch (Exception $e) {
                        continue;
                    }
                }
                // Si encuentra datos sigue para descargar el archivo.
                if ($print_button) {
                    try {
                        $this->cleanDownloadDirectory();
                        $this->driver->executeScript("arguments[0].scrollIntoView(true);", [$print_button]);
                        $this->chromeSimulator->humanDelay(0.5, 1);
                        $print_button->click();
                        $this->chromeSimulator->humanDelay(2, 4);
                        $downloaded_file = $this->waitForDownload(10);
                        if ($downloaded_file) {
                            try {
                                // Leer el contenido del archivo
                                $file_content = file_get_contents($downloaded_file);
                                $file_name = basename($downloaded_file);
                                $file_size = filesize($downloaded_file);

                                // Opcional: Convertir a base64 para envío
                                $file_base64 = base64_encode($file_content);
                                dd($file_base64);
                                // Eliminar el archivo después de leerlo
                                unlink($downloaded_file);

                                return response()->json([
                                    'success' => true,
                                    'message' => 'Consulta realizada exitosamente y archivo descargado',
                                    'content' => $page_source,
                                    'url' => $current_url,
                                    'download' => [
                                        'file_name' => $file_name,
                                        'file_size' => $file_size,
                                        'file_content_base64' => $file_base64,
                                        'file_deleted' => true
                                    ]
                                ], 200);

                            } catch (Exception $file_error) {
                                // Si hay error leyendo el archivo, intentar eliminarlo de todas formas
                                if (file_exists($downloaded_file)) {
                                    unlink($downloaded_file);
                                }

                                return response()->json([
                                    'success' => false,
                                    'error' => 'FILE_READ_ERROR',
                                    'message' => 'Error al leer el archivo descargado: ' . $file_error->getMessage(),
                                    'content' => $page_source,
                                    'url' => $current_url
                                ], 500);
                            }

                        } else {
                            return response()->json([
                                'success' => true,
                                'message' => 'Consulta realizada exitosamente pero no se pudo descargar el archivo',
                                'content' => $page_source,
                                'url' => $current_url,
                                'download' => null
                            ], 200);
                        }
                    } catch (Exception $e) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Consulta realizada exitosamente pero error en descarga: ' . $e->getMessage(),
                            'content' => $page_source,
                            'url' => $current_url,
                            'download' => null
                        ], 200);
                    }

                } else {
                    return response()->json([
                        'success' => true,
                        'message' => 'Consulta realizada exitosamente pero no se encontró el botón de imprimir',
                        'content' => $page_source,
                        'url' => $current_url,
                        'download' => null
                    ], 200);
                }*/
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
}
