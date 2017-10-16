<?php

namespace Entity;

use Helpers\Helper;

class Metadados extends CreateEntityStorage
{
    private static $erro;

    /**
     * @return mixed
     */
    public function getErroEntity()
    {
        return self::$erro;
    }

    /**
     * @param string $entity
     * @return mixed
     */
    public static function getStructInfo(string $entity)
    {
        self::checkLoad($entity);
        return self::$erro ? null : array("struct" => self::getStruct($entity), "info" => self::getInfo($entity));
    }

    /**
     * @param string $entity
     * @return mixed
     */
    public static function getStruct($entity)
    {
        self::checkLoad($entity);
        return self::$erro ? null : json_decode(file_get_contents(PATH_HOME . "entity/cache/" . $entity . '.json'), true);
    }

    /**
     * @param string $entity
     * @return mixed
     */
    public static function getInfo($entity)
    {
        self::checkLoad($entity);
        return self::$erro ? null : json_decode(file_get_contents(PATH_HOME . "entity/cache/" . $entity . '_info.json'), true);
    }

    /**
     * @param string $entity
     * @return mixed
     */
    public static function getFields($entity)
    {
        self::checkLoad($entity);
        return self::$erro ? null : array_keys(json_decode(file_get_contents(PATH_HOME . "entity/cache/" . $entity . '.json'), true));
    }

    private static function checkLoad(string $entity)
    {
        if ((!file_exists(PATH_HOME . "entity/cache/" . $entity . '.json') || !file_exists(PATH_HOME . "entity/cache/" . $entity . '_info.json')) && file_exists(PATH_HOME . "entity/" . $entity . '.json')) {

            self::autoLoadInfo($entity);

        } else {

            if(!file_exists(PATH_HOME . "entity/" . $entity . '.json')) {
                if (file_exists(PATH_HOME . "entity/cache/" . $entity . '.json')) {
                    unlink(PATH_HOME . "entity/cache/{$entity}.json");
                }
                if (file_exists(PATH_HOME . "entity/cache/" . $entity . '_info.json')) {
                    unlink(PATH_HOME . "entity/cache/{$entity}_info.json");
                }

                Helper::createFolderIfNoExist(PATH_HOME . "entity");
                self::$erro = "não foi possível encontrar a entidade na pasta 'entity/' no formato json";
            }
        }
    }

    private static function createCache(string $entity, array $data)
    {
        Helper::createFolderIfNoExist(PATH_HOME . "entity/cache");
        $fp = fopen(PATH_HOME . "entity/cache/" . $entity . '.json', "w");
        fwrite($fp, json_encode($data));
        fclose($fp);
    }

    private static function autoLoadInfo(string $entity)
    {
        $identificador = 0;

        $dataInfo = array("title" => null, "image" => null, "primary" => null, "extend" => null, "extend_mult" => null, "list" => null, "list_mult" => null);

        $data = json_decode(file_get_contents(PATH_HOME . 'entity/' . $entity . '.json'), true);
        foreach ($data as $column => $metadados) {
            switch ($metadados['type']) {
                case 'extend':
                    $data[$column] = self::extend($metadados);
                    break;
                case 'extend_mult':
                    $data[$column] = self::extendMultiple($metadados);
                    break;
                case 'list':
                    $data[$column] = self::list($metadados);
                    break;
                case 'list_mult':
                    $data[$column] = self::listMultiple($metadados);
                    break;
                case 'pri':
                    $data[$column] = self::inputPrimary($metadados);
                    break;
                case 'int':
                    $data[$column] = self::inputInt($metadados);
                    break;
                case 'tinyint':
                    $data[$column] = self::inputTinyint($metadados);
                    break;
                case 'title':
                    $data[$column] = self::inputTitle($metadados);
                    break;
                case 'link':
                    $data[$column] = self::inputLink($data, $column, $dataInfo);
                    break;
                case 'date':
                    $data[$column] = self::inputDate($data, $column);
                    break;
                case 'datetime':
                    $data[$column] = self::inputDateTime($data, $column);
                    break;
                case 'time':
                    $data[$column] = self::inputTime($data, $column);
                    break;
                case 'week':
                    $data[$column] = self::inputWeek($data, $column);
                    break;
                case 'cover':
                    $data[$column] = self::inputCover($metadados);
                    break;
                case 'email':
                    $data[$column] = self::inputEmail($metadados);
                    break;
                case 'password':
                    $data[$column] = self::inputPassword($metadados);
                    break;
                case 'status':
                    $data[$column] = self::inputStatus($metadados);
                    break;
                case 'text':
                    $data[$column] = self::inputText($metadados);
                    break;
                case 'textarea':
                    $data[$column] = self::inputTextArea($metadados);
                    break;
                case 'on':
                    $data[$column] = self::inputOn($metadados);
                    break;
                case 'select':
                    $data[$column] = self::inputSelect($metadados);
                    break;
                case 'valor':
                    $data[$column] = self::inputValor($metadados);
                    break;
                case 'float':
                    $data[$column] = self::inputFloat($metadados);
                    break;
                case 'cpf':
                    $data[$column] = self::inputCpf($metadados);
                    break;
                case 'cnpj':
                    $data[$column] = self::inputCnpj($metadados);
                    break;
            }

            if(isset($data[$column]['identificador'])) {
                $identificador = $data[$column]['identificador'] > $identificador ? $data[$column]['identificador'] : $identificador;
            }

            $data[$column] = self::checkTagsValuesDefault($data[$column], $column, $identificador);
            $dataInfo = self::setInfo($dataInfo, $data[$column], $column);

            $identificador++;
        }

        self::createCache($entity . "_info", $dataInfo);
        self::createCache($entity, $data);
        new CreateEntityStorage($entity, $data);
    }

    private static function setInfo(array $dataInfo, array $data, string $column) :array
    {

        if ($data['unique']) {
            $dataInfo["unique"][] = $column;

        } elseif($data['key'] === "primary") {
            $dataInfo["primary"] = $column;
        }

        if($data['tag'] === "title") {
            $dataInfo["title"] = $column;

        } elseif($data['key'] === "list") {
            $dataInfo["list"][] = $column;

            if (!empty($data['tag']) && ((is_array($data['tag']) && in_array("image", $data['tag'])) || $data['tag'] === "image")) {
                $dataInfo["image"] = $column;
            }

        } elseif($data['key'] === "list_mult") {
            $dataInfo["list_mult"][] = $column;

        } elseif($data['key'] === "extend_mult") {
            $dataInfo["extend_mult"][] = $column;

        } elseif($data['key'] === "extend") {
            $dataInfo["extend"][] = $column;
        }

        return $dataInfo;
    }

    private static function checkTagsValuesDefault(array $field, string $column, int $identificador = 0) :array
    {
        $field['column'] = $column;
        $field['title'] = self::prepareColumnName($column);
        $field['class'] = $field['class'] ?? "";
        $field['edit'] = $field['edit'] ?? true;
        $field['list'] = $field['list'] ?? true;
        $field['update'] = $field['update'] ?? true;
        $field['unique'] = $field['unique'] ?? false;
        $field['null'] = ($field['unique'] ? false : ($field['null'] ?? true));
        $field['indice'] = $field['indice'] ?? false;
        $field["size"] = $field["size"] ?? "";
        $field["allow"] = $field["allow"] ?? "";
        $field["allowRelation"] = $field["allowRelation"] ?? "";
        $field["default"] = $field["default"] ?? "";
        $field["table"] = $field["table"] ?? "";
        $field["col"] = $field["col"] ?? "row";
        $field["class"] = $field["class"] ?? "";
        $field["style"] = $field["style"] ?? "";
        $field["regular"] = $field["regular"] ?? "";
        $field["prefixo"] = $field["prefixo"] ?? "";
        $field["sulfixo"] = $field["sulfixo"] ?? "";
        $field['key'] = $field['key'] ?? "";
        $field['tag'] = $field['tag'] ?? "";
        $field['identificador'] = $field['identificador'] ?? $identificador;

        return $field;
    }

    private static function extend($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "extend";
        $field['key_delete'] = "cascade";
        $field['key_update'] = "no action";
        $field['input'] = "extend";
        $field['null'] = false;
        $field['unique'] = true;

        return $field;
    }

    private static function list($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "list";
        $field['key_delete'] = "no action";
        $field['key_update'] = "no action";
        $field['input'] = "list";
        $field['null'] = $field['null'] ?? true;
        $field['unique'] = false;

        return $field;
    }

    private static function extendMultiple($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "extend_mult";
        $field['key_delete'] = "cascade";
        $field['key_update'] = "no action";
        $field['input'] = "extend_mult";
        $field['null'] = false;
        $field['unique'] = true;

        return $field;
    }

    private static function listMultiple($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "list_mult";
        $field['key_delete'] = "cascade";
        $field['key_update'] = "no action";
        $field['input'] = "list_mult";
        $field['null'] = false;
        $field['unique'] = false;

        return $field;
    }

    private static function inputPrimary($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['null'] = true;
        $field['key'] = "primary";
        $field['input'] = "hidden";
        $field['update'] = false;
        $field['list'] = $field['list'] ?? false;

        return $field;
    }

    private static function inputText($field)
    {
        $field['type'] = "varchar";
        $field['input'] = "text";

        return $field;
    }

    private static function inputTextArea($field)
    {
        $field['type'] = "text";
        $field['input'] = "textarea";

        return $field;
    }

    private static function inputInt($field)
    {
        $field['size'] = $field['size'] ?? 11;
        $field['input'] = "int";

        return $field;
    }

    private static function inputTinyint($field)
    {
        $field['size'] = $field['size'] ?? 1;
        $field['input'] = "int";

        return $field;
    }

    private static function inputTitle($field)
    {
        $field['type'] = "varchar";
        $field['size'] = $field['size'] ?? 127;
        $field['null'] = $field['null'] ?? false;
        $field['unique'] = $field['unique'] ?? true;
        $field['class'] = "font-size20 font-bold";
        $field['tag'] = "title";
        $field['list'] = true;
        $field['input'] = "text";

        return $field;
    }

    private static function inputLink($data, $field, $dataInfo)
    {
        $data[$field]['link'] = $data[$field]['link'] ?? $dataInfo['title'];
        $data[$field]['type'] = $data[$dataInfo['title']]['type'] ?? "varchar";
        $data[$field]['size'] = $data[$field]['size'] ?? $data[$dataInfo['title']]['size'];
        $data[$field]['null'] = false;
        $data[$field]['unique'] = true;
        $data[$field]['class'] = "font-size08";
        $data[$field]['tag'] = "link";
        $data[$field]['input'] = "link";

        return $data[$field];
    }

    private static function inputDate($data, $field)
    {
        $data[$field]['input'] = "date";
        $data[$field]['default'] = $data[$field]['default'] ?? "date";

        return $data[$field];
    }

    private static function inputDateTime($data, $field)
    {
        $data[$field]['input'] = "datetime";
        $data[$field]['default'] = $data[$field]['default'] ?? "datetime";

        return $data[$field];
    }

    private static function inputTime($data, $field)
    {
        $data[$field]['input'] = "time";
        $data[$field]['default'] = $data[$field]['default'] ?? "time";

        return $data[$field];
    }

    private static function inputWeek($data, $field)
    {
        $data[$field]['input'] = "week";

        return $data[$field];
    }

    private static function inputEmail($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = 127;
        $field['input'] = "email";
        $field['validade'] = "email";

        return $field;
    }

    private static function inputPassword($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = $field['size'] ?? 255;
        $field['null'] = false;
        $field['input'] = "password";

        return $field;
    }

    private static function inputCover($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = 255;
        $field['null'] = false;
        $field['unique'] = true;
        $field['input'] = "image";
        $field['list'] = $field['list'] ?? true;

        return $field;
    }

    private static function inputStatus($field)
    {
        $field['type'] = 'tinyint';
        $field['size'] = 1;
        $field['null'] = false;
        $field['allow'] = [0, 1];
        $field["relation"] = [0 => "desativado", 1 => "ativo"];
        $field['input'] = "on";
        $field['default'] = $field['default'] ?? 0;

        return $field;
    }

    private static function inputOn($field)
    {
        $field['type'] = 'tinyint';
        $field['size'] = 1;
        $field['null'] = false;
        $field['allow'] = [0, 1];
        $field['input'] = "on";
        $field['default'] = $field['default'] ?? 0;

        return $field;
    }

    private static function inputSelect($field)
    {
        $field['type'] = 'int';
        $field['input'] = "select";

        return $field;
    }

    private static function inputValor($field)
    {
        $field['type'] = 'double';
        $field['input'] = "valor";

        return $field;
    }

    private static function inputFloat($field)
    {
        $field['type'] = 'float';
        $field['input'] = "float";

        return $field;
    }

    private static function inputCpf($field)
    {
        $field['type'] = 'varchar';
        $field['input'] = "cpf";
        $field['size'] = 11;

        return $field;
    }

    private static function inputCnpj($field)
    {
        $field['type'] = 'varchar';
        $field['input'] = "cnpj";
        $field['size'] = 14;

        return $field;
    }

    private static function prepareColumnName($column)
    {
        return trim(ucwords(str_replace(array('_', '-'), ' ', $column)));
    }
}