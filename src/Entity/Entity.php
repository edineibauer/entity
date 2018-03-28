<?php

namespace Entity;

use ConnCrud\Read;
use EntityForm\Metadados;

class Entity extends EntityCreate
{
    /**
     * Le a data de uma entidade de forma extendida
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $recursive
     * @return mixed
     */
    public static function read(string $entity, $data = null, bool $recursive = true)
    {
        return self::exeRead($entity, $data, $recursive);
    }

    /**
     * Salva data à uma entidade
     *
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @param mixed $callback
     * @return mixed
     */
    public static function add(string $entity, array $data, bool $save = true)
    {
        return self::exeCreate($entity, $data, $save);
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $checkPermission
     */
    public static function delete(string $entity, $data, bool $checkPermission = false)
    {
        self::exeDelete($entity, $data, $checkPermission);
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $checkPermission
     * @return mixed
     */
    public static function copy(string $entity, $data, bool $checkPermission = false)
    {
        return self::exeCopy($entity, $data, $checkPermission);
    }

    /**
     * @param string $entity
     * @param int $id
     * @param bool $check
     * @return bool
    */
    public static function checkPermission(string $entity, int $id, bool $check = false)
    {
        if(!$check)
            return true;

        $info = Metadados::getInfo($entity);

        if($entity !== "login" && empty($info['publisher']))
            return true;

        if(empty($_SESSION['userlogin']))
            return false;

        $read = new Read();
        $read->exeRead(PRE . $entity, "WHERE id = :id", "id={$id}");
        if($read->getResult()) {
            $dados = $read->getResult()[0];

            if ($entity !== "login") {
                $metadados = Metadados::getDicionario($entity);
                if ($_SESSION['userlogin']['id'] == $dados[$metadados[$info['publisher']]['column']])
                    return true;

            } else {
                if ($_SESSION['userlogin']['setor'] < $dados['setor'])
                    return true;

                if ($_SESSION['userlogin']['setor'] == $dados['setor'] && $_SESSION['userlogin']['nivel'] < $dados['nivel'])
                    return true;
            }
        }

        return false;
    }

    /**
     * @param array $data
     * @param array $info
     * @param array $dicionario
     * @return array
    private static function splitRelation(array $data, array $info, array $dicionario): array
    {
    $relation = null;

    foreach (["extend_mult", "list_mult"] as $e) {
    if ($info[$e]) {
    foreach ($info[$e] as $i) {
    $relation[$dicionario[$i]['relation']] = $data[$dicionario[$i]['column']];
    unset($data[$dicionario[$i]['column']]);
    }
    }
    }

    return ["relation" => $relation, "data" => $data];
    }
     */

}