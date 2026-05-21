<?php
class Controller
{
    public $model;

    function __construct() {}

    function loadModel($controllerName)
    {
        $url = 'models/' . $controllerName . 'Model.php';
        if (file_exists($url)) {
            require $url;
            $modelName = $controllerName . 'Model';
            $this->model = new $modelName();
        }
    }
}
?>
