<?php

namespace App\Http\Controllers;

use App\Models\ChromeSimulator;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;
use Illuminate\Http\Request;

class RegistroGraduadosController extends Controller
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
        try {

            $request->validate(
                [
                    'tipo_busqueda' => 'required|string|in:CC,CE,CI,DNI,LC,LE,OTS,PAS',
                    'numero_documento' => 'required|string|max:8|min:6',
                    'nombre' => 'string',
                    'apellido' => 'string'],
                [
                    'numero_documento.required' => 'El campo numero_documento es obligatorio',
                    'numero_documento.max' => 'El campo numero_documento no puede ser mayor a 8 caracteres',
                    'numero_documento.min' => 'El campo numero_documento no puede ser menor a 6 caracteres',
                    'tipo_busqueda.required' => 'El campo tipo_busqueda es obligatorio', 'tipo_busqueda.in' => 'El campo tipo_busqueda debe ser CC,CE,CI,DNI,LC,LE,OTS,PAS',
                    'nombre.required' => 'El campo nombre debe ser de tipo caracter', 'apellido.in' => 'El campo apellido debe ser de tipo caracter'
                ]
            );

            // 1. Configurar y crear driver
            $capabilities = $this->chromeSimulator->setupChrome();

            $this->driver = RemoteWebDriver::create($this->selenium_url, $capabilities);
            // 2. Le decimos que URL debe ir

            $this->driver->get('https://registrograduados.siu.edu.ar/');
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

            $form = $this->driver->findElement(WebDriverBy::id("cuerpo_js_form_2308_filtro"));

            $elements = array(
                ['type' => 'id', 'value' => 'ef_form_2308_filtroid_tipo_documento'],
                ['type' => 'id', 'value' => 'ef_form_2308_filtrodocumento'],
                ['type' => 'id', 'value' => 'ef_form_2308_filtroapellido'],
                ['type' => 'id', 'value' => 'ef_form_2308_filtronombre'],
                ['type' => 'id', 'value' => 'form_2308_filtro_filtrar']
            );

            $elementsFound = $this->chromeSimulator->findElements($form, $elements);

            $select = new WebDriverSelect($elementsFound['ef_form_2308_filtroid_tipo_documento']);
            $select->selectByValue($request->tipo_busqueda);
            $this->chromeSimulator->humanDelay();

            $this->chromeSimulator->typingElement($this->chromeSimulator, $elementsFound['ef_form_2308_filtrodocumento'], $request->numero_documento);
            if (!empty($request->nombre)) $this->chromeSimulator->typingElement($this->chromeSimulator, $elementsFound['ef_form_2308_filtrodocumento'], $request->nombre);
            if (!empty($request->apellido)) $this->chromeSimulator->typingElement($this->chromeSimulator, $elementsFound['ef_form_2308_filtrodocumento'], $request->apellid);

            $elementsFound['form_2308_filtro_filtrar']->click();

            $this->chromeSimulator->humanDelay(2, 5);

            $page_source = $driver->getPageSource();

            $detail = $driver->findElement(WebDriverBy::id("cuadro_2309_cuadro_sicer0_ver"));
            $page_source2 = null;
            if ($detail->isDisplayed()) {
                $detail->click();
                $this->chromeSimulator->humanDelay(2, 5);
                $page_source2 = $driver->getPageSource();
            }


            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
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
