<?php

namespace Entity;

use ConnCrud\Create;
use ConnCrud\Read;
use ConnCrud\TableCrud;
use Helpers\Check;

class Entity
{
    private static $entity;
    private static $edit;
    private static $dicionario;
    private static $info;
    private static $backup;
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
     * Salva data à uma entidade
     *
     * @param string $entity
     * @param array $data
     * @return mixed
     */
    public static function add(string $entity, array $data)
    {
        self::setEntity($entity);
        $result = 0;

        if (!Check::isAssoc($data)) {
            foreach ($data as $datum)
                $result[] = self::addData($datum);
        } else {
            $result = self::addData($data);
        }

        self::restore();

        return self::$error || $result === 0 ? null : $result;
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
        self::setEntity($entity);
        $result = null;

        if (is_int($data)) {
            $copy = new TableCrud($entity);
            $copy->load($data);
            if ($copy->exist())
                $result = self::copyEntity($copy->getDados());
            else
                self::$error[self::$entity]['id'] = "id: {$data} não encontrado para cópia";

        } elseif (is_array($data)) {
            if (Check::isAssoc($data)) {
                $copy = new TableCrud($entity);
                $copy->loadArray($data);
                if ($copy->exist())
                    $result = self::copyEntity($copy->getDados());
                else
                    self::$error[self::$entity]['id'] = "datas não encontrado em " . self::$entity . " para cópia";

            } else {
                foreach ($data as $datum) {
                    if (!self::$error)
                        $result[] = self::copy($entity, (int)$datum);
                }
            }
        }

        self::restore();

        unset($result['id']);

        return self::$error ? null : $result;
    }

    /**
     * Seta Entidade e seu dicionário
     *
     * @param string $entity
     */
    private static function setEntity(string $entity)
    {
        self::backup();
        self::$edit = null;
        self::$error = null;
        self::$entity = $entity;
        self::$dicionario = \EntityForm\Metadados::getDicionario($entity);
        self::$info = \EntityForm\Metadados::getInfo($entity);
    }

    /**
     * Faz backup das informações atuais
     */
    private static function backup()
    {
        self::$backup['dici'] = self::$dicionario;
        self::$backup['info'] = self::$info;
        self::$backup['entity'] = self::$entity;
        self::$backup['edit'] = self::$edit;
    }

    /**
     * Restaura informações armazenadas em backup
     */
    private static function restore()
    {
        self::$dicionario = self::$backup['dici'];
        self::$info = self::$backup['info'];
        self::$entity = self::$backup['entity'];
        self::$edit = self::$backup['edit'];
    }

    /**
     * Deleta informações extendidas multiplas de uma entidade
     *
     * @param string $entity
     * @param array $data
     */
    private static function deleteLinkedContent(string $entity, array $data)
    {
        $info = \EntityForm\Metadados::getInfo($entity);
        $dic = \EntityForm\Metadados::getDicionario($entity);
        if ($info) {

            /*   REMOVE EXTEND MULT  */
            foreach ($info['extend_mult'] as $item) {
                $read = new Read();
                $read->exeRead(PRE . $entity . "_" . $dic[$item]['relation'], "WHERE {$entity}_id = :id", "id={$data['id']}");
                if ($read->getResult()) {
                    foreach ($read->getResult() as $i)
                        self::deleteExtend($dic[$item]['relation'], $i[$dic[$item]['relation'] . "_id"]);
                }
            }

            /*   REMOVE EXTEND   */
            foreach ($info['extend'] as $item)
                self::deleteExtend($dic[$item]['relation'], $data[$dic[$item]['column']]);

            /*   REMOVE SOURCES   */
            foreach ($info['source'] as $type => $id) {
                $read = new Read();
                $read->exeRead(PRE . $entity, "WHERE id != :id && " . $dic[$id]['column'] . " = :s", "id={$data['id']}&s={$data[$dic[$id]['column']]}");
                if(!$read->getResult())
                    unlink(PATH_HOME . $data[$dic[$id]['column']]);
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
     * @param array $data
     * @return array
     */
    private static function copyEntity(array $data): array
    {
        foreach (self::$dicionario as $dic) {
            if ($dic['key'] === "extend" && !self::$error)
                $data[$dic['column']] = self::copy($dic['relation'], (int)$data[$dic['column']]);

            elseif ($dic['key'] === "list")
                $data[$dic['column']] = self::copyList($dic['relation'], (int)$data[$dic['column']]);

            elseif ($dic['key'] === "extend_mult")
                $data[$dic['column']] = self::copyEntityMult($dic, $data['id']);

            elseif ($dic['key'] === "list_mult")
                $data[$dic['column']] = self::copyListMult($dic, $data['id']);

            else
                $data[$dic['column']] = self::copyEntityData($dic, $data);
        }

        return $data;
    }


    /**
     * @param array $dic
     * @param int $id
     * @return mixed
     */
    private static function copyListMult(array $dic, int $id)
    {
        $datas = null;
        $read = new Read();
        $read->exeRead(PRE . self::$entity . "_" . $dic['relation'], "WHERE " . self::$entity . "_id = :id", "id={$id}");
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
     * @param array $dic
     * @param array $data
     * @return mixed
     */
    private static function copyEntityData(array $dic, array $data)
    {
        if ($dic['unique']) {
            $data[$dic['column']] = rand(0, 999999) . "-" . $data[$dic['column']];
            $read = new TableCrud(PRE . self::$entity);
            $read->loadArray([$dic['column'] => $data[$dic['column']]]);
            if ($read->exist())
                $data[$dic['column']] = rand(0, 999999) . "--" . $data[$dic['column']];
        }

        if ($dic['key'] === "link")
            $data[$dic['column']] = Check::name($data[self::$dicionario[self::$info['title']]['column']]);

        return $data[$dic['column']];
    }

    /**
     * @param array $dic
     * @param int $id
     * @return mixed
     */
    private static function copyEntityMult(array $dic, int $id)
    {
        $datas = null;
        $read = new Read();
        $read->exeRead(PRE . self::$entity . "_" . $dic['relation'], "WHERE " . self::$entity . "_id = :id", "id={$id}");
        if ($read->getResult()) {
            foreach ($read->getResult() as $item) {
                if (!self::$error)
                    $datas[] = self::copy($dic['relation'], (int)$item[$dic['relation'] . "_id"]);
            }
        }

        return $datas;
    }

    /**
     * @param array $data
     * @return int
     */
    private static function addData(array $data): int
    {
        if (isset($data['id'])) {
            self::$edit = $data['id'];
            unset($data['id']);
        }

        $data = self::checkData($data);

        if (!self::$error)
            return self::addDataToStore($data);

        return 0;
    }

    /**
     * @param array $data
     * @return int
     */
    private static function addDataToStore(array $data): int
    {
        $id = null;

        $relations = self::splitRelation($data);
        $data = $relations['data'];
        $relations = $relations['relation'];

        if (self::$edit)
            $id = (int)self::updateTableData($data);
        else
            $id = (int)self::createTableData($data);

        if ($id > 0 && $relations)
            self::createRelationalData($id, $relations);

        if ($id < 1)
            self::$error[self::$entity]['id'] = "Erro ao salvar, id retornado 0.";

        return $id;
    }

    /**
     * @param array $data
     * @return array
     */
    private static function splitRelation(array $data): array
    {
        $relation = null;
        foreach (self::$dicionario as $i => $dic) {
            if (in_array($dic['key'], ["extend_mult", "list_mult"])) {
                $relation[$dic['relation']] = $data[$dic['column']];
                unset($data[$dic['column']]);
            }
        }

        return ["relation" => $relation, "data" => $data];
    }


    /**
     * @param int $id
     * @param array $relation
     */
    private static function createRelationalData(int $id, array $relation)
    {
        $create = new Create();
        foreach ($relation as $entity => $list) {
            if (is_array($list)) {
                foreach ($list as $i)
                    $create->exeCreate(PRE . self::$entity . "_" . $entity, [self::$entity . "_id" => $id, $entity . "_id" => $i]);
            }
        }
    }

    /**
     * @param array $data
     * @return int
     */
    private static function createTableData(array $data): int
    {
        $create = new TableCrud(PRE . self::$entity);
        $create->loadArray($data);
        return $create->save();
    }

    /**
     * @param array $data
     * @return int
     */
    private static function updateTableData(array $data): int
    {
        $create = new TableCrud(self::$entity);
        $create->load(self::$edit);
        if ($create->exist()) {
            $create->setDados($data);
            $create->save();
            return self::$edit;
        }

        return self::createTableData($data);
    }

    /**
     * Verifica se os valores passados corrêspondem ao que é permitido para a entidade
     *
     * @param array $data
     * @return mixed
     */
    private static function checkData(array $data)
    {
        foreach (self::$dicionario as $i => $dic) {
            $data[$dic['column']] = $data[$dic['column']] ?? null;

            if (in_array($dic['key'], ["extend", "list"]))
                $data[$dic['column']] = self::checkDataOne($dic, $data[$dic['column']]);
            elseif (in_array($dic['key'], ["extend_mult", "list_mult"]))
                $data[$dic['column']] = self::checkDataMult($dic, $data[$dic['column']]);
            else
                $data[$dic['column']] = self::checkDataEntity($dic, $data);
        }

        if (self::$error)
            return null;

        return $data;
    }

    /**
     * @param array $dic
     * @param array $data
     * @return mixed
     */
    private static function checkDataEntity(array $dic, array $data)
    {
        $data[$dic['column']] = self::checkLink($dic, $data);

        self::checkNullSet($dic, $data[$dic['column']]);

        $data[$dic['column']] = self::checkDefaultSet($dic, $data[$dic['column']]);

        self::checkType($dic, $data[$dic['column']]);
        self::checkSize($dic, $data[$dic['column']]);
        self::checkUnique($dic, $data[$dic['column']]);
        self::checkRegular($dic, $data[$dic['column']]);
        self::checkValidate($dic, $data[$dic['column']]);
        self::checkValues($dic, $data[$dic['column']]);

        return $data[$dic['column']];
    }

    /**
     *
     * @param array $dic
     * @param mixed $dados
     * @return mixed
     */
    private static function checkDataOne(array $dic, $dados = null)
    {
        if ($dados && is_array($dados) && Check::isAssoc($dados)) {
            $id = 0;
            if (!self::$error)
                $id = Entity::add($dic['relation'], $dados);

            return $id > 0 ? $id : null;
        }

        return null;
    }

    /**
     *
     * @param array $dic
     * @param mixed $dados
     * @return mixed
     */
    private static function checkDataMult(array $dic, $dados = null)
    {
        if ($dados && is_array($dados)) {
            $results = null;
            foreach ($dados as $dado)
                $results[] = self::checkDataOne($dic, $dado);

            return $results;
        }

        return null;
    }

    /**
     * Verifica se o valor submetido é inválido ao desejado
     *
     * @param array $dic
     * @param mixed $value
     */
    private static function checkNullSet(array $dic, $value)
    {
        if ($dic['default'] === false && empty($value))
            self::$error[self::$entity][$dic['column']] = "campo necessário";
    }

    /**
     * Verifica se o campo é do tipo link, se for, linka o valor ao título
     *
     * @param array $dic
     * @param array $dados
     * @return mixed
     */
    private static function checkLink(array $dic, array $dados)
    {
        if ($dic['key'] === "link" && self::$info['title'] !== null && !empty($dados[self::$dicionario[self::$info['title']]['column']]))
            return Check::name($dados[self::$dicionario[self::$info['title']]['column']]);

        return $dados[$dic['column']];
    }

    /**
     * Verifica se precisa alterar de modo padrão a informação deste campo
     *
     * @param array $dic
     * @param mixed $value
     * @return mixed
     */
    private static function checkDefaultSet(array $dic, $value)
    {
        if (empty($value)) {
            switch ($dic['default']) {
                case "datetime":
                    return date("Y-m-d H:i:s");
                    break;
                case "date":
                    return date("Y-m-d");
                    break;
                case "time":
                    return date("H:i:s");
                    break;
                default:
                    return $dic['default'];
            }
        }

        return $value;
    }

    /**
     * Verifica se o tipo do campo é o desejado
     *
     * @param array $dic
     * @param mixed $value
     */
    private static function checkType(array $dic, $value)
    {
        if (!empty($value)) {
            if (in_array($dic['type'], array("tinyint", "smallint", "mediumint", "int", "bigint"))) {
                if (!is_numeric($value))
                    self::$error[self::$entity][$dic['column']] = "número inválido";

            } elseif ($dic['type'] === "decimal") {
                $size = (!empty($dic['size']) ? explode(',', str_replace(array('(', ')'), '', $dic['size'])) : array(10, 30));
                $val = explode('.', str_replace(',', '.', $value));
                if (strlen($val[1]) > $size[1])
                    self::$error[self::$entity][$dic['column']] = "valor das casas decimais excedido. Max {$size[1]}";
                elseif (strlen($val[0]) > $size[0])
                    self::$error[self::$entity][$dic['column']] = "valor inteiro do valor decimal excedido. Max {$size[0]}";

            } elseif (in_array($dic['type'], array("double", "real"))) {
                if (!is_double($value))
                    self::$error[self::$entity][$dic['column']] = "valor double não válido";

            } elseif ($dic['type'] === "float") {
                if (!is_float($value))
                    self::$error[self::$entity][$dic['column']] = "valor flutuante não é válido";

            } elseif (in_array($dic['type'], array("bit", "boolean", "serial"))) {
                if (!is_bool($value))
                    self::$error[self::$entity][$dic['column']] = "valor boleano inválido. (true ou false)";

            } elseif (in_array($dic['type'], array("datetime", "timestamp"))) {
                if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $value))
                    self::$error[self::$entity][$dic['column']] = "formato de data inválido ex válido:(2017-08-23 21:58:00)";

            } elseif ($dic['type'] === "date") {
                if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $value))
                    self::$error[self::$entity][$dic['column']] = "formato de data inválido ex válido:(2017-08-23)";

            } elseif ($dic['type'] === "time") {
                if (!preg_match('/\d{2}:\d{2}/i', $value))
                    self::$error[self::$entity][$dic['column']] = "formato de tempo inválido ex válido:(21:58)";
            }
        }
    }

    /**
     * Verifica se o tamanho do valor corresponde ao desejado
     *
     * @param array $dic
     * @param mixed $value
     */
    private static function checkSize(array $dic, $value)
    {
        if ($dic['size']) {
            if ($dic['type'] === "varchar" && strlen($value) > $dic['size'])
                self::$error[self::$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "char" && strlen($value) > 1)
                self::$error[self::$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "tinytext" && strlen($value) > 255)
                self::$error[self::$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "text" && strlen($value) > 65535)
                self::$error[self::$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "mediumtext" && strlen($value) > 16777215)
                self::$error[self::$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "longtext" && strlen($value) > 4294967295)
                self::$error[self::$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "tinyint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 8) - 1)))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "smallint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 16) - 1)))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "mediumint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 24) - 1)))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "int" && !in_array($dic['key'], ["extend", "list", "list_mult", "extend_mult"]) && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 32) - 1)))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "bigint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 64) - 1)))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);
        }
    }

    /**
     * Verifica se o valor precisa ser único
     *
     * @param array $dic
     * @param mixed $value
     */
    private static function checkUnique(array $dic, $value)
    {
        if ($dic['unique']) {
            $read = new Read();
            $read->exeRead(PRE . self::$entity, "WHERE {$dic['column']} = '{$value}'" . (self::$edit ? " && id != " . self::$edit : ""));
            if ($read->getResult())
                self::$error[self::$entity][$dic['column']] = "precisa ser único";
        }
    }

    /**
     * Verifica se existe expressão regular, e se existe, aplica a verificação
     *
     * @param array $dic
     * @param mixed $value
     */
    private static function checkRegular(array $dic, $value)
    {
        if (!empty($dic['allow']['regex']) && !empty($value) && is_string($value) && !preg_match($dic['allow']['regex'], $value))
            self::$error[self::$entity][$dic['column']] = "formato não permitido.";
    }

    /**
     * Verifica se o campo precisa de validação pré-formatada
     *
     * @param array $dic
     * @param mixed $value
     */
    private static function checkValidate(array $dic, $value)
    {
        if (!empty($dic['allow']['validate']) && !empty($value)) {
            switch ($dic['allow']['validate']) {
                case 'email':
                    if (!Check::email($value))
                        self::$error[self::$entity][$dic['column']] = "email inválido.";
                    break;
                case 'cpf':
                    if (!Check::cpf($value))
                        self::$error[self::$entity][$dic['column']] = "CPF inválido.";
                    break;
                case 'cnpj':
                    if (!Check::cnpj($value))
                        self::$error[self::$entity][$dic['column']] = "CNPJ inválido.";
                    break;
            }
        }
    }

    /**
     * Verifica se existem valores exatos permitidos
     *
     * @param array $dic
     * @param mixed $value
     */
    private static function checkValues(array $dic, $value)
    {
        if (!empty($dic['allow']['values']) && !empty($value) && !in_array($value, $dic['allow']['values']))
            self::$error[self::$entity][$dic['column']] = "valor não é permitido";
    }
}