<?php

namespace Entity;

use ConnCrud\Create;
use ConnCrud\Read;
use ConnCrud\Update;
use Helpers\Check;

class DataBase
{
    private $erro;

    /**
     * @param mixed $erro
     * @param int $linha
     * @param mixed $coluna
     */
    protected function setErro(string $erro, int $linha, string $coluna)
    {
        $this->erro[$coluna] = $erro . " #linha {$linha}.";
    }

    /**
     * Adiciona dados a uma entidade
     * @param Entity $data
     * @return int $id
     */
    public function add(Entity $data): int
    {
        return $this->addDataEntity($data);
    }

    /**
     * Deleta dados de uma entidade
     * @param Mixed $entityId
     */
    public function remove($entityId)
    {
        $this->callFunctionMultData('removeData', $entityId);
    }

    /**
     * Retorna uma entidade a partir de um id ou a partir de um filtro de entidade
     * @param Mixed $id
     * @return Entity
     */
    public function get($id): Entity
    {
        $entity = new Entity();

        return $entity;
    }

    private function callFunctionMultData($func, $ids)
    {
        if (is_array($ids)) {
            array_map($func, $ids);
        } else {
            $this->$func($ids);
        }
    }

    private function addDataEntity(Entity $data)
    {
        $id = null;
        $info = Metadados::getStructInfo($data->getEntity());

        foreach ($data->getData() as $column => $value) {
            $data->$column = $this->checkValueColumn($data, $column, $value, $info);
        }

        if(!$this->erro) {
            if ($data->$info['info']['primary']) {
                $id = $this->updateEntity($data, $info);
            } else {
                $id = $this->createEntity($data, $info);
            }
        }

        $this->createRelationalInfo($data, $id, $info['info']);

        return $id;
    }

    /**
     * @param Entity $data
     * @param string $column
     * @param mixed $value
     * @param array $info
     * @return mixed
     */
    private function checkValueColumn(Entity $data, string $column, $value, array $info)
    {
        try {
            $field = $info['struct'][$column];
            if (isset($field['link']) && !empty($field['link'])) {
                $value = Check::name($data->$field['link']);
            }
            $this->checkUnique($data, $column, $value, $info);

            return $value;

        } catch (\Exception $e) {
            $this->setErro($e->getMessage(), $e->getLine(), $column);
        }
    }

    private function checkUnique(Entity $data, string $column, $value, array $info)
    {
        if ($info['struct'][$column]['unique']) {
            $read = new Read();
            $read->exeRead($data->getEntity(), "WHERE {$column} = '{$value}'" . ($data->$info['info']['primary'] ? " && {$info['info']['primary']} != {$data->$info['info']['primary']}" : ""));
            if ($read->getResult()) {
                throw new \Exception("campo precisa ser único");
            }
        }
    }

    private function updateEntity(Entity $data, array $info)
    {
        $update = new Update();
        $update->exeUpdate($data->getEntity(), $this->getDadosEntity($data, $info['info']), "WHERE {$info['info']['primary']} = :id", "id={$data->$info['info']['primary']}");
        if($update->getResult()) {
            return $data->$info['info']['primary'];
        }

        return null;
    }

    private function getDadosEntity(Entity $data, array $info) :array
    {
        $dados = [];
        foreach ($data->getData() as $column => $value) {
            if($info['primary'] !== $column && !in_array($column, $info['extend_mult']) && !in_array($column, $info['list_mult'])) {
                if(in_array($column, $info['extend']) && in_array($column, $info['list'])) {
                    $dataEntityFk = new DataBase();
                    $dados[$column] = $dataEntityFk->add($value);
                } else {
                    $dados[$column] = $value;
                }
            }
        }

        return $dados;
    }

    private function createEntity(Entity $data, array $info)
    {
        $create = new Create();
        $create->exeCreate($data->getEntity(), $this->getDadosEntity($data, $info['info']));
        if($create->getResult()) {
            return $create->getResult();
        }

        return null;
    }

    private function createRelationalInfo(Entity $data, int $id, array $info)
    {
        if($id && !empty($info['extend_mult']) || !empty($info['list_mult'])) {
            $create = new Create();
            foreach (array_merge($info['extend_mult'], $info['list_mult']) as $column) {
                foreach ($data->$column as $entityExtend) {
                    $database = new DataBase();
                    $relational[$entityExtend->getEntity() . "_id"] = $database->add($entityExtend);
                    $relational[$data->getEntity() . "_id"] = $id;
                    if($relational[$entityExtend->getEntity() . "_id"]) {
                        $create->exeCreate($data->getEntity() . "_" . $entityExtend->getEntity(), $relational);
                    }
                    unset($relational);
                }
            }
        }
    }

    private function removeData($id)
    {

    }
}