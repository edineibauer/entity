<?php

namespace Entity;

use ConnCrud\Read;
use EntityForm\Dicionario;
use EntityForm\Metadados;

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
     * @param string $entity
     * @param mixed $id
     * @param bool $check
     * @return bool
     */
    public static function checkPermission(string $entity, $id = null, bool $check = true)
    {
        $login = $_SESSION['userlogin'] ?? null;
        $allowCreate = file_exists(PATH_HOME . "_config/entity_not_show.json") ? json_decode(file_get_contents(PATH_HOME . "_config/entity_not_show.json"), true) : null;

        if (empty($login)) {
            //Anônimo
            //Nega Alterações para todas as Entidades e Permite criações somente na lista de permissão.
            return (!$id && !empty($allowCreate) && in_array($entity, $allowCreate));

        } else {
            //Logado
            //Bloqueia Alterações ou Criação em entidades selecionadas para o setor do usuário
            if (!empty($allowCreate[$login['setor']]) && in_array($entity, $allowCreate[$login['setor']]))
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
                            $read->exeRead("usuarios", "WHERE id = :id", "id={$idData}");
                            if ($read->getResult() && $login['setor'] >= $read->getResult()[0]['setor'])
                                return false;
                        }
                    }
                }
            }

            //permite caso a verificação esteja desativada ou se for criação ou se a entidade não possui publisher
            if (!$check || !$id || ($entity !== "usuarios" && empty($dicionario->getInfo()['publisher'])))
                return true;

            $read = new Read();
            $read->exeRead(PRE . $entity, "WHERE id = :id", "id={$id}");
            if ($read->getResult()) {
                if ($entity !== "usuarios") {

                    $dados = $read->getResult()[0];
                    $metadados = Metadados::getDicionario($entity);

                    if ($login['id'] == $dados[$metadados[$dicionario->getInfo()['publisher']]['column']])
                        return true;

                    $read->exeRead("usuarios", "WHERE id = :idl", "idl={$dados[$metadados[$dicionario->getInfo()['publisher']]['column']]}");
                    if ($read->getResult())
                        $publisher = $read->getResult()[0];
                } else {
                    $publisher = $read->getResult()[0];
                }

                if (isset($publisher)) {
                    if ($login['setor'] < $publisher['setor'] || $login['id'] === $publisher['id'])
                        return true;

                    if ($login['setor'] == $publisher['setor'] && $login['nivel'] < $publisher['nivel'])
                        return true;
                }
            }

            return false;
        }
    }
}