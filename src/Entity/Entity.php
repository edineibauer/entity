<?php

namespace Entity;

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
     * @return mixed
     */
    public static function add(string $entity, array $data)
    {
        return self::exeCreate($entity, $data);
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     */
    public static function delete(string $entity, $data)
    {
        self::exeDelete($entity, $data);
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @return mixed
     */
    public static function copy(string $entity, $data)
    {
        return self::exeCopy($entity, $data);
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