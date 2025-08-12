<?php

namespace App\Models;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;

class ChromeSimulator
{
    private $process;
    private $port;
    private $profileDir;

    /**
     * Ejecuta una sesión con RemoteWebDriver gestionada automáticamente.
     *
     * @param callable $callback Función que recibe RemoteWebDriver y devuelve resultado.
     * @param array $prefsCustom Opciones personalizadas para Chrome.
     * @param int $maxRetries Cantidad máxima de reintentos para iniciar el driver.
     * @return mixed Resultado devuelto por el callback.
     * @throws \Exception
     */
    public function run(callable $callback, array $prefsCustom = [], int $maxRetries = 3)
    {
        $attempt = 0;

        do {
            $this->port = $this->getFreePort();
            $this->profileDir = sys_get_temp_dir() . '/chrome-profile-' . uniqid();
            mkdir($this->profileDir);

            $chromeDriverBinary = '/usr/local/bin/chromedriver';

            // Levantar proceso ChromeDriver
            $this->process = proc_open(
                "$chromeDriverBinary --port=$this->port --disable-logging --log-level=OFF",
                [],
                $pipes
            );

            if (!is_resource($this->process)) {
                throw new \Exception("No se pudo iniciar ChromeDriver en puerto $this->port");
            }

            // Esperar a que el puerto esté disponible (driver listo)
            $ready = $this->waitForPort($this->port, 3000); // 3 segundos
            if (!$ready) {
                $this->closeProcess();
                $attempt++;
                continue; // Reintentar
            }

            $capabilities = $this->setupChrome($prefsCustom);

            try {
                $driver = RemoteWebDriver::create("http://localhost:$this->port", $capabilities);
                $result = $callback($driver);
                $driver->quit();
                $this->closeProcess();
                $this->deleteDirectory($this->profileDir);

                return $result;
            } catch (\Exception $e) {
                if (isset($driver)) {
                    try {
                        $driver->quit();
                    } catch (\Exception $ignore) {}
                }
                $this->closeProcess();
                $this->deleteDirectory($this->profileDir);
                throw $e;
            }
        } while (++$attempt <= $maxRetries);

        throw new \Exception('No se pudo iniciar ChromeDriver después de ' . $maxRetries . ' intentos');
    }

    private function getFreePort(): int
    {
        for ($i = 0; $i < 10; $i++) {
            $port = rand(9515, 9599);
            if ($this->isPortFree($port)) {
                return $port;
            }
        }
        throw new \Exception('No se encontró puerto libre para ChromeDriver');
    }

    private function isPortFree(int $port): bool
    {
        $connection = @fsockopen('localhost', $port);
        if (is_resource($connection)) {
            fclose($connection);
            return false; // puerto ocupado
        }
        return true; // puerto libre
    }

    private function waitForPort(int $port, int $timeoutMillis): bool
    {
        $start = microtime(true);
        while ((microtime(true) - $start) * 1000 < $timeoutMillis) {
            if (!$this->isPortFree($port)) {
                return true;
            }
            usleep(100_000);
        }
        return false;
    }

    private function closeProcess(): void
    {
        if ($this->process && is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }
    }

    public function setupChrome(array $prefsCustom = []): DesiredCapabilities
    {
        $options = new ChromeOptions();
        $options->addArguments([
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--disable-web-security',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            '--window-size=640,480',
            '--start-maximized',
            '--user-data-dir=' . $this->profileDir,
            '--headless=new'
        ]);

        $prefs = [
            'profile.default_content_setting_values.notifications' => 2,
            'profile.managed_default_content_settings.images' => 2,
        ];

        if (!empty($prefsCustom)) {
            $prefs = array_merge($prefs, $prefsCustom);
        }

        $options->setExperimentalOption('prefs', $prefs);
        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        return $capabilities;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!file_exists($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file) : unlink($file);
        }
        rmdir($dir);
    }

    public function humanDelay(float $min_seconds, float $max_seconds): void
    {
        usleep(rand($min_seconds * 1_000_000, $max_seconds * 1_000_000));
    }

    public function typeLikeHuman($element, string $text): void
    {
        foreach (str_split($text) as $char) {
            $element->sendKeys($char);
            usleep(rand(10_000, 15_000));
        }
    }

    public function getCookies(RemoteWebDriver $driver): string
    {
        $cookies = $driver->manage()->getCookies();
        $cookieStrings = [];
        foreach ($cookies as $cookie) {
            $cookieStrings[] = $cookie['name'] . '=' . $cookie['value'];
        }
        return implode('; ', $cookieStrings);
    }
}
