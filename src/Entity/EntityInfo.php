<?php
/**
 * Created by PhpStorm.
 * User: nenab
 * Date: 22/08/2017
 * Time: 22:21
 */

namespace Entity;

class EntityInfo
{
    private $entityName;
    private $entityDados;
    private $erro;

    public function __construct($entity)
    {
        $this->entityName = $entity;
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
    public function getJsonInfoEntity()
    {
        return $this->entityDados;
    }

    private function loadStart()
    {
        if(!$this->checkExistInfo()) {
            new Entity($this->entityName);

            if(!$this->checkExistInfo()) {
                $this->erro = "entidade não encontrada.";
            }
        }
    }

    private function checkExistInfo()
    {
        if (file_exists(PATH_HOME . "entity/cache/" . $this->entityName . '_info.json')) {
            $this->entityDados = json_decode(file_get_contents(PATH_HOME . "entity/cache/" . $this->entityName . '_info.json'), true);
            return true;
        }

        return false;
    }
}