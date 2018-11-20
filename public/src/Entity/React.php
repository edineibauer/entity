<?php

namespace Entity;

use Helpers\Helper;

class React
{
    /**
     * React constructor.
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $dadosOld
     */
    public function __construct(string $action, string $entity, array $dados, array $dadosOld = [])
    {
        $this->log($action, $entity, $dados);

        /* CRUD REACT DEFAULT */
        if (file_exists(PATH_HOME . "public/react/{$entity}/{$action}.php"))
            include PATH_HOME . "public/react/{$entity}/{$action}.php";

        /* CRUD REACT SETOR DEFAULT */
        if (!empty($_SESSION['userlogin']['setor']) && file_exists(PATH_HOME . "public/react/{$entity}/{$_SESSION['userlogin']['setor']}/{$action}.php"))
            include PATH_HOME . "public/react/{$entity}/{$_SESSION['userlogin']['setor']}/{$action}.php";

        foreach (Helper::listFolder(PATH_HOME . VENDOR) as $lib) {
            if (file_exists(PATH_HOME . VENDOR . "{$lib}/react/{$entity}/{$action}.php"))
                include PATH_HOME . VENDOR . "{$lib}/react/{$entity}/{$action}.php";

            if (!empty($_SESSION['userlogin']['setor']) && file_exists(PATH_HOME . VENDOR . "{$lib}/react/{$entity}/{$_SESSION['userlogin']['setor']}/{$action}.php"))
                include PATH_HOME . VENDOR . "{$lib}/react/{$entity}/{$_SESSION['userlogin']['setor']}/{$action}.php";
        }
    }

    /**
     * Cria log da atividade executada.
     * @param string $action
     * @param string $entity
     * @param array $dados
     * @param array $dadosOld
     */
    public function log(string $action, string $entity, array $dados)
    {
        $store = new Json("store/{$entity}");

        if(!empty($_SESSION['userlogin']))
            $dados['userlogin'] = $_SESSION['userlogin']['id'];

        if($action === "delete")
            $store->delete($dados['id']);
        else
            $store->save($dados['id'], $dados);
    }
}