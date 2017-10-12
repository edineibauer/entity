<?php
/**
 * Created by PhpStorm.
 * User: nenab
 * Date: 22/08/2017
 * Time: 22:21
 */

namespace Entity;

use ConnCrud\Read;
use Helpers\Check;
use Helpers\Helper;

class EntityOld extends EntityCreateStorage
{
    private $entityName;
    private $entityDados;
    private $identificador;
    private $erro;

    private $title;
    private $primary;
    private $image;
    private $extendData;
    private $extendMultData;
    private $listData;
    private $listMultData;

    public function __construct($entity)
    {
        $this->entityName = $entity;
        $this->identificador = 0;
        $this->loadStart();
    }

    public function insertDataEntity($arrayDataEntity)
    {
        if (is_array($arrayDataEntity)) {
            $this->setEntityArray($arrayDataEntity, $this->entityDados);
        } elseif (Check::json($arrayDataEntity)) {
            $this->setEntityArray(json_decode($arrayDataEntity, true), $this->entityDados);
        }
    }

    public function getDataEntity($id, $table = null) {
        $table = $table ?? $this->entityName;

        $info = new Entity($table);
        $struct = $info->getJsonStructEntity();
        $info = $info->getJsonInfoEntity();

        $read = new Read();
        $read->exeRead(PRE . $table, "WHERE id = :id", "id={$id}");

        return ($read->getResult() ? $this->getDadosFk($read->getResult()[0], $info, $struct, $table) : null);
    }

    /**
     * @return mixed
     */
    public function getErroEntity()
    {
        return $this->erro;
    }

    /**
     * @return array
     */
    public function getJsonStructEntity()
    {
        return $this->entityDados;
    }

    /**
     * @return array
     */
    public function getJsonInfoEntity(): array
    {
        return json_decode(file_get_contents(PATH_HOME . "entity/cache/" . $this->entityName . '_info.json'), true);
    }

    private function getDadosFk($dados, $info, $struct, $table)
    {
        //extend and list busca dados
        foreach (array("extend", "list") as $list) {
            if (!empty($info[$list])) {
                foreach ($info[$list] as $column) {
                    $dados[$column] = $this->getDataEntity($dados[$column], $struct[$column]['table']);
                }
            }
        }

        //extend and list mult busca relações -> busca dados
        foreach (array("extend_mult", "list_mult") as $list) {
            if(!empty($info[$list])) {
                foreach ($info[$list] as $column) {
                    $dados[$column] = $this->readDataFromMultFk($column, $struct, $dados, $table);
                }
            }
        }

        return $dados;
    }

    private function readDataFromMultFk($column, $struct, $dados, $table)
    {
        $result = null;
        $read = new Read();
        $read->exeRead(PRE . $table . "_" . $struct[$column]['table'], "WHERE {$table}_id = :ti", "ti={$dados['id']}");
        if($read->getResult()) {
            foreach ($read->getResult() as $item) {
                $result[$item[$struct[$column]['table'] . "_id"]] = $this->getDataEntity($item[$struct[$column]['table'] . "_id"], $struct[$column]['table']);
            }
        }

        return $result;
    }

    private function loadStart()
    {
        if (file_exists(PATH_HOME . "entity/cache/" . $this->entityName . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "entity/cache/" . $this->entityName . '.json'), true);

        } elseif (file_exists(PATH_HOME . "entity/" . $this->entityName . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "entity/" . $this->entityName . '.json'));

        } else {
            Helper::createFolderIfNoExist(PATH_HOME . "entity");
            $this->erro = "as entidades devem ficar na pasta 'entity/' e no formato json";
        }
    }

    private function loadEntityByJsonString($json, $worked = false)
    {
        if (!$worked && !$this->erro) {

            $this->entityDados = $this->autoLoadInfo(json_decode($json, true));
            $this->createCache($this->entityName, $this->entityDados);
            parent::createStorageEntity($this->entityName, $this->entityDados);

        } else if (!$this->erro) {

            parent::setTable($this->entityName);
            $this->entityDados = json_decode($json, true);
        }
    }

    private function createCache(string $nome, array $data)
    {
        Helper::createFolderIfNoExist(PATH_HOME . "entity/cache");
        $fp = fopen(PATH_HOME . "entity/cache/" . $nome . '.json', "w");
        fwrite($fp, json_encode($data));
        fclose($fp);
    }

    private function autoLoadInfo($data)
    {
        if ($data && is_array($data)) {
            $uniques = [];
            foreach ($data as $column => $dados) {
                switch ($dados['type']) {
                    case 'extend':
                        $data[$column] = $this->extend($data[$column], $column);
                        break;
                    case 'extend_mult':
                        $data[$column] = $this->extendMultiple($data[$column], $column);
                        break;
                    case 'list':
                        $data[$column] = $this->list($data[$column], $column);
                        break;
                    case 'list_mult':
                        $data[$column] = $this->listMultiple($data[$column], $column);
                        break;
                    case 'pri':
                        $data[$column] = $this->inputPrimary($data[$column], $column);
                        break;
                    case 'int':
                        $data[$column] = $this->inputInt($data[$column]);
                        break;
                    case 'tinyint':
                        $data[$column] = $this->inputTinyint($data[$column]);
                        break;
                    case 'title':
                        $data[$column] = $this->inputTitle($column, $data[$column]);
                        break;
                    case 'link':
                        $data[$column] = $this->inputLink($data, $column);
                        break;
                    case 'date':
                        $data[$column] = $this->inputDate($data, $column);
                        break;
                    case 'datetime':
                        $data[$column] = $this->inputDateTime($data, $column);
                        break;
                    case 'time':
                        $data[$column] = $this->inputTime($data, $column);
                        break;
                    case 'week':
                        $data[$column] = $this->inputWeek($data, $column);
                        break;
                    case 'cover':
                        $data[$column] = $this->inputCover($data[$column]);
                        break;
                    case 'email':
                        $data[$column] = $this->inputEmail($data[$column]);
                        break;
                    case 'password':
                        $data[$column] = $this->inputPassword($data[$column]);
                        break;
                    case 'status':
                        $data[$column] = $this->inputStatus($data[$column]);
                        break;
                    case 'text':
                        $data[$column] = $this->inputText($data[$column]);
                        break;
                    case 'textarea':
                        $data[$column] = $this->inputTextArea($data[$column]);
                        break;
                    case 'on':
                        $data[$column] = $this->inputOn($data[$column]);
                        break;
                    case 'select':
                        $data[$column] = $this->inputSelect($data[$column]);
                        break;
                    case 'valor':
                        $data[$column] = $this->inputValor($data[$column]);
                        break;
                    case 'float':
                        $data[$column] = $this->inputFloat($data[$column]);
                        break;
                    case 'cpf':
                        $data[$column] = $this->inputCpf($data[$column]);
                        break;
                    case 'cnpj':
                        $data[$column] = $this->inputCnpj($data[$column]);
                        break;
                }
                $data[$column] = $this->checkTagsValuesDefault($data[$column], $column);
                if($data[$column]['unique']) {
                    $uniques[] = $column;
                }
            }

            $dataInfo = array(
                "title" => $this->title ?? null,
                "image" => $this->image ?? null,
                "unique" => $uniques,
                "primary" => $this->primary ?? null,
                "extend" => $this->extendData ?? null,
                "extend_mult" => $this->extendMultData ?? null,
                "list" => $this->listData ?? null,
                "list_mult" => $this->listMultData ?? null,
            );
            $this->createCache($this->entityName . "_info", $dataInfo);
        }


        return $data;
    }

    private function checkTagsValuesDefault($field, $column)
    {
        $field['column'] = $column;
        $field['title'] = $this->prepareColumnName($column);
        $field['class'] = $field['class'] ?? "";
        $field['null'] = $field['null'] ?? true;
        $field['edit'] = $field['edit'] ?? true;
        $field['list'] = $field['list'] ?? true;
        $field['update'] = $field['update'] ?? true;
        $field['unique'] = $field['unique'] ?? false;
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
        if(isset($field['identificador'])) {
            $this->identificador = $field['identificador'] > $this->identificador ? $field['identificador'] : $this->identificador;
        } else {
            $field['identificador'] = $this->identificador;
        }
        $this->identificador ++;

        if($field['unique']) {
            $field['null'] = false;
        }

        return $field;
    }

    private function extend($field, $column)
    {
        $this->extendData[] = $column;
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

    private function list($field, $column)
    {
        $this->listData[] = $column;
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "list";
        $field['key_delete'] = "no action";
        $field['key_update'] = "no action";
        $field['input'] = "list";
        $field['null'] = $field['null'] ?? true;
        $field['unique'] = false;

        if (isset($field['tag']) && ((is_array($field['tag']) && in_array("image", $field['tag'])) || $field['tag'] === "image")) {
            $this->image = $column;
        }

        return $field;
    }

    private function extendMultiple($field, $column)
    {
        $this->extendMultData[] = $column;
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

    private function listMultiple($field, $column)
    {
        $this->listMultData[] = $column;
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

    private function inputPrimary($field, $column)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['null'] = false;
        $field['key'] = "primary";
        $field['input'] = "hidden";
        $field['update'] = false;
        $field['list'] = $field['list'] ?? false;
        $this->primary = $column;

        return $field;
    }

    private function inputText($field)
    {
        $field['type'] = "varchar";
        $field['input'] = "text";

        return $field;
    }

    private function inputTextArea($field)
    {
        $field['type'] = "text";
        $field['input'] = "textarea";

        return $field;
    }

    private function inputInt($field)
    {
        $field['size'] = $field['size'] ?? 11;
        $field['input'] = "int";

        return $field;
    }

    private function inputTinyint($field)
    {
        $field['size'] = $field['size'] ?? 1;
        $field['input'] = "int";

        return $field;
    }

    private function inputTitle($column, $field)
    {
        $field['type'] = "varchar";
        $field['size'] = $field['size'] ?? 127;
        $field['null'] = $field['null'] ?? false;
        $field['unique'] = $field['unique'] ?? true;
        $field['class'] = "font-size20 font-bold";
        $field['tag'] = "title";
        $field['list'] = true;
        $field['input'] = "text";
        $this->title = $column;

        return $field;
    }

    private function inputLink($data, $field)
    {
        $data[$field]['link'] = $data[$field]['link'] ?? $this->title;
        $data[$field]['type'] = $data[$this->title]['type'] ?? "varchar";
        $data[$field]['size'] = $data[$field]['size'] ?? $data[$this->title]['size'];
        $data[$field]['null'] = false;
        $data[$field]['unique'] = true;
        $data[$field]['class'] = "font-size08";
        $data[$field]['tag'] = "link";
        $data[$field]['input'] = "link";

        return $data[$field];
    }

    private function inputDate($data, $field)
    {
        $data[$field]['input'] = "date";
        $data[$field]['default'] = $data[$field]['default'] ?? "date";

        return $data[$field];
    }

    private function inputDateTime($data, $field)
    {
        $data[$field]['input'] = "datetime";
        $data[$field]['default'] = $data[$field]['default'] ?? "datetime";

        return $data[$field];
    }

    private function inputTime($data, $field)
    {
        $data[$field]['input'] = "time";
        $data[$field]['default'] = $data[$field]['default'] ?? "time";

        return $data[$field];
    }

    private function inputWeek($data, $field)
    {
        $data[$field]['input'] = "week";

        return $data[$field];
    }

    private function inputEmail($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = 127;
        $field['input'] = "email";
        $field['validade'] = "email";

        return $field;
    }

    private function inputPassword($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = $field['size'] ?? 255;
        $field['null'] = false;
        $field['input'] = "password";

        return $field;
    }

    private function inputCover($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = 255;
        $field['null'] = false;
        $field['unique'] = true;
        $field['input'] = "image";
        $field['list'] = $field['list'] ?? true;

        return $field;
    }

    private function inputStatus($field)
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

    private function inputOn($field)
    {
        $field['type'] = 'tinyint';
        $field['size'] = 1;
        $field['null'] = false;
        $field['allow'] = [0, 1];
        $field['input'] = "on";
        $field['default'] = $field['default'] ?? 0;

        return $field;
    }

    private function inputSelect($field)
    {
        $field['type'] = 'int';
        $field['input'] = "select";

        return $field;
    }

    private function inputValor($field)
    {
        $field['type'] = 'double';
        $field['input'] = "valor";

        return $field;
    }

    private function inputFloat($field)
    {
        $field['type'] = 'float';
        $field['input'] = "float";

        return $field;
    }

    private function inputCpf($field)
    {
        $field['type'] = 'varchar';
        $field['input'] = "cpf";
        $field['size'] = 11;

        return $field;
    }

    private function inputCnpj($field)
    {
        $field['type'] = 'varchar';
        $field['input'] = "cnpj";
        $field['size'] = 14;

        return $field;
    }

    private function prepareColumnName($column)
    {
        return trim(ucwords(str_replace(array('_', '-'), ' ', $column)));
    }
}