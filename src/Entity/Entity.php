<?php
/**
 * Created by PhpStorm.
 * User: nenab
 * Date: 22/08/2017
 * Time: 22:21
 */

namespace Entity;

use Helpers\Check;

class Entity extends EntityCreateStorage
{
    private $entityName;
    private $entity;
    private $title;
    private $erro;

    public function __construct($file = null)
    {
        if ($file) {
            $this->loadEntityByFileName($file);
        }
    }

    /**
     * @param mixed $entity
     */
    public function setEntity($entity)
    {
        if(strlen($entity) < 25) {
            $this->loadEntityByFileName($entity);
        }elseif(Check::json($entity)) {
            $this->loadEntityByJsonString($entity);
        } else {
            $this->loadEntityByFileName($entity);
        }
    }

    public function setDataEntity($entityData)
    {
        if(is_array($entityData)) {
            $this->setEntityArray($entityData);
        }elseif(Check::json($entityData)) {
            $this->setEntityJson($entityData);
        }
    }

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->entity;
    }

    private function loadEntityByFileName($file)
    {
        if (file_exists(PATH_HOME . "sql/entities_worked/" . $file . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "sql/entities_worked/" . $file . '.json'), $file);

        } elseif (file_exists(PATH_HOME . "sql/entities/" . $file . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "sql/entities/" . $file . '.json'), $file);

        } else {
            $this->erro = "os arquivos json para serem carregados devem ficar na pasta 'sql/entities/' caso estas pastas não existão no seu sistema, crie elas";

        }
    }

    private function loadEntityByJsonString($json, $fileName = null)
    {
        if(!$fileName) {
            foreach ($json as $table => $dados) {
                $fileName = $table;
                break;
            }
        }
        if (isset($fileName) && !empty($fileName)) {
            $this->entityName = $fileName;
            $this->entity = $this->autoLoadInfo(json_decode($json, true));
            $this->createEntityWorked();
            parent::createStorageEntity($this->entityName, $this->entity);
        }
    }

    private function createEntityWorked()
    {
        $fp = fopen(PATH_HOME . "sql/entities_worked/" . $this->entityName . '.json', "w");
        fwrite($fp, json_encode($this->entity));
        fclose($fp);
    }

    private function autoLoadInfo($data)
    {
        if ($data && is_array($data)) {
            foreach ($data as $column => $dados) {
                switch ($dados['type']) {
                    case '1-1':
                        $data[$column] = $this->oneToOne($data[$column]);
                        break;
                    case '1-n':
                        $data[$column] = $this->oneToMany($data[$column]);
                        break;
                    case 'n-n':
                        $data[$column] = $this->manyToMany($data[$column]);
                        break;
                    case 'pri':
                        $data[$column] = $this->inputPrimary($data[$column]);
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
                    case 'cover':
                        $data[$column] = $this->inputCover($data[$column]);
                        break;
                    case 'text':
                        $data[$column] = $this->inputText($data[$column]);
                        break;
                    case 'on':
                        $data[$column] = $this->inputOn($data[$column]);
                        break;
                }
            }
        }

        return $data;
    }

    private function oneToOne($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "fk";
        $field['key_delete'] = "cascade";
        $field['key_update'] = "no action";

        return $field;
    }

    private function oneToMany($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "fk";
        $field['key_delete'] = "no action";
        $field['key_update'] = "no action";

        return $field;
    }

    private function manyToMany($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['key'] = "fk";
        $field['key_delete'] = "no action";
        $field['key_update'] = "no action";

        return $field;
    }

    private function inputPrimary($field)
    {
        $field['type'] = "int";
        $field['size'] = 11;
        $field['null'] = false;
        $field['key'] = "primary";

        return $field;
    }

    private function inputText($field)
    {
        $field['type'] = "varchar";

        return $field;
    }

    private function inputInt($field)
    {
        $field['size'] = $field['size'] ?? 11;

        return $field;
    }

    private function inputTinyint($field)
    {
        $field['size'] = $field['size'] ?? 1;

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

        return $data[$field];
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
}