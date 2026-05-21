<?php

class App
{
    function __construct()
    {
        $url = isset($_GET['url']) ? $_GET['url'] : null;
        $url = rtrim($url, '/');
        $url = explode('/', $url);

        if (empty($url[0])) {
            header('Content-Type: application/json');
            echo json_encode([
                "status" => 200,
                "message" => "Norte Walk API",
                "version" => "1.0.0"
            ]);
            return;
        }

        $archivoController = 'controllers/' . $url[0] . '.php';

        if (file_exists($archivoController)) {
            require_once $archivoController;

            $controller = new $url[0];
            $controller->loadModel($url[0]);

            if (isset($url[1])) {
                if (method_exists($controller, $url[1])) {
                    $param = array_slice($url, 2);
                    $controller->{$url[1]}($param);
                } else {
                    $this->sendError(404, "Metodo no encontrado: " . $url[1]);
                }
            } else {
                if (method_exists($controller, 'render')) {
                    $controller->render();
                } else {
                    $this->sendError(404, "Endpoint no valido");
                }
            }
        } else {
            $this->sendError(404, "Controlador no encontrado");
        }
    }

    private function sendError($code, $message)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(["status" => $code, "message" => $message]);
    }
}
?>
