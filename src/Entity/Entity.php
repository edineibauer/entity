<?php

namespace Entity;

use ConnCrud\Read;
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
     */
    public static function add(string $entity, array $data)
    {
        self::setEntity($entity);

        if (isset($data['id']))
            self::editData($data);
        else
            self::addData($data);
    }

    /**
     * Edita data de uma entidade
     *
     * @param array $data
     */
    private static function editData(array $data)
    {
        self::$edit = $data['id'];
        self::checkDataEdit($data);
    }

    /**
     * Adiciona data à uma entidade
     *
     * @param array $data
     */
    private static function addData(array $data)
    {
        $data = self::checkDataAdd($data);

        var_dump($data);
        var_dump(self::$error);
    }

    /**
     * Verifica se os valores passados corrêspondem ao que é permitido para a entidade
     *
     * @param array $data
     * @return mixed
     */
    private static function checkDataAdd(array $data)
    {
        foreach (self::$dicionario as $i => $dic) {
            $data[$dic['column']] = $data[$dic['column']] ?? null;
            $data[$dic['column']] = self::checkLink($dic, $data);

            if (self::checkNullSet($dic, $data[$dic['column']]))
                return null;

            $data[$dic['column']] = self::checkDefaultSet($dic, $data[$dic['column']]);

            if (self::checkType($dic, $data[$dic['column']]))
                return null;
            if (self::checkSize($dic, $data[$dic['column']]))
                return null;
            if (self::checkUnique($dic, $data[$dic['column']]))
                return null;
            if (self::checkRegular($dic, $data[$dic['column']]))
                return null;
            if (self::checkValidate($dic, $data[$dic['column']]))
                return null;
            if (self::checkValues($dic, $data[$dic['column']]))
                return null;
        }

        return $data;
    }

    /**
     * Verifica se o valor submetido é inválido ao desejado
     *
     * @param array $dic
     * @param mixed $value
     * @return bool
     */
    private static function checkNullSet(array $dic, $value): bool
    {
        if ($dic['default'] === false && empty($value)) {
            self::$error = "{$dic['nome']} é necessário";
            return true;
        }

        return false;
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
     * @return bool
     */
    private static function checkType(array $dic, $value) :bool
    {
        if (!empty($value)) {
            if (in_array($dic['type'], array("tinyint", "smallint", "mediumint", "int", "bigint"))) {
                if (!is_numeric($value))
                    self::$error = "número inválido para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            } elseif ($dic['type'] === "decimal") {
                $size = (!empty($dic['size']) ? explode(',', str_replace(array('(', ')'), '', $dic['size'])) : array(10, 30));
                $val = explode('.', str_replace(',', '.', $value));
                if (strlen($val[1]) > $size[1])
                    self::$error = "valor das casas decimais excedido. Max {$size[1]} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";
                elseif (strlen($val[0]) > $size[0])
                    self::$error = "valor inteiro do valor decimal excedido. Max {$size[0]} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            } elseif (in_array($dic['type'], array("double", "real"))) {
                if (!is_double($value))
                    self::$error = "valor double não válido para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            } elseif ($dic['type'] === "float") {
                if (!is_float($value))
                    self::$error = "valor flutuante não é válido para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            } elseif (in_array($dic['type'], array("bit", "boolean", "serial"))) {
                if (!is_bool($value)) 
                    self::$error = "valor boleano inválido. (true ou false) para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";
                
            } elseif (in_array($dic['type'], array("datetime", "timestamp"))) {
                if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $value))
                    self::$error = "formato de data inválido ex válido:(2017-08-23 21:58:00) para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            } elseif ($dic['type'] === "date") {
                if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $value))
                    self::$error = "formato de data inválido ex válido:(2017-08-23) para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            } elseif ($dic['type'] === "time") {
                if (!preg_match('/\d{2}:\d{2}/i', $value))
                    self::$error = "formato de tempo inválido ex válido:(21:58) para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";
            }
        }
        
        return self::$error !== null;
    }

    /**
     * Verifica se o tamanho do valor corresponde ao desejado
     *
     * @param array $dic
     * @param mixed $value
     * @return bool
     */
    private static function checkSize(array $dic, $value) :bool
    {
        if($dic['size']) {
            if ($dic['type'] === "varchar" && strlen($value) > $dic['size'])
                self::$error = "tamanho máximo de caracteres excedido. Max {$dic['size']} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "char" && strlen($value) > 1)
                self::$error = "tamanho máximo de caracteres excedido. Max {$dic['size']} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "tinytext" && strlen($value) > 255)
                self::$error = "tamanho máximo de caracteres excedido. Max {$dic['size']} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "text" && strlen($value) > 65535)
                self::$error = "tamanho máximo de caracteres excedido. Max {$dic['size']} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "mediumtext" && strlen($value) > 16777215)
                self::$error = "tamanho máximo de caracteres excedido. Max {$dic['size']} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "longtext" && strlen($value) > 4294967295)
                self::$error = "tamanho máximo de caracteres excedido. Max {$dic['size']} para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "tinyint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 8) - 1))
                self::$error = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1) . " para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "smallint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 16) - 1))
                self::$error = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1) . " para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "mediumint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 24) - 1))
                self::$error = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1) . " para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "int" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 32) - 1))
                self::$error = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1) . " para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";

            elseif ($dic['type'] === "bigint" && $value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 64) - 1))
                self::$error = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1) . " para o campo {$dic["nome"]} em '" . ucfirst(self::$entity) . "'.";
        }

        return self::$error !== null;
    }

    /**
     * Verifica se o valor precisa ser único
     *
     * @param array $dic
     * @param mixed $value
     * @return bool
     */
    private static function checkUnique(array $dic, $value) :bool
    {
        if ($dic['unique']) {
            $read = new Read();
            $read->exeRead(PRE . self::$entity, "WHERE {$dic['column']} = '{$value}'" . (self::$edit ? " && id != " . self::$edit : ""));
            if ($read->getResult())
                self::$error = "{$dic["nome"]} precisa ser único em " . self::$entity;
        }

        return self::$error !== null;
    }

    /**
     * Verifica se existe expressão regular, e se existe, aplica a verificação
     *
     * @param array $dic
     * @param mixed $value
     * @return bool
     */
    private static function checkRegular(array $dic, $value) :bool
    {
        if (!empty($dic['allow']['regex']) && !empty($value) && is_string($value) && !preg_match($dic['allow']['regex'], $value))
            self::$error = "{$dic['nome']} não esta no formato permitido em '" . ucfirst(self::$entity) . "'.";

        return self::$error !== null;
    }

    /**
     * Verifica se o campo precisa de validação pré-formatada
     *
     * @param array $dic
     * @param mixed $value
     * @return bool
     */
    private static function checkValidate(array $dic, $value) :bool
    {
        if(!empty($dic['allow']['validate']) && !empty($value)) {
            switch ($dic['allow']['validate']) {
                case 'email':
                    if(!Check::email($value))
                        self::$error = "Campo {$dic['nome']} não corresponde a um email válido em '" . ucfirst(self::$entity) . "'.";
                    break;
                case 'cpf':
                    if(!Check::cpf($value))
                        self::$error = "Campo {$dic['nome']} não corresponde a um CPF válido em '" . ucfirst(self::$entity) . "'.";
                    break;
                 case 'cnpj':
                    if(!Check::cnpj($value))
                        self::$error = "Campo {$dic['nome']} não corresponde a um CNPJ válido em '" . ucfirst(self::$entity) . "'.";
                    break;
            }
        }

        return self::$error !== null;
    }

    /**
     * Verifica se existem valores exatos permitidos
     *
     * @param array $dic
     * @param mixed $value
     * @return bool
     */
    private static function checkValues(array $dic, $value) :bool
    {
        if (!empty($dic['allow']['values']) && !empty($value) && !in_array($value, $dic['allow']['values']))
            self::$error = "{$dic['nome']} possui um valor não é permitido em " . self::$entity;

        return self::$error !== null;
    }

    /**
     * Verifica se os valores passados corrêspondem ao que é permitido para a entidade
     *
     * @param array $data
     */
    private static function checkDataEdit(array $data)
    {

    }

}