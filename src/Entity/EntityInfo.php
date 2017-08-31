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
    private $entityDados;
    private $erro;

    public function __construct($file)
    {
        $this->loadEntityByFileName($file);
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

    private function loadEntityByFileName($file)
    {
        if (file_exists(PATH_HOME . "sql/entities_worked/" . $file . '_info.json')) {
            $this->entityDados = json_decode(file_get_contents(PATH_HOME . "sql/entities_worked/" . $file . '_info.json'), true);

        } else {
            new Entity($file);

            if (file_exists(PATH_HOME . "sql/entities_worked/" . $file . '_info.json')) {
                $this->entityDados = json_decode(file_get_contents(PATH_HOME . "sql/entities_worked/" . $file . '_info.json'), true);
            } else {
                $this->erro = "os arquivos json para serem carregados devem ficar na pasta 'sql/entities/'";
            }
        }
    }
}