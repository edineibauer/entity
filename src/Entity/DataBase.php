<?php

namespace Entity;

use ConnCrud\Create;
use ConnCrud\Delete;
use ConnCrud\Read;
use ConnCrud\Update;
use Helpers\Check;

class DataBase
{
    private static $erro;

    /**
     * Adiciona dados a uma entidade
     * @param Entity $data
     * @return mixed $id
     */
    public static function add(Entity $data)
    {
        self::$erro = null;
        return self::addDataEntity($data);
    }

    /**
     * Deleta dados de uma entidade
     * @param Entity $data
     */
    public static function delete(Entity $data)
    {
        self::$erro = null;
        self::deleteDataEntity($data);
    }

    /**
     * @param Entity $data
     * @return Entity
     */
    public static function copy(Entity $data) :Entity
    {
        self::$erro = null;
        return self::copyDataEntity($data);
    }

    /**
     * Retorna uma entidade a partir de um id
     * @param mixed $data
     * @param int $id
     * @return Entity
     */
    public static function get($data, int $id): Entity
    {
        self::$erro = null;
        if (is_string($data)) {
            $data = new Entity($data);
        }

        if (!is_object($data) || !is_a($data, "Entity\Entity")) {
            self::$erro = "não foi possível carregar a entidade passada. Envie uma entidade e um id.";
        } else {
            $data = self::getEntityValues($data, $id);
        }

        return $data;
    }

    /**
     * @return mixed
     */
    public static function getErro()
    {
        return self::$erro;
    }

    /**
     * Retorna uma entidade a partir de um id
     * @param mixed $data
     * @param int $id
     * @return Entity
     */
    private static function getEntityValues(Entity $data, int $id): Entity
    {
        $read = new Read();
        $read->exeRead($data->getEntity(), "WHERE " . $data->getMetadados()['info']['primary'] . " = :id", "id={$id}");
        if ($read->getResult()) {
            foreach ($read->getResult()[0] as $column => $value) {
                if (in_array($column, array("list", "extend"))) {
                    if ($value && !empty($data->getMetadados()['struct'][$column]['table'])) {
                        $data->set($column, DataBase::get($data->getMetadados()['struct'][$column]['table'], $value));
                    }
                } else {
                    $data->set($column, $value);
                }
            }

            $data = self::getRelationalDataForEntity($data);

        } else {
            self::$erro = "id = {$id} não encontrado na entidade {$data->getEntity()}.";
        }

        return $data;
    }

    /**
     * Retorna uma entidade com suas relações multiplas adicionadas,
     * requer uma entidade com id
     * @param Entity $data
     * @return Entity $data
     */
    private static function getRelationalDataForEntity(Entity $data): Entity
    {
        if ($data->get($data->getMetadados()['info']['primary'])) {
            $read = new Read();
            foreach ($data->systemGetRelationalColumn() as $column) {
                $table = $data->getMetadados()['struct'][$column]['table'];
                $read->exeRead(PRE . $data->getEntity() . "_" . $table, "WHERE " . $data->getEntity() . "_id = :fid", "fid={$data->get($data->getMetadados()['info']['primary'])}");
                if ($read->getResult()) {
                    foreach ($read->getResult() as $result) {
                        $data->set($column, DataBase::get($table, $result[$table . "_id"]));
                    }
                }
            }
        }

        return $data;
    }

    /**
     * adiciona dados para uma entidade
     * @param Entity $data
     * @return mixed
    */
    private static function addDataEntity(Entity $data)
    {
        $id = null;

        foreach ($data->systemGetEditableData() as $column => $value) {
            $data->set($column, self::checkValueColumn($data, $column, $value));
        }

        if (!self::$erro) {
            if ($data->get($data->getMetadados()['info']['primary'])) {
                $id = self::updateEntity($data);
            } else {
                $id = self::createEntity($data);
            }

            self::createRelationalInfo($data, $id);
        }

        return $id;
    }

    /**
     * @param Entity $data
     * @param string $column
     * @param mixed $value
     * @return mixed
     */
    private static function checkValueColumn(Entity $data, string $column, $value)
    {
        try {
            $field = $data->getMetadados()['struct'][$column];

            if(!in_array($field['key'], array('list', 'extend'))) {
                if (isset($field['link']) && !empty($field['link'])) {
                    $value = Check::name($data->get($field['link']));
                }

                self::checkUnique($data, $column, $value);
            }

            $value = self::checkNull($data, $column, $value);

        } catch (\Exception $e) {
            self::setErro($e->getMessage(), $e->getLine(), $column);

        } finally {

            return $value;
        }
    }

    /**
     * Verifica se o campo pode ficar vazio, se não, verifica se esta vazio e retorna erro
     * @param Entity $data
     * @param string $column
     * @param mixed $value
     * @return mixed
     * @throws \Exception
     */
    private static function checkNull(Entity $data, string $column, $value)
    {
        $info = $data->getMetadados()['struct'][$column];
        if (!$info['null'] && ($value === "" || is_null($value))) {
            throw new \Exception("campo precisa ser preenchido");

        } elseif ($info['null'] && $value === "") {
            $value = null;
        }

        return $value;
    }

    /**
     * Verifica se possue campos únicos na entidade, se possuir, verifica se é único
     * senão, retorna erro e não prossegue com a operação
     * @param Entity $data
     * @param string $column
     * @param mixed $value
     * @throws \Exception
     */
    private static function checkUnique(Entity $data, string $column, $value)
    {
        $info = $data->getMetadados();
        if ($info['struct'][$column]['unique']) {
            $read = new Read();
            $read->exeRead($data->getEntity(), "WHERE {$column} = '{$value}'" . ($data->get($info['info']['primary']) ? " && {$info['info']['primary']} != {$data->get($info['info']['primary'])}" : ""));
            if ($read->getResult()) {
                throw new \Exception("campo precisa ser único");
            }
        }
    }

    /**
     * Atualiza os dados de uma entidade
     * @param Entity $data
     * @return mixed
     */
    private static function updateEntity(Entity $data)
    {
        $primary = $data->getMetadados()['info']['primary'];
        $read = new Read();
        $read->exeRead($data->getEntity(), "WHERE {$primary} = :pid", "pid={$data->get($primary)}");
        if ($read->getResult()) {
            $update = new Update();
            $update->exeUpdate($data->getEntity(), self::getDadosEntity($data), "WHERE {$primary} = :id", "id={$data->get($primary)}");
        } else {
            return self::createEntity($data);
        }

        return ($update->getResult() ? $data->get($primary) : null);
    }

    /**
     * cria dados em uma entidade
     * @param Entity $data
     * @return mixed
     */
    private static function createEntity(Entity $data)
    {
        $create = new Create();
        $create->exeCreate($data->getEntity(), self::getDadosEntity($data));

        return ($create->getResult() ? $create->getResult() : null);
    }

    /**
     * obtem os dados de uma entidade,
     * cria entidades extend e list e retorna o id de relacionamento, deixando preparado os dados para armazenamento
     * @param Entity $data
     * @return array
     */
    private static function getDadosEntity(Entity $data): array
    {
        $dados = [];
        foreach ($data->systemGetEditableData() as $column => $value) {
            if (in_array($data->getMetadados()['struct'][$column]['key'], array('extend', 'list'))) {
                if (!empty($value) && is_object($value) && is_a($value, "Entity\Entity")) {
                    $dados[$column] = DataBase::add($value);
                }
            } else {
                $dados[$column] = $value;
            }
        }

        return $dados;
    }

    /**
     * Cria as relações mult de uma entidade
     * @param Entity $data
     * @param mixed $id
     */
    private static function createRelationalInfo(Entity $data, $id = null)
    {
        if ($id && $id > 0) {
            $create = new Create();
            $read = new Read();
            foreach ($data->systemGetRelationalColumn() as $column) {
                if (!empty($column)) {
                    foreach ($data->get($column) as $entityExtend) {
                        $relational[$entityExtend->getEntity() . "_id"] = $entityExtend->save();
                        $relational[$data->getEntity() . "_id"] = $id;
                        if ($relational[$entityExtend->getEntity() . "_id"]) {
                            $read->exeRead(PRE . $data->getEntity() . "_" . $entityExtend->getEntity(), "WHERE " . $entityExtend->getEntity() . "_id = :fid && " . $data->getEntity() . "_id = :gid", "fid={$relational[$entityExtend->getEntity() . "_id"]}&gid={$id}");
                            if (!$read->getResult()) {
                                $create->exeCreate(PRE . $data->getEntity() . "_" . $entityExtend->getEntity(), $relational);
                            }
                        }
                        unset($relational);
                    }
                }
            }
        }
    }

    /**
     * Copia os dados de uma entidade, criando uma replica
     * @param Entity $data
     * @return Entity
    */
    private static function copyDataEntity(Entity $data) :Entity
    {
        $data->set($data->getMetadados()['info']['primary'], null);

        if (!empty($data->getMetadados()['info']['extend'])) {
            foreach ($data->getMetadados()['info']['extend'] as $column) {
                if(!empty($column) && is_object($data->get($column)) && is_a($data->get($column), "Entity\Entity")) {
                    $data->get($column)->duplicate();
                    $data->set($column, $data->get($column));
                }
            }
        }

        if (!empty($data->getMetadados()['info']['extend_mult'])) {
            foreach ($data->getMetadados()['info']['extend_mult'] as $column) {
                if(!empty($data->get($column)) && is_array($data->get($column))) {
                    $listExtend = [];
                    foreach ($data->get($column) as $extend) {
                        $extend->duplicate();
                        $listExtend[] = $extend;
                    }
                    $data->set($column, $listExtend);
                }
            }
        }

        //campos unicos modifica info
        if (!empty($data->getMetadados()['info']['unique'])) {
            foreach ($data->getMetadados()['info']['unique'] as $column) {
                if(!empty($column) && !in_array($data->getMetadados()['struct'][$column]['key'], array('primary', 'list', 'list_mult', 'extend', 'extend_mult'))) {
                    if (is_string($data->get($column))) {
                        $valor = $data->get($column);
                        $valor = (preg_match('/-cp--\d{1,4}/i', $valor) ? explode('-cp--', $valor)[0] : $valor) . "-cp--" . rand(1, 1000) . date("s") . date("i");
                        $data->set($column, $valor);

                    } elseif (is_numeric($data->get($column))) {
                        $data->set($column, (rand(0, 10000)) * -1);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Deleta dados de uma entidade
     * @param Entity $data
     */
    private static function deleteDataEntity(Entity $data)
    {
        if ($data->get($data->getMetadados()['info']['primary'])) {
            if (!empty($data->getMetadados()['info']['extend'])) {
                foreach ($data->getMetadados()['info']['extend'] as $column) {
                    if (!empty($column)) {
                        $extend = $data->get($column);
                        if (is_object($extend) && is_a($extend, 'Entity\Entity')) {
                            $extend->delete();
                            if ($extend->getErro()) {
                                self::$erro = $extend->getErro();
                            }
                            $del = true;
                        }
                    }
                }
            }

            if (!empty($data->getMetadados()['info']['extend_list'])) {
                foreach ($data->getMetadados()['info']['extend_list'] as $column) {
                    if (!empty($column)) {
                        foreach ($data->get($column) as $extendList) {
                            if (is_object($extendList) && is_a($extendList, 'Entity\Entity')) {
                                $extendList->delete();
                                if ($extendList->getErro()) {
                                    self::$erro = $extendList->getErro();
                                }
                            }
                        }
                    }
                }
            }

            if (!isset($del)) {
                $del = new Delete();
                $del->exeDelete($data->getEntity(), "WHERE " . $data->getMetadados()['info']['primary'] . " = :id", "id={$data->get($data->getMetadados()['info']['primary'])}");
                if (!$del->getResult()) {
                    self::$erro = $del->getErro();
                }
            }
        } else {
            self::$erro = "Não é possível deletar uma entidade ('{$data->getEntity()}') sem 'id'.";
        }
    }

    /**
     * @param mixed $erro
     * @param int $linha
     * @param mixed $coluna
     */
    private static function setErro(string $erro, int $linha, string $coluna)
    {
        self::$erro[$coluna] = $erro;
    }
}