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
    private static $error;

    private static function setEntity(string $entity)
    {
        self::$entity = $entity;
        self::$dicionario = \EntityForm\Metadados::getDicionario($entity);
        self::$info = \EntityForm\Metadados::getInfo($entity);
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
        $result = null;

        if (!Check::isAssoc($data)) {
            foreach ($data as $datum)
                $result[] = self::addData($datum);
        } else {
            $result = self::addData($data);
        }

        var_dump($data);
        var_dump(self::$error);

        return $result;
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

        return self::addDataToStore($data);
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

        if ($id > 0 && !self::$error && $relations)
            self::createRelationalData($id, $relations);

        if($id < 1 && !self::$error)
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
            foreach ($list as $i)
                $create->exeCreate(PRE . self::$entity . "_" . $entity, [self::$entity . "_id" => $id, $entity . "_id" => $i]);
        }
    }

    /**
     * @param array $data
     * @return int
     */
    private static function createTableData(array $data): int
    {
        if (!self::$error) {
            $create = new TableCrud(PRE . self::$entity);
            $create->loadArray($data);
            return $create->save();
        }

        return 0;
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
            if (!self::$error) {
                $create->setDados($data);
                $create->save();
                return self::$edit;
            }
        } else {
            return self::createTableData($data);
        }

        return 0;
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
        if ($dados && is_array($dados)) {
            $dici = self::$dicionario;
            $info = self::$info;
            $entity = self::$entity;
            $edit = self::$edit;

            $id = Entity::add($dic['relation'], $dados);

            self::$dicionario = $dici;
            self::$info = $info;
            self::$entity = $entity;
            self::$edit = $edit;

            return $id;
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

            elseif ($dic['type'] === "tinyint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 8) - 1))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "smallint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 16) - 1))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "mediumint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 24) - 1))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "int" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 32) - 1))
                self::$error[self::$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "bigint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 64) - 1))
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