<?php

namespace Entity;

abstract class EntityCreate extends EntityRead
{
    /**
     * Salva data à uma entidade
     *
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @param mixed $callback
     * @return mixed
     */
    protected static function exeCreate(string $entity, array $data, bool $save)
    {
        $dicionario = new Dicionario($entity);
        $dicionario->setData($data);

        if ($save)
            $dicionario->save();

        return self::return($dicionario);
    }

    /**
     * @param Dicionario $dicionario
     * @param bool $save
     * @return mixed
     */
    private static function return(Dicionario $dicionario)
    {
        $error = null;
        foreach ($dicionario->getDicionario() as $meta) {
            if (!empty($meta->getError())) {
                if(is_array($meta->getError())) {
                    foreach ($meta->getError() as $column => $value)
                        $error[$dicionario->getEntity()][$meta->getRelation()][$column] = $value;
                } else {
                    $error[$dicionario->getEntity()][$meta->getColumn()] = $meta->getError();
                }
            }
        }

        return $error ?? (int)$dicionario->search("id")->getValue();
    }
}