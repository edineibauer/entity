<?php

namespace Entity;

use ConnCrud\Read;

class Entity extends EntityCreate
{
    /**
     * Le a data de uma entidade de forma extendida
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $recursive
     * @return mixed
     */
    public static function read(string $entity, $data = null)
    {
        return self::exeRead($entity, $data);
    }

    /**
     * Salva data à uma entidade
     *
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @param mixed $callback
     * @return mixed
     */
    public static function add(string $entity, array $data, bool $save = true)
    {
        return self::exeCreate($entity, $data, $save);
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $checkPermission
     */
    public static function delete(string $entity, $data, bool $checkPermission = true)
    {
        self::exeDelete($entity, $data, $checkPermission);
    }

    /**
     * Deleta informações de uma entidade
     *
     * @param string $entity
     * @param mixed $data
     * @param bool $checkPermission
     * @return mixed
     */
    public static function copy(string $entity, $data, bool $checkPermission = true)
    {
        return self::exeCopy($entity, $data, $checkPermission);
    }

    /**
     * Retorna o dicionário da entidade ou então a lista de dicionário de entidades permitida
     * @param string|null $entity
     */
    public static function dicionario(string $entity = null)
    {
        if(file_exists(PATH_HOME . "entity/cache") && (empty($entity) || file_exists(PATH_HOME . "entity/cache/{$entity}.json"))) {
            if(empty($entity)){

                //read all dicionarios
                $list = [];
                foreach (\Helpers\Helper::listFolder(PATH_HOME . "entity/cache") as $json) {
                    if(preg_match('/^\w+\.json$/i', $json)){
                        $entity = str_replace('.json', '', $json);
                        if($dic = self::dicionario($entity))
                            $list[$entity] = $dic;
                    }
                }

                return $list;

            } else {

                //read dicionario específico
                $setor = empty($_SESSION['userlogin']['setor']) ? 0 : $_SESSION['userlogin']['setor'];
                $entidadesNaoPermitidas = \Config\Config::getEntityNotAllow();
                $entidadesNaoPermitidas = isset($entidadesNaoPermitidas[$setor]) ? $entidadesNaoPermitidas[$setor] : [];

                if(!in_array($entity, $entidadesNaoPermitidas)) {
                    return \Entity\Metadados::getDicionario($entity, true, true);
                }
            }
        }

        return null;
    }

    /**
     * @param string $entity
     * @param mixed $id
     * @param bool $check
     * @return bool
     */
    public static function checkPermission(string $entity, $id = null, bool $check = true): bool
    {
        $login = $_SESSION['userlogin'] ?? null;

        //permissão master
        if(!empty($login['setor']) && $login['setor'] <= ADM)
            return true;

        $allowCreate = file_exists(PATH_HOME . "_config/entity_not_show.json") ? json_decode(file_get_contents(PATH_HOME . "_config/entity_not_show.json"), true) : null;

        if (!$login) {
            //Anônimo tem permissão para criar caso não esteja na lista negra
            return (!$id && !in_array($entity, $allowCreate[0]));

        } else {
            //Logado
            //Bloqueia Alterações ou Criação em entidades selecionadas para o setor do usuário
            if (!empty($allowCreate[$login['setor']]) && in_array($entity, $allowCreate[$login['setor']]) && $id)
                return false;

            $dicionario = new Dicionario($entity);
            $read = new Read();

            //check associação simples if have entity usuários ou if have publisher
            $read->exeRead($entity, "WHERE id = :id", "id={$id}");
            if ($read->getResult()) {
                $tableData = $read->getResult()[0];
                foreach ($dicionario->getAssociationSimple() as $meta) {
                    if ($meta->getRelation() === "usuarios" || $meta->getKey() === "publisher") {
                        $idData = $tableData[$meta->getColumn()];
                        if (!empty($idData) && $idData != $login['id']) {

                            $continua = true;
                            $general = json_decode(file_get_contents(PATH_HOME . "entity/general/general_info.json"), true);
                            if (!empty($general[$entity]['owner']) || !empty($general[$entity]['ownerPublisher'])) {
                                foreach (array_merge($general[$entity]['owner'] ?? [], $general[$entity]['ownerPublisher'] ?? []) as $item) {
                                    $entityRelation = $item[0];
                                    $column = $item[1];
                                    $userColumn = $item[2];
                                    $tableRelational = PRE . $entityRelation . "_" . $entity . "_" . $column;

                                    $read = new Read();
                                    $read->exeRead($entityRelation, "WHERE {$userColumn} = :user", "user={$_SESSION['userlogin']['id']}");
                                    if ($read->getResult()) {
                                        $idUser = $read->getResult()[0]['id'];
                                        $read->exeRead($tableRelational, "WHERE {$entityRelation}_id = :id", "id={$idUser}");
                                        if ($read->getResult())
                                            $continua = false;
                                    }
                                }
                            }

                            if ($continua) {
                                $read->exeRead("usuarios", "WHERE id = :id", "id={$idData}");
                                if ($read->getResult() && $login['setor'] >= $read->getResult()[0]['setor'])
                                    return false;
                            }
                        }
                    }
                }
            }

            //permite caso a verificação esteja desativada ou se for criação ou se a entidade não possui publisher
            if (!$check || !$id || ($entity !== "usuarios" && empty($dicionario->getInfo()['publisher'])))
                return true;

            if (isset($tableData)) {
                if ($entity !== "usuarios") {

                    $metadados = Metadados::getDicionario($entity);

                    if ($login['id'] == $tableData[$metadados[$dicionario->getInfo()['publisher']]['column']])
                        return true;

                    $read->exeRead("usuarios", "WHERE id = :idl", "idl={$tableData[$metadados[$dicionario->getInfo()['publisher']]['column']]}");
                    if ($read->getResult() && (($login['setor'] == $read->getResult()[0]['setor'] && $login['nivel'] < $read->getResult()[0]['nivel']) || $login['id'] === $read->getResult()[0]['id']))
                        return true;
                    else
                        return false;
                }

                if (($login['setor'] == $tableData['setor'] && $login['nivel'] < $tableData['nivel']) || $login['id'] === $tableData['id'] || $tableData['setor'] > ADM)
                    return true;
            }

            return false;
        }
    }
}