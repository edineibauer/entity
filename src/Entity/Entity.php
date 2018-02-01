<?php

namespace Entity;

use ConnCrud\Create;
use ConnCrud\Read;
use ConnCrud\TableCrud;
use ConnCrud\Update;
use EntityForm\Metadados;
use Helpers\Check;

class Entity
{
    private static $error;

    /**
     * Retorna o erro acumulado na Entidade
     *
     * @return mixed
     */
    public static function getError()
    {
        return self::$error;
    }

    /**
     * Le a data de uma entidade de forma extendida
     *
     * @param string $entity
     * @param mixed $data
     * @return mixed
     */
    public static function read(string $entity, $data = null)
    {
        $dicionario = Metadados::getDicionario($entity);
        $result = null;

        if (!$data) {
            foreach ($dicionario as $dic) {
                if (in_array($dic['key'], ["extend", "list"]) && !self::$error)
                    $result[$dic['column']] = self::read($dic['relation']);
                elseif ($dic['key'] === "extend_mult")
                    $result[$dic['column']] = self::readEntityMult($entity, $dic);
                else
                    $result[$dic['column']] = self::checkDefaultSet($dic);
            }

        } elseif (is_numeric($data)) {
            $copy = new TableCrud($entity);
            $copy->load($data);
            if ($copy->exist())
                $result = self::readEntity($copy->getDados(), $dicionario);
            else
                self::$error[$entity]['id'] = "id: {$data} não encontrado para leitura";

        } elseif (is_array($data)) {
            if (Check::isAssoc($data)) {
                $copy = new TableCrud($entity);
                $copy->loadArray($data);
                if ($copy->exist())
                    $result = self::readEntity($copy->getDados(), $dicionario);
                else
                    self::$error[$entity]['id'] = "datas não encontrado em " . $entity . " para leitura";

            } else {
                foreach ($data as $datum) {
                    if (!self::$error)
                        $result[] = self::read($entity, (int)$datum);
                }
            }
        }

        return self::$error ? null : $result;
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
        self::$error = null;

        if (!Check::isAssoc($data)) {
            $result = [];
            foreach ($data as $datum)
                $result[] = self::addData($entity, $datum);

            return $result;
        } else {

            return self::addData($entity, $data);
        }
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     */
    public static function delete(string $entity, $data)
    {
        if (is_int($data)) {
            $del = new TableCrud($entity);
            $del->load($data);
            if ($del->exist()) {
                self::deleteLinkedContent($entity, $del->getDados());
                $del->delete();
            } else {
                self::$error[$entity]['id'] = "id não encontrado para deletar";
            }
        } elseif (is_array($data)) {
            if (Check::isAssoc($data)) {
                $del = new TableCrud($entity);
                $del->loadArray($data);
                if ($del->exist()) {
                    self::deleteLinkedContent($entity, $del->getDados());
                    $del->delete();
                } else {
                    self::$error[$entity]['id'] = "data não encontrada para deletar";
                }
            } else {
                foreach ($data as $datum)
                    self::delete($entity, $datum);
            }
        }
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
                        $result[] = self::copy($entity, (int)$datum);
                }
            }
        }
        unset($result['id']);

        return self::$error ? null : $result;
    }

    /**
     * @param array $data
     * @param array $dicionario
     * @return array
     */
    private static function readEntity(array $data, array $dicionario): array
    {
        foreach ($dicionario as $dic) {
            if (in_array($dic['key'], ["extend", "list"]) && !self::$error)
                $data[$dic['column']] = self::read($dic['relation'], (int)$data[$dic['column']]);

            elseif ($dic['key'] === "extend_mult")
                $data[$dic['column']] = self::readEntityMult($dic, $data['id']);
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
            $read->exeRead(PRE . $entity . "_" . $dic['relation'], "WHERE " . $entity . "_id = :id", "id={$id}");
            if ($read->getResult()) {
                foreach ($read->getResult() as $item) {
                    if (!self::$error)
                        $datas[] = self::read($dic['relation'], (int)$item[$dic['relation'] . "_id"]);
                }
            }
        }

        return $datas;
    }

    /**
     * Deleta informações extendidas multiplas de uma entidade
     *
     * @param string $entity
     * @param array $data
     */
    private static function deleteLinkedContent(string $entity, array $data)
    {
        $info = Metadados::getInfo($entity);
        $dic = Metadados::getDicionario($entity);
        if ($info && isset($data['id'])) {

            /*   REMOVE EXTEND MULT  */
            if ($info['extend_mult']) {
                foreach ($info['extend_mult'] as $item) {
                    $read = new Read();
                    $read->exeRead(PRE . $entity . "_" . $dic[$item]['relation'], "WHERE {$entity}_id = :id", "id={$data['id']}");
                    if ($read->getResult()) {
                        foreach ($read->getResult() as $i)
                            self::deleteExtend($dic[$item]['relation'], $i[$dic[$item]['relation'] . "_id"]);
                    }
                }
            }

            /*   REMOVE EXTEND   */
            if ($info['extend']) {
                foreach ($info['extend'] as $item)
                    self::deleteExtend($dic[$item]['relation'], $data[$dic[$item]['column']]);
            }

            /*   REMOVE SOURCES   */
            foreach ($dic as $id => $c) {
                if ($c['key'] === "source" && !empty($data[$c['column']])) {
                    $read = new Read();
                    $read->exeRead(PRE . $entity, "WHERE id != :id && " . $c['column'] . " = :s", "id={$data['id']}&s={$data[$c['column']]}");
                    if (!$read->getResult() && file_exists(PATH_HOME . $data[$c['column']]))
                        unlink(PATH_HOME . $data[$c['column']]);
                }
            }
        }
    }

    /**
     * Deleta informações extendidas de uma entidade
     *
     * @param string $entity
     * @param int $id
     */
    private static function deleteExtend(string $entity, int $id)
    {
        $del = new TableCrud($entity);
        $del->load($id);
        if ($del->exist())
            $del->delete();
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
                $data[$dic['column']] = self::copy($dic['relation'], (int)$data[$dic['column']]);

            elseif ($dic['key'] === "list")
                $data[$dic['column']] = self::copyList($dic['relation'], (int)$data[$dic['column']]);

            elseif ($dic['key'] === "extend_mult")
                $data[$dic['column']] = self::copyEntityMult($entity, $dic, $data['id']);

            elseif ($dic['key'] === "list_mult")
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
                    $datas[] = self::copy($dic['relation'], (int)$item[$dic['relation'] . "_id"]);
            }
        }

        return $datas;
    }

    /**
     * @param string $entity
     * @param array $data
     * @return mixed
     */
    private static function addData(string $entity, array $data)
    {
        $info = Metadados::getInfo($entity);
        $dicionario = Metadados::getDicionario($entity);
        $id = null;

        $data = self::validateData($entity, $data, $info, $dicionario);

        if (!self::$error)
            $id = self::storeData($entity, $data, $info, $dicionario);

        return self::$error ?? $id;
    }

    /**
     * @param string $entity
     * @param array $data
     * @param array $info
     * @param array $dicionario
     * @return mixed
     */
    private static function validateData(string $entity, array $data, array $info, array $dicionario)
    {
        $dataR = !empty($data['id']) && $data['id'] > 0 ? ["id" => $data['id']] : [];
        foreach ($dicionario as $i => $dic) {
            if (in_array($dic['key'], ["extend", "list"]))
                $dataR[$dic['column']] = self::checkDataOne($entity, $dic, $data[$dic['column']]);
            elseif (in_array($dic['key'], ["extend_mult", "list_mult"]))
                $dataR[$dic['column']] = self::checkDataMult($entity, $dic, $data[$dic['column']]);
            else
                $dataR[$dic['column']] = self::checkData($entity, $data, $dic, $dicionario, $info);
        }

        return self::$error ? null : $dataR;
    }

    /**
     * Armazena data no banco
     *
     * @param string $entity
     * @param array $data
     * @param array $info
     * @param array $dicionario
     * @return mixed
     */
    private static function storeData(string $entity, array $data, array $info, array $dicionario)
    {
        foreach (["extend", "list"] as $e) {
            if ($info[$e]) {
                foreach ($info[$e] as $i) {
                    if ($data[$dicionario[$i]['column']]) {
                        $eInfo = Metadados::getInfo($dicionario[$i]['relation']);
                        $eDic = Metadados::getDicionario($dicionario[$i]['relation']);
                        $data[$dicionario[$i]['column']] = self::storeData($dicionario[$i]['relation'], $data[$dicionario[$i]['column']], $eInfo, $eDic);
                    }
                }
            }
        }

        $relation = null;
        foreach (["extend_mult", "list_mult"] as $e) {
            if ($info[$e]) {
                foreach ($info[$e] as $i) {
                    $lInfo = Metadados::getInfo($dicionario[$i]['relation']);
                    $lDic = Metadados::getDicionario($dicionario[$i]['relation']);

                    foreach ($data[$dicionario[$i]['column']] as $datum) {
                        $relation[$dicionario[$i]['relation']][] = self::storeData($dicionario[$i]['relation'], $datum, $lInfo, $lDic);
                    }
                    unset($data[$dicionario[$i]['column']]);
                }
            }
        }

        $id = (!empty($data['id']) ? self::updateTableData($entity, $data) : self::createTableData($entity, $data));

        if (!$id)
            self::$error[$entity]['id'] = "Erro ao Salvar no Banco";
        elseif ($relation)
            self::createRelationalData($entity, $id, $relation);

        return $id;
    }

    /**
     * @param string $entity
     * @param int $id
     * @param array $relation
     */
    private static function createRelationalData(string $entity, int $id, array $relation)
    {
        $read = new Read();
        $create = new Create();
        foreach ($relation as $e => $list) {
            if (is_array($list)) {
                foreach ($list as $i) {
                    $read->exeRead(PRE . $entity . "_" . $e, "WHERE {$entity}_id = :eid && {$e}_id = :iid", "eid={$id}&iid={$i}");
                    if (!$read->getResult())
                        $create->exeCreate(PRE . $entity . "_" . $e, [$entity . "_id" => $id, $e . "_id" => $i]);
                }
            }
        }
    }

    /**
     * @param string $entity
     * @param array $data
     * @return mixed
     */
    private static function createTableData(string $entity, array $data)
    {
        unset($data['id']);
        $create = new Create();
        $create->exeCreate(PRE . $entity, $data);
        return $create->getResult();
    }

    /**
     * @param string $entity
     * @param array $data
     * @return mixed
     */
    private static function updateTableData(string $entity, array $data)
    {
        $read = new Read();
        $read->exeRead(PRE . $entity, "WHERE id=:id", "id={$data['id']}");
        if ($read->getResult()) {
            $up = new Update();
            $up->exeUpdate(PRE . $entity, $data, "WHERE id=:id", "id={$data['id']}");
            if ($up->getResult())
                return $data['id'];
        }

        return self::createTableData($entity, $data);
    }

    /**
     * @param string $entity
     * @param array $data
     * @param array $dic
     * @param array $dicionario
     * @param array $info
     * @return mixed
     */
    private static function checkData(string $entity, array $data, array $dic, array $dicionario, array $info)
    {
        $data[$dic['column']] = $data[$dic['column']] ?? null;
        $data[$dic['column']] = self::checkLink($data, $dic, $dicionario, $info);
        self::checkNullSet($entity, $dic, $data[$dic['column']]);

        $data[$dic['column']] = self::checkDefaultSet($dic, $data[$dic['column']]);

        self::checkType($entity, $dic, $data[$dic['column']]);
        self::checkSize($entity, $dic, $data[$dic['column']]);
        self::checkUnique($entity, $dic, $data[$dic['column']], $data['id'] ?? null);
        self::checkRegular($entity, $dic, $data[$dic['column']]);
        self::checkValidate($entity, $dic, $data[$dic['column']]);
        self::checkValues($entity, $dic, $data[$dic['column']]);

        return $data[$dic['column']];
    }

    /**
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $dados
     * @return mixed
     */
    private static function checkDataOne(string $entity, array $dic, $dados = null)
    {
        if (is_numeric($dados))
            $dados = Entity::read($dic['relation'], $dados);

        if ($dados && is_array($dados) && Check::isAssoc($dados)) {
            $data = Entity::validateData($dic['relation'], $dados, Metadados::getInfo($dic['relation']), Metadados::getDicionario($dic['relation']));

            if (isset(self::$error[$dic['relation']])) {
                self::$error[$entity][$dic['column']] = self::$error[$dic['relation']];
                unset(self::$error[$dic['relation']]);
            } else {
                return $data;
            }
        }

        return null;
    }

    /**
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $dados
     * @return mixed
     */
    private static function checkDataMult(string $entity, array $dic, $dados = null)
    {
        if ($dados && is_array($dados)) {
            $results = null;
            foreach ($dados as $dado)
                $results[] = self::checkDataOne($entity, $dic, $dado);

            return $results;
        }

        return null;
    }

    /**
     * Verifica se o valor submetido é inválido ao desejado
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkNullSet(string $entity, array $dic, $value)
    {
        if ($dic['default'] === false && empty($value))
            self::$error[$entity][$dic['column']] = "campo necessário";
    }

    /**
     * Verifica se o campo é do tipo link, se for, linka o valor ao título
     *
     * @param array $dados
     * @param array $dic
     * @param array $dicionario
     * @param array $info
     * @return mixed
     */
    private static function checkLink(array $dados, array $dic, array $dicionario, array $info)
    {
        if ($dic['key'] === "link" && $info['title'] !== null && !empty($dados[$dicionario[$info['title']]['column']]))
            return Check::name($dados[$dicionario[$info['title']]['column']]);

        return $dados[$dic['column']];
    }

    /**
     * Verifica se precisa alterar de modo padrão a informação deste campo
     *
     * @param array $dic
     * @param mixed $value
     * @return mixed
     */
    private static function checkDefaultSet(array $dic, $value = null)
    {
        if (!$value || empty($value)) {
            if($dic['default'] === "") {
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
            $d = [];
            foreach ($value as $i => $v)
                $d[$i] = (int)$v;

            $value = json_encode($d);
        }

        return $value;
    }

    /**
     * Verifica se o tipo do campo é o desejado
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkType(string $entity, array $dic, $value)
    {
        if (!empty($value)) {
            if (in_array($dic['type'], array("tinyint", "smallint", "mediumint", "int", "bigint"))) {
                if (!is_numeric($value))
                    self::$error[$entity][$dic['column']] = "número inválido";

            } elseif ($dic['type'] === "decimal") {
                $size = (!empty($dic['size']) ? explode(',', str_replace(array('(', ')'), '', $dic['size'])) : array(10, 30));
                $val = explode('.', str_replace(',', '.', $value));
                if (strlen($val[1]) > $size[1])
                    self::$error[$entity][$dic['column']] = "valor das casas decimais excedido. Max {$size[1]}";
                elseif (strlen($val[0]) > $size[0])
                    self::$error[$entity][$dic['column']] = "valor inteiro do valor decimal excedido. Max {$size[0]}";

            } elseif (in_array($dic['type'], array("double", "real"))) {
                if (!is_double($value))
                    self::$error[$entity][$dic['column']] = "valor double não válido";

            } elseif ($dic['type'] === "float") {
                if (!is_float($value))
                    self::$error[$entity][$dic['column']] = "valor flutuante não é válido";

            } elseif (in_array($dic['type'], array("bit", "boolean", "serial"))) {
                if (!is_bool($value))
                    self::$error[$entity][$dic['column']] = "valor boleano inválido. (true ou false)";

            } elseif (in_array($dic['type'], array("datetime", "timestamp"))) {
                if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $value))
                    self::$error[$entity][$dic['column']] = "formato de data inválido ex válido:(2017-08-23 21:58:00)";

            } elseif ($dic['type'] === "date") {
                if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $value))
                    self::$error[$entity][$dic['column']] = "formato de data inválido ex válido:(2017-08-23)";

            } elseif ($dic['type'] === "time") {
                if (!preg_match('/\d{2}:\d{2}/i', $value))
                    self::$error[$entity][$dic['column']] = "formato de tempo inválido ex válido:(21:58)";

            } elseif ($dic['type'] === "json") {
                if (!is_string($value))
                    self::$error[$entity][$dic['column']] = "formato json inválido";
            }
        }
    }

    /**
     * Verifica se o tamanho do valor corresponde ao desejado
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkSize(string $entity, array $dic, $value)
    {
        if ($dic['size']) {
            if ($dic['type'] === "varchar" && strlen($value) > $dic['size'])
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "char" && strlen($value) > 1)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "tinytext" && strlen($value) > 255)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "text" && strlen($value) > 65535)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "mediumtext" && strlen($value) > 16777215)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "longtext" && strlen($value) > 4294967295)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "tinyint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 8) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "smallint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 16) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "mediumint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 24) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "int" && !in_array($dic['key'], ["extend", "list", "list_mult", "extend_mult"]) && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 32) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "bigint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 64) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);
        }
    }

    /**
     * Verifica se o valor precisa ser único
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     * @param mixed $id
     */
    private static function checkUnique(string $entity, array $dic, $value, $id = null)
    {
        if ($dic['unique']) {
            $read = new Read();
            $read->exeRead(PRE . $entity, "WHERE {$dic['column']} = '{$value}'" . ($id ? " && id != " . $id : ""));
            if ($read->getResult())
                self::$error[$entity][$dic['column']] = "valor já existe, digite outro";
        }
    }

    /**
     * Verifica se existe expressão regular, e se existe, aplica a verificação
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkRegular(string $entity, array $dic, $value)
    {
        if (!empty($dic['allow']['regex']) && !empty($value) && is_string($value) && !preg_match($dic['allow']['regex'], $value))
            self::$error[$entity][$dic['column']] = "formato não permitido.";
    }

    /**
     * Verifica se o campo precisa de validação pré-formatada
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkValidate(string $entity, array $dic, $value)
    {
        if (!empty($dic['allow']['validate']) && !empty($value)) {
            switch ($dic['allow']['validate']) {
                case 'email':
                    if (!Check::email($value))
                        self::$error[$entity][$dic['column']] = "email inválido.";
                    break;
                case 'cpf':
                    if (!Check::cpf($value))
                        self::$error[$entity][$dic['column']] = "CPF inválido.";
                    break;
                case 'cnpj':
                    if (!Check::cnpj($value))
                        self::$error[$entity][$dic['column']] = "CNPJ inválido.";
                    break;
            }
        }
    }

    /**
     * Verifica se existem valores exatos permitidos
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkValues(string $entity, array $dic, $value)
    {
        if (!empty($dic['allow']['values']) && !empty($value) && !in_array($value, $dic['allow']['values']))
            self::$error[$entity][$dic['column']] = "valor não é permitido";
    }
}