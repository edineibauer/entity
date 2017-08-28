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
    private $entityName;
    private $entityDados;
    private $title;
    private $erro;

    public function __construct($file)
    {
            $this->loadEntityByFileName($file);
    }

    public function insertDataEntity($arrayDataEntity)
    {
        if(is_array($arrayDataEntity)) {
            $this->setEntityArray($arrayDataEntity, $this->entityDados);
        }elseif(Check::json($arrayDataEntity)) {
            $this->setEntityArray(json_decode($arrayDataEntity, true), $this->entityDados);
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
     * @return mixed
     */
    public function getJsonStructEntity()
    {
        return $this->entityDados;
    }

    private function loadEntityByFileName($file)
    {
        if (file_exists(PATH_HOME . "sql/entities_worked/" . $file . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "sql/entities_worked/" . $file . '.json'), $file, true);

        } elseif (file_exists(PATH_HOME . "sql/entities/" . $file . '.json')) {
            $this->loadEntityByJsonString(file_get_contents(PATH_HOME . "sql/entities/" . $file . '.json'), $file);

        } else {
            Helper::createFolderIfNoExist(PATH_HOME . "sql");
            Helper::createFolderIfNoExist(PATH_HOME . "sql/entities");
            $this->erro = "os arquivos json para serem carregados devem ficar na pasta 'sql/entities/'";

        }
    }

    private function loadEntityByJsonString($json, $fileName = null, $worked = false)
    {
        if (isset($fileName) && !empty($fileName)) {
            $this->entityName = $fileName;
        } else {
            foreach ($json as $table => $dados) {
                $this->entityName = $table;
                break;
            }
        }

        if(!$this->entityName) {
            $this->erro = "nome da entidade não encontrada";
        }

        if(!$worked && !$this->erro){
            $this->entityDados = $this->autoLoadInfo(json_decode($json, true));
            $this->createEntityWorked();
            parent::createStorageEntity($this->entityName, $this->entityDados);
        } else if(!$this->erro) {
            parent::setTable($this->entityName);
            $this->entityDados = json_decode($json, true);
        }
    }

    private function createEntityWorked()
    {
        Helper::createFolderIfNoExist(PATH_HOME . "sql/entities_worked");
        $fp = fopen(PATH_HOME . "sql/entities_worked/" . $this->entityName . '.json', "w");
        fwrite($fp, json_encode($this->entityDados));
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