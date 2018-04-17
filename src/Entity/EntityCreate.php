<?php

namespace Entity;


use ConnCrud\Create;
use ConnCrud\Delete;
use ConnCrud\Read;
use ConnCrud\Update;
use EntityForm\Dicionario;
use EntityForm\Meta;
use EntityForm\Metadados;
use EntityForm\Validate;
use Helpers\Check;

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
            self::storeData($dicionario);

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

    /**
     * @param Dicionario $d
     */
    private static function validateData(Dicionario $d)
    {
        foreach ($d->getListas() as $meta)
            self::checkSelecaoUnique($meta);

        foreach ($d->getAssociationSimple() as $meta)
            self::checkDataOne($meta);

        foreach ($d->getAssociationMult() as $meta)
            self::checkDataMult($meta);

        if ($d->getPublisher()) {
            if (!empty($_SESSION['userlogin']))
                $d->search($d->getPublisher())->setValue($_SESSION['userlogin']['id']);
            else
                $d->search($d->getPublisher())->setError("precisa estar logado para editar");
        }
    }

    /**
     * Armazena data no banco
     *
     * @param Dicionario $dicionario
     * @return mixed
     */
    private static function storeData(Dicionario $dicionario)
    {
        $id = $dicionario->search(0);
        if (!empty($id->getValue()))
            self::updateTableData($dicionario);
        else
            self::createTableData($dicionario);

        if (!empty($id->getValue()))
            self::createRelationalData($dicionario);

        return $id->getValue();
    }

    /**
     * Valida informações submetidas por um campo multiplo selecionado de um campo relacional
     * ex: selecione uma pessoa, agora selecione um dos endereços dessa pessoa, este endereço selecionado é validado.
     *
     * @param Meta $meta
     */
    private static function checkSelecaoUnique(Meta $meta)
    {
        if (!empty($meta->getSelect())) {
            //            foreach ($meta->getSelect() as $select)
            //                $dataValidada[$select . "__" . $meta->getColumn()] = (is_numeric($dados[$select . "__" . $meta->getColumn()]) && $dados[$select . "__" . $meta->getColumn()] > 0 ? (int)$dados[$select . "__" . $meta->getColumn()] : null);
        }
    }

    /**
     *
     * @param Meta $m
     */
    private static function checkDataOne(Meta $m)
    {
        if (!empty($m->getValue()) && is_array($m->getValue()))
            $m->setValue(Entity::add($m->getRelation(), $m->getValue()));

        elseif (!empty($m->getValue()) && is_int($m->getValue()))
            $m->setValue((int)$m->getValue());
    }


    /**
     * @param Dicionario $d
     */
    private static function createRelationalData(Dicionario $d)
    {
        $create = new Create();
        $del = new Delete();
        $id = $d->search(0)->getValue();
        if (!empty($d->getAssociationMult())) {
            foreach ($d->getAssociationMult() as $meta) {
                if (!empty($meta->getValue())) {
                    $entityRelation = PRE . $d->getEntity() . "_" . $meta->getRelation() . "_" . $meta->getColumn();
                    $del->exeDelete($entityRelation, "WHERE {$d->getEntity()}_id = :eid", "eid={$id}");
                    $listId = [];
                    foreach (json_decode($meta->getValue(), true) as $idRelation) {
                        if (!in_array($idRelation, $listId)) {
                            $listId[] = $idRelation;
                            $create->exeCreate($entityRelation, [$d->getEntity() . "_id" => $id, $meta->getRelation() . "_id" => $idRelation]);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Dicionario $d
     * @return mixed
     */
    private static function createTableData(Dicionario $d)
    {
        if (!$d->getError()) {

            $create = new Create();
            $create->exeCreate($d->getEntity(), array_filter($d->getData(), function ($var)
            {
                return ($var !== NULL && $var !== FALSE && $var !== '');
            }));

            $d->search(0)->setValue((int)$create->getResult());
        }
    }

    /**
     * @param Dicionario $d
     * @return mixed
     */
    private static function updateTableData(Dicionario $d)
    {
        $id = $d->search(0)->getValue();
        if (Validate::update($d)) {
            $up = new Update();
            $up->exeUpdate($d->getEntity(), $d->getData(), "WHERE id = :id", "id={$id}");
            return $id;
        } else {
            $read = new Read();
            $read->exeRead($d->getEntity(), "WHERE id = :id", "id={$id}");
            return $read->getResult() ? $id : null;
        }
    }

    /**
     *
     * @param Meta $m
     */
    private static function checkDataMult(Meta $m)
    {
        if (!empty($m->getValue()) && !empty($m->getValue()[0]) && is_array($m->getValue()[0])) {
            foreach ($m->getValue() as $i => $dado)
                self::checkDataOne($m);
        }
    }

    /**
     * @param string $entity
     * @param Meta $m
     * @return mixed
     */
    private static function checkData(string $entity, Meta $m)
    {
        /*    $data[$dic['column']] = self::checkLink($data, $dic, $dicionario, $info);
            $data[$dic['column']] = self::checkDefaultSet($dic, $data[$dic['column']]);
            self::checkNullSet($entity, $dic, $data[$dic['column']]);
            self::checkType($entity, $dic, $data[$dic['column']]);
            self::checkSize($entity, $dic, $data[$dic['column']]);
            self::checkUnique($entity, $dic, $data[$dic['column']], $data['id'] ?? null);
            self::checkRegular($entity, $dic, $data[$dic['column']]);
            self::checkValidate($entity, $dic, $data[$dic['column']]);
            self::checkValues($entity, $dic, $data[$dic['column']]);

            if ($dic['key'] === 'link' && !empty(self::$error[$entity][$dic['column']])) {
                self::$error[$entity][$dicionario[$info['title']]['column']] = self::$error[$entity][$dic['column']];
                unset(self::$error[$entity][$dic['column']]);
            }

            return $data[$dic['column']];*/

        return $m;
    }

    /**
     * Verifica se o valor submetido é inválido ao desejado
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkNullSet(string $entity, array $dic, $value)
    {
        if ($dic['default'] === false && empty($value))
            self::$error[$entity][$dic['column']] = "informe um valor";
    }

    /**
     * Verifica se o campo é do tipo link, se for, linka o valor ao título
     *
     * @param array $dados
     * @param array $dic
     * @param array $dicionario
     * @param array $info
     * @return mixed
     */
    private static function checkLink(array $dados, array $dic, array $dicionario, array $info)
    {
        if ($dic['key'] === "link" && $info['title'] !== null && !empty($dados[$dicionario[$info['title']]['column']]))
            return Check::name($dados[$dicionario[$info['title']]['column']]);
        elseif ($dic['key'] === "link")
            return Check::name($dados[$dic['column']]);

        return $dados[$dic['column']];
    }

    /**
     * Verifica se o tipo do campo é o desejado
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkType(string $entity, array $dic, $value)
    {
        if (!empty($value)) {
            if (in_array($dic['type'], array("tinyint", "smallint", "mediumint", "int", "bigint"))) {
                if (!is_numeric($value))
                    self::$error[$entity][$dic['column']] = "número inválido";

            } elseif ($dic['type'] === "decimal") {
                $size = (!empty($dic['size']) ? explode(',', str_replace(array('(', ')'), '', $dic['size'])) : array(10, 30));
                $val = explode('.', str_replace(',', '.', $value));
                if (strlen($val[1]) > $size[1])
                    self::$error[$entity][$dic['column']] = "valor das casas decimais excedido. Max {$size[1]}";
                elseif (strlen($val[0]) > $size[0])
                    self::$error[$entity][$dic['column']] = "valor inteiro do valor decimal excedido. Max {$size[0]}";

            } elseif (in_array($dic['type'], array("double", "real", "float"))) {
                if (!is_numeric($value))
                    self::$error[$entity][$dic['column']] = "valor não é um número";

            } elseif (in_array($dic['type'], array("bit", "boolean", "serial"))) {
                if (!is_bool($value))
                    self::$error[$entity][$dic['column']] = "valor boleano inválido. (true ou false)";

            } elseif (in_array($dic['type'], array("datetime", "timestamp"))) {
                if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $value))
                    self::$error[$entity][$dic['column']] = "formato de data inválido ex válido:(2017-08-23 21:58:00)";

            } elseif ($dic['type'] === "date") {
                if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $value))
                    self::$error[$entity][$dic['column']] = "formato de data inválido ex válido:(2017-08-23)";

            } elseif ($dic['type'] === "time") {
                if (!preg_match('/\d{2}:\d{2}/i', $value))
                    self::$error[$entity][$dic['column']] = "formato de tempo inválido ex válido:(21:58)";

            } elseif ($dic['type'] === "json") {
                if (!is_string($value))
                    self::$error[$entity][$dic['column']] = "formato json inválido";
            }
        }
    }

    /**
     * Verifica se o tamanho do valor corresponde ao desejado
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkSize(string $entity, array $dic, $value)
    {
        if ($dic['size']) {
            if ($dic['type'] === "varchar" && strlen($value) > $dic['size'])
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "char" && strlen($value) > 1)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "tinytext" && strlen($value) > 255)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "text" && strlen($value) > 65535)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "mediumtext" && strlen($value) > 16777215)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "longtext" && strlen($value) > 4294967295)
                self::$error[$entity][$dic['column']] = "tamanho máximo de caracteres excedido. Max {$dic['size']}";

            elseif ($dic['type'] === "tinyint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 8) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "smallint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 16) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "mediumint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 24) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "int" && !in_array($dic['key'], ["extend", "list", "list_mult", "selecao", "selecao_mult", "extend_mult"]) && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 32) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);

            elseif ($dic['type'] === "bigint" && ($value > (pow(2, ($dic['size'] * 2)) - 1) || $value > (pow(2, 64) - 1)))
                self::$error[$entity][$dic['column']] = "numero excedeu seu limite. Max " . (pow(2, ($dic['size'] * 2)) - 1);
        }
    }

    /**
     * Verifica se o valor precisa ser único
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     * @param mixed $id
     */
    private static function checkUnique(string $entity, array $dic, $value, $id = null)
    {
        if ($dic['unique']) {
            $read = new Read();
            $read->exeRead(PRE . $entity, "WHERE {$dic['column']} = '{$value}'" . ($id ? " && id != " . $id : ""));
            if ($read->getResult())
                self::$error[$entity][$dic['column']] = "valor já existe, digite outro";
        }
    }

    /**
     * Verifica se existe expressão regular, e se existe, aplica a verificação
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkRegular(string $entity, array $dic, $value)
    {
        if (!empty($dic['allow']['regex']) && !empty($value) && is_string($value) && !preg_match($dic['allow']['regex'], $value))
            self::$error[$entity][$dic['column']] = "formato não permitido.";
    }

    /**
     * Verifica se o campo precisa de validação pré-formatada
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkValidate(string $entity, array $dic, $value)
    {
        if (!empty($dic['allow']['validate']) && !empty($value)) {
            switch ($dic['allow']['validate']) {
                case 'email':
                    if (!Check::email($value))
                        self::$error[$entity][$dic['column']] = "email inválido.";
                    break;
                case 'cpf':
                    if (!Check::cpf($value))
                        self::$error[$entity][$dic['column']] = "CPF inválido.";
                    break;
                case 'cnpj':
                    if (!Check::cnpj($value))
                        self::$error[$entity][$dic['column']] = "CNPJ inválido.";
                    break;
            }
        }
    }

    /**
     * Verifica se existem valores exatos permitidos
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $value
     */
    private static function checkValues(string $entity, array $dic, $value)
    {
        if ($dic['type'] === "json") {
            if (!empty($value) && !empty($dic['allow']['values'])) {
                if (in_array($dic['format'], ["sources", "source"])) {
                    foreach (json_decode($value, true) as $v) {
                        if (!in_array(pathinfo($v['url'], PATHINFO_EXTENSION), $dic['allow']['values']))
                            self::$error[$entity][$dic['column']] = "valor não é permitido";
                    }
                } else {
                    foreach (json_decode($value, true) as $item) {
                        if (!empty($item) && !in_array($item, $dic['allow']['values']))
                            self::$error[$entity][$dic['column']] = "valor não é permitido";
                    }
                }
            }
        } else {
            if (!empty($dic['allow']['values']) && !empty($value) && !in_array($value, $dic['allow']['values']))
                self::$error[$entity][$dic['column']] = "valor não é permitido";
        }
    }
}