# INSTRUCCIONES DE USO:

## 1. CON DOCKER:
   - Crear docker-compose.yml con el contenido de arriba
   - docker-compose up -d
   - composer require php-webdriver/webdriver
   - php anses_automation.php

## 2. SIN DOCKER (local):
   - Instalar Chrome y ChromeDriver
   - composer require php-webdriver/webdriver
   - Ejecutar ChromeDriver: ./chromedriver --port=4444
   - php anses_automation.php

## 3. VER EL NAVEGADOR EN ACCIÓN:
   - Ir a http://localhost:7900 (password: secret)

## 4. PERSONALIZAR:
   - Cambiar $cuil por el que quieras consultar
   - Modificar $selenium_url si usás otro setup
   - debug = true para ver todos los elementos

