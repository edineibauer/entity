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
    private $library;
    private $entityName;
    private $entityDados;
    private $erro;

    public function __construct($entity, $library = null)
    {
        $this->entityName = $entity;
        if($library) {
            $this->setLibrary($library);
        }
    }

    /**
     * @param mixed $library
     */
    public function setLibrary($library)
    {
        $this->library = $library;
        $this->loadStart();
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
        if ($this->library) {
            if (file_exists(PATH_HOME . "sql/entities_worked/" . $this->entityName . '_info.json')) {
                $this->entityDados = json_decode(file_get_contents(PATH_HOME . "sql/entities_worked/" . $this->entityName . '_info.json'), true);

            } else {
                new Entity($this->entityName);

                if (file_exists(PATH_HOME . "sql/entities_worked/" . $this->entityName . '_info.json')) {
                    $this->entityDados = json_decode(file_get_contents(PATH_HOME . "sql/entities_worked/" . $this->entityName . '_info.json'), true);
                } else {
                    $this->erro = "os arquivos json para serem carregados devem ficar na pasta 'sql/entities/'";
                }
            }
        } else {
            $this->erro = "Informe a biblioteca destino desta entidade";
        }
    }
}