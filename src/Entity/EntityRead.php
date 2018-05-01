<?php

namespace Entity;


use ConnCrud\Read;
use ConnCrud\TableCrud;
use EntityForm\Dicionario;
use EntityForm\Metadados;
use Helpers\Check;
use Helpers\Helper;

abstract class EntityRead extends EntityCopy
{

    /**
     * Le a data de uma entidade de forma extendida
     *
     * @param string $entity
     * @param mixed $data
     * @return mixed
     */
    protected static function exeRead(string $entity, $data = null)
    {
        if(!is_array($data) && !is_numeric($data))
            return null;

        if(is_array($data)) {
            $read = new TableCrud($entity);
            $read->loadArray($data);
            if ($read->exist())
                $data = (int)$read->getDados()['id'];

            if(empty($data) || !is_numeric($data))
                return null;
        }

        return self::readValues($entity, $data);
    }

    /**
     * @param string $entity
     * @param int $id
     */
    private static function readValues(string $entity, int $id)
    {
        $d = new Dicionario($entity);
        $d->setData($id);
        return $d->getDataFullRead();
    }

    /**
     * Verifica se precisa alterar de modo padrão a informação deste campo
     *
     * @param array $dic
     * @param mixed $value
     * @return mixed
     */
    protected static function checkDefaultSet(array $dic, $value = null)
    {
        if (!$value || empty($value)) {
            if ($dic['default'] === "") {
                return null;
            } else {
                if ($dic['default'] === "datetime")
                    return date("Y-m-d H:i:s");
                elseif ($dic['default'] === "date")
                    return date("Y-m-d");
                elseif ($dic['default'] === "time")
                    return date("H:i:s");
                else
                    return $dic['default'];
            }

        } elseif ($dic['type'] === "json" && is_array($value)) {
            $value = json_encode($value);

        } elseif ($dic['format'] === "password") {
            $value = Check::password($value);
        }

        return $value;
    }

    /**
     * @param string $entity
     * @param array $data
     * @param array $dicionario
     * @param bool $recursive
     * @return array
     */
    private static function readEntity(string $entity, array $data, array $dicionario, bool $recursive = true): array
    {
        foreach ($dicionario as $dic) {
            if ($dic['key'] === "extend" && !self::$error) {
                if ($recursive)
                    $data[$dic['column']] = self::exeRead($dic['relation'], $data[$dic['column']]);
            } elseif ($dic['key'] === "list" || $dic['key'] === "selecao") {
                if (!empty($data[$dic['column']]) && is_numeric($data[$dic['column']]) && !self::$error)
                    $data[$dic['column']] = self::exeRead($dic['relation'], $data[$dic['column']]);
                else
                    $data[$dic['column']] = null;
            } elseif ($dic['key'] === "extend_mult" || $dic['key'] === "list_mult" || $dic['key'] === "selecao_mult") {
                $data[$dic['column']] = self::readEntityMult($entity, $dic, $data['id']);
            } elseif ($dic['type'] === 'json') {
                $data[$dic['column']] = !empty($data[$dic['column']]) ? json_decode($data[$dic['column']], true) : [];
            }
        }

        return $data;
    }

    /**
     * @param string $entity
     * @param array $dic
     * @param mixed $id
     * @return mixed
     */
    private static function readEntityMult(string $entity, array $dic, $id = null)
    {
        $datas = null;
        if ($id) {
            $read = new Read();
            $read->exeRead(PRE . $entity . "_" . $dic['relation'] . "_" . $dic['column'], "WHERE " . $entity . "_id = :id", "id={$id}");
            if ($read->getResult()) {
                foreach ($read->getResult() as $item) {
                    if (!self::$error)
                        $datas[] = self::exeRead($dic['relation'], (int)$item[$dic['relation'] . "_id"]);
                }
            }
        }

        return $datas;
    }
}