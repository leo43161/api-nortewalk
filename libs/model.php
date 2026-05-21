<?php
class Model
{
    public $db;

    function __construct()
    {
        $this->db = new Database();
    }

    /**
     * Helper interno: ejecuta un CALL y devuelve la primera fila o false.
     */
    protected function callOne($conexion, $sql)
    {
        $resultado = mysqli_query($conexion, $sql);
        if (!$resultado) return false;
        $row = mysqli_fetch_assoc($resultado);
        mysqli_free_result($resultado);
        $this->drainResults($conexion);
        return $row;
    }

    /**
     * Helper interno: ejecuta un CALL y devuelve todas las filas como array.
     */
    protected function callAll($conexion, $sql)
    {
        $resultado = mysqli_query($conexion, $sql);
        if (!$resultado) return [];
        $rows = [];
        while ($row = mysqli_fetch_assoc($resultado)) {
            $rows[] = $row;
        }
        mysqli_free_result($resultado);
        $this->drainResults($conexion);
        return $rows;
    }

    /**
     * Ejecuta un CALL que devuelve N result sets (ej: lista + total).
     * Devuelve array de arrays.
     */
    protected function callMulti($conexion, $sql)
    {
        if (!mysqli_multi_query($conexion, $sql)) return [];
        $out = [];
        do {
            if ($res = mysqli_store_result($conexion)) {
                $set = [];
                while ($row = mysqli_fetch_assoc($res)) {
                    $set[] = $row;
                }
                $out[] = $set;
                mysqli_free_result($res);
            }
        } while (mysqli_more_results($conexion) && mysqli_next_result($conexion));
        return $out;
    }

    /**
     * Limpia resultados pendientes de un CALL para evitar "Commands out of sync".
     */
    protected function drainResults($conexion)
    {
        while (mysqli_more_results($conexion) && mysqli_next_result($conexion)) {
            if ($res = mysqli_store_result($conexion)) {
                mysqli_free_result($res);
            }
        }
    }

    /**
     * Escapa valor para CALL. Si es null devuelve NULL literal.
     */
    protected function esc($conexion, $val)
    {
        if ($val === null || $val === '') return 'NULL';
        return "'" . mysqli_real_escape_string($conexion, $val) . "'";
    }

    /**
     * Escapa numero. Si es null devuelve NULL literal.
     */
    protected function escNum($val)
    {
        if ($val === null || $val === '') return 'NULL';
        return (float) $val;
    }
}
?>
