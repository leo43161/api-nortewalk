<?php
class Database
{
    private $host;
    private $user;
    private $pass;
    private $db;
    private $link;

    public function __construct()
    {
        $this->host = constant('HOST');
        $this->user = constant('USER');
        $this->pass = constant('PASSWORD');
        $this->db   = constant('DB');
    }

    public function connect()
    {
        $this->link = mysqli_connect($this->host, $this->user, $this->pass);
        if (!$this->link) {
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "message" => "Error al conectar al servidor MySQL"
            ]);
            exit;
        }
        if (!mysqli_select_db($this->link, $this->db)) {
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "message" => "Base de datos no encontrada: " . $this->db
            ]);
            exit;
        }
        mysqli_set_charset($this->link, constant('CHARSET'));
        return $this->link;
    }
}
?>
