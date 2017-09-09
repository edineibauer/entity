<?php
/**
 * Created by PhpStorm.
 * User: nenab
 * Date: 22/08/2017
 * Time: 22:21
 */

namespace Entity;

use Helpers\Check;
use Helpers\Helper;

class Entity extends EntityCreateStorage
{
    private $library;
    private $entityName;
    private $entityDados;
    private $title;
    private $primary;
    private $image;
    private $erro;

    public function __construct($entity, $library = null)
    {
        $this->entityName = $entity;
        if($library) {
            $this->setLibrary($library);
        } else {
            $this->library = "entity";
        }
    }

    /**
     * @param string $library
     */
    public function setLibrary(string $library)
    {
        $this->library = $library;
        $this->loadStart();
    }

    public function insertDataEntity($arrayDataEntity)
    {
        if ($this->library) {
            if (is_array($arrayDataEntity)) {
                $this->setEntityArray($arrayDataEntity, $this->entityDados);
            } elseif (Check::json($arrayDataEntity)) {
                $this->setEntityArray(json_decode($arrayDataEntity, true), $this->entityDados);
            }
        } else {
            $this->erro = "Informe a biblioteca destino desta entidade";
        }
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
    public function getJsonInfoEntity()
    {
        if ($this->library) {
            return json_decode(file_get_contents(PATH_HOME . "vendor/conn/{$this->library}/entity/cache/" . $this->entityName . '_info.json'), true);
        } else {
            $this->erro = "Informe a biblioteca destino desta entidade";
        }
    }

    private function loadStart()
    {
        if (file_exists(PATH_HOME . "vendor/conn/{$this->library}/entity/cache/" . $this->entityName . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "vendor/conn/{$this->library}/entity/cache/" . $this->entityName . '.json'), true);

        } elseif (file_exists(PATH_HOME . "vendor/conn/{$this->library}/entity/" . $this->entityName . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "vendor/conn/{$this->library}/entity/" . $this->entityName . '.json'));

        } else {
            Helper::createFolderIfNoExist(PATH_HOME . "vendor/conn/{$this->library}/entity");
            $this->erro = "os arquivos json para serem carregados devem ficar na pasta 'vendor/conn/{$this->library}/entity/'";

        }
    }

    private function loadEntityByJsonString($json, $worked = false)
    {
        if (!$worked && !$this->erro) {

            $this->entityDados = $this->autoLoadInfo(json_decode($json, true));
            $this->createEntityWorked($this->entityName, $this->entityDados);
            parent::createStorageEntity($this->entityName, $this->entityDados);

        } else if (!$this->erro) {

            parent::setTable($this->entityName);
            $this->entityDados = json_decode($json, true);
        }
    }

    private function createEntityWorked(string $nome, array $data)
    {
        Helper::createFolderIfNoExist(PATH_HOME . "vendor/conn/{$this->library}/entity/cache");
        $fp = fopen(PATH_HOME . "vendor/conn/{$this->library}/entity/cache/" . $nome . '.json', "w");
        fwrite($fp, json_encode($data));
        fclose($fp);
    }

    private function autoLoadInfo($data)
    {
        if ($data && is_array($data)) {
            foreach ($data as $column => $dados) {
                switch ($dados['type']) {
                    case '1-1':
                        $data[$column] = $this->oneToOne($data[$column], $column);
                        break;
                    case '1-n':
                        $data[$column] = $this->oneToMany($data[$column]);
                        break;
                    case 'n-n':
                        $data[$column] = $this->manyToMany($data[$column]);
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
                    case 'on':
                        $data[$column] = $this->inputOn($data[$column]);
                        break;
                    case 'select':
                        $data[$column] = $this->inputSelect($data[$column]);
                        break;
                }
                $data[$column] = $this->checkTagsValuesDefault($data[$column], $column);
            }

            $dataInfo = array(
                "title" => $this->title ?? null,
                "primary" => $this->primary ?? null,
                "image" => $this->image ?? null
            );
            $this->createEntityWorked($this->entityName . "_info", $dataInfo);
        }


        return $data;
    }

    private function checkTagsValuesDefault($field, $column)
    {
        $field['column'] = $column;
        $field['title'] = $this->prepareColumnName($column);
        $field['class'] = $field['class'] ?? "";
        $field['row'] = $field['row'] ?? "row";
        $field['null'] = $field['null'] ?? true;
        $field['key'] = $field['key'] ?? "";

        return $field;
    }

    private function oneToOne($field, $column)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "fk";
        $field['key_delete'] = "cascade";
        $field['key_update'] = "no action";
        $field['input'] = "oneToOne";

        if (isset($field['tag']) && ((is_array($field['tag']) && in_array("image", $field['tag'])) || $field['tag'] === "image")) {
            $this->image = $column;
        }

        return $field;
    }

    private function oneToMany($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "fk";
        $field['key_delete'] = "no action";
        $field['key_update'] = "no action";
        $field['input'] = "oneToMany";

        return $field;
    }

    private function manyToMany($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "fk";
        $field['key_delete'] = "no action";
        $field['key_update'] = "no action";
        $field['input'] = "manyToMany";

        return $field;
    }

    private function inputPrimary($field, $column)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['null'] = false;
        $field['key'] = "primary";
        $field['input'] = "hidden";
        $this->primary = $column;

        return $field;
    }

    private function inputText($field)
    {
        $field['type'] = "varchar";
        $field['input'] = "text";

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
        $field['null'] = false;
        $field['key'] = "unique";
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
        $data[$field]['key'] = "unique";
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
        $field['key'] = "unique";
        $field['input'] = "email";
        $field['validade'] = "email";

        return $field;
    }

    private function inputPassword($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = 127;
        $field['null'] = false;
        $field['input'] = "password";

        return $field;
    }

    private function inputCover($field)
    {
        $field['type'] = 'varchar';
        $field['size'] = 255;
        $field['null'] = false;
        $field['key'] = "unique";
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
        $field['default'] = 0;

        return $field;
    }

    private function inputSelect($field)
    {
        $field['type'] = 'int';
        $field['input'] = "select";

        return $field;
    }

    private function prepareColumnName($column)
    {
        return trim(ucwords(str_replace(array('_', '-'), ' ', $column)));
    }
}