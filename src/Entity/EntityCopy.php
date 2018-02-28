<?php

namespace Entity;


use ConnCrud\Read;
use ConnCrud\TableCrud;
use EntityForm\Metadados;
use Helpers\Check;

abstract class EntityCopy extends EntityDelete
{
    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @return mixed
     */
    protected static function exeCopy(string $entity, $data)
    {
        $result = null;
        $dicionario = Metadados::getDicionario($entity);

        if (is_int($data)) {
            $copy = new TableCrud($entity);
            $copy->load($data);
            if ($copy->exist())
                $result = self::copyEntity($entity, $copy->getDados(), $dicionario);
            else
                self::$error[$entity]['id'] = "id: {$data} não encontrado para cópia";

        } elseif (is_array($data)) {
            if (Check::isAssoc($data)) {
                $copy = new TableCrud($entity);
                $copy->loadArray($data);
                if ($copy->exist())
                    $result = self::copyEntity($entity, $copy->getDados(), $dicionario);
                else
                    self::$error[$entity]['id'] = "datas não encontrado em " . $entity . " para cópia";

            } else {
                foreach ($data as $datum) {
                    if (!self::$error)
                        $result[] = self::exeCopy($entity, (int)$datum);
                }
            }
        }
        unset($result['id']);

        return self::$error ? null : $result;
    }

    /**
     * @param string $entity
     * @param array $dicionario
     * @param array $data
     * @return array
     */
    private static function copyEntity(string $entity, array $data, array $dicionario): array
    {
        foreach ($dicionario as $dic) {
            if ($dic['key'] === "extend" && !self::$error)
                $data[$dic['column']] = self::exeCopy($dic['relation'], (int)$data[$dic['column']]);

            elseif ($dic['key'] === "list" || $dic['key'] === "selecao")
                $data[$dic['column']] = self::copyList($dic['relation'], (int)$data[$dic['column']]);

            elseif ($dic['key'] === "extend_mult")
                $data[$dic['column']] = self::copyEntityMult($entity, $dic, $data['id']);

            elseif ($dic['key'] === "list_mult" || $dic['key'] === "selecao_mult")
                $data[$dic['column']] = self::copyListMult($entity, $dic, $data['id']);

            else
                $data[$dic['column']] = self::copyEntityData($entity, $dic, $data, $dicionario);
        }

        return $data;
    }


    /**
     * @param string $entity
     * @param array $dic
     * @param int $id
     * @return mixed
     */
    private static function copyListMult(string $entity, array $dic, int $id)
    {
        $datas = null;
        $read = new Read();
        $read->exeRead(PRE . $entity . "_" . $dic['relation'], "WHERE " . $entity . "_id = :id", "id={$id}");
        if ($read->getResult()) {
            foreach ($read->getResult() as $item) {
                $datas[] = self::copyList($dic['relation'], (int)$item[$dic['relation'] . "_id"]);
            }
        }

        return $datas;
    }

    /**
     * @param string $entity
     * @param int $id
     * @return mixed
     */
    private static function copyList(string $entity, int $id)
    {
        $copy = new TableCrud($entity);
        $copy->load($id);
        if ($copy->exist())
            return $copy->getDados();

        return null;
    }

    /**
     * @param string $entity
     * @param array $dic
     * @param array $data
     * @param array $dicionario
     * @return mixed
     */
    private static function copyEntityData(string $entity, array $dic, array $data, array $dicionario)
    {
        if ($dic['unique']) {
            $data[$dic['column']] = rand(0, 999999) . "-" . $data[$dic['column']];
            $read = new TableCrud(PRE . $entity);
            $read->loadArray([$dic['column'] => $data[$dic['column']]]);
            if ($read->exist())
                $data[$dic['column']] = rand(0, 999999) . "--" . $data[$dic['column']];
        }

        if ($dic['key'] === "link")
            $data[$dic['column']] = Check::name($data[$dicionario[Metadados::getInfo($entity)['title']]['column']]);

        return $data[$dic['column']];
    }

    /**
     * @param string $entity
     * @param array $dic
     * @param int $id
     * @return mixed
     */
    private static function copyEntityMult(string $entity, array $dic, int $id)
    {
        $datas = null;
        $read = new Read();
        $read->exeRead(PRE . $entity . "_" . $dic['relation'], "WHERE " . $entity . "_id = :id", "id={$id}");
        if ($read->getResult()) {
            foreach ($read->getResult() as $item) {
                if (!self::$error)
                    $datas[] = self::exeCopy($dic['relation'], (int)$item[$dic['relation'] . "_id"]);
            }
        }

        return $datas;
    }
}