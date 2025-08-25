<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChromeSimulator;
use Exception;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use HttpRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BCRAController extends Controller
{
    private string $cuit;
    private string $selenium_url;
    private ChromeSimulator $chromeSimulator;

    public function __construct()
    {
        $this->selenium_url = config('app.selenium_url');
        $this->chromeSimulator = new ChromeSimulator();

    }

    public function __invoke($cuit)
    {
        try {

            if (strlen($cuit) !== 11) throw new Exception('El cuit no es valido. Debe ser igual a 11 digitos.', 400);
            $this->cuit = $cuit;

//            $debts = $this->getDebts();
//            $bouncedChecks = $this->getBouncedChecks();
//            $historicalDebts = $this->getHistoricalDebts();
//
//            return response()->json([
//                'success' => true,
//                'deudas' => $debts,
//                'deudas_historicas' => $historicalDebts,
//                'cheques_rechazados' => $bouncedChecks,
//            ]);
            $html = file_get_contents(public_path('consulta.html'));
            $html = mb_convert_encoding($html, 'UTF-8', 'auto');

            return response()->json([
                'success' => true,
                'data' => $html,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'EXCEPTION',
                'message' => $e->getMessage(),
                'content' => null
            ], $e->getCode() > 200 && $e->getCode() < 500 ?: 500);
        }
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function getDebts() : array {
        $response = Http::withOptions(['verify' => false])->get("https://api.bcra.gob.ar/centraldedeudores/v1.0/Deudas/$this->cuit");
        return $this->returnData($response);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function getBouncedChecks() : array {
        $response = Http::withOptions(['verify' => false])->get("https://api.bcra.gob.ar/centraldedeudores/v1.0/Deudas/ChequesRechazados/$this->cuit");
        return $this->returnData($response);
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function getHistoricalDebts() : array {
        $response = Http::withOptions(['verify' => false])->get("https://api.bcra.gob.ar/centraldedeudores/v1.0/Deudas/Historicas/$this->cuit");
        return $this->returnData($response);
    }
    /**
     * @throws Exception
     */
    private function returnData($response) : array {
        if ($response->status() === 500) throw new \Exception("Error al consumir API de BCRA");
        $response_json = $response->json();
        if (isset($response_json['results'])) return $response_json['results'];
        if (isset($response_json['errorMessages'])) return [];
    }
}
