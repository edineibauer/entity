<?php

namespace Entity;


use ConnCrud\Create;
use ConnCrud\Delete;
use ConnCrud\Read;
use ConnCrud\Update;
use EntityForm\Metadados;
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
        self::$error = null;

        if (!Check::isAssoc($data)) {
            $result = [];
            foreach ($data as $datum)
                $result[] = self::addData($entity, $datum, $save);

            return $result;
        } else {

            return self::addData($entity, $data, $save);
        }
    }

    /**
     * @param string $entity
     * @param array $data
     * @param bool $save
     * @return mixed
     */
    private static function addData(string $entity, array $data, bool $save)
    {
        $id = null;
        $info = Metadados::getInfo($entity);
        $dicionario = Metadados::getDicionario($entity);

        $data = self::validateData($entity, $data, $info, $dicionario);

        if ($save) {
            if (self::$error && !empty($data['id']) && $data['id'] > 0)
                $data = self::removeWrongValueFromUpdate($data, $entity, $dicionario);

            $data = self::checkNivelUser($data, $entity);

            if (!self::$error || (!empty($data['id']) && $data['id'] > 0))
                $id = self::storeData($entity, $data, $info, $dicionario);

            return self::$error ?? $id;
        }

        return self::$error;
    }

    /**
     * @param string $entity
     * @param array $data
     * @param array $info
     * @param array $dicionario
     * @return array
     */
    private static function validateData(string $entity, array $data, array $info, array $dicionario): array
    {
        $dataR = !empty($data['id']) && $data['id'] > 0 ? ["id" => $data['id']] : [];
        foreach ($dicionario as $i => $dic) {
            if (empty($data['id']) || $dic['format'] === "link" || $dic['format'] === "publisher" || ($data['id'] > 0 && isset($data[$dic['column']]))) {
                $data[$dic['column']] = $data[$dic['column']] ?? null;

                if (in_array($dic['key'], ["extend_mult", "list", "selecao", "list_mult", "selecao_mult"]))
                    $dataR = self::checkSelecaoUnique($dataR, $dic, $data);

                if (in_array($dic['key'], ["extend", "list", "selecao"])) {
                    $dataR[$dic['column']] = self::checkDataOne($entity, $dic, $data[$dic['column']], $data['id']);

                } elseif (in_array($dic['key'], ["extend_mult", "list_mult", "selecao_mult"])) {
                    $dataR[$dic['column']] = self::checkDataMult($entity, $dic, $data[$dic['column']], $data['id']);

                } elseif ($dic['key'] === "publisher") {
                    if (empty($_SESSION['userlogin']))
                        self::$error[$entity]['id'] = "precisa estar logado para editar";
                    else
                        $dataR[$dic['column']] = $_SESSION['userlogin']['id'];

                } else {
                    $dataR[$dic['column']] = self::checkData($entity, $data, $dic, $dicionario, $info);
                }
            }
        }

        return $dataR;
    }

    /**
     * Remove valores com erros para salvar os corretos
     * @param array $data
     * @param string $entity
     * @param array $dic
     * @return array
     */
    private static function removeWrongValueFromUpdate(array $data, string $entity, array $dic): array
    {
        foreach (self::$error as $entidade => $dados) {
            foreach ($dados as $column => $mensagem) {
                unset($data[$column]);
            }
        }

        foreach ($dic as $i => $dataColumn) {
            if($dataColumn['key'] = "extend") {
                $read = new Read();
                $read->exeRead(PRE . $entity, "WHERE id = :id", "id={$data['id']}");
                $value = $read->getResult() ? $read->getResult()[0][$dataColumn['column']] : null;
                if(empty($value))
                    continue;
                elseif(empty($data[$dataColumn['column']]))
                    unset($data[$dataColumn['column']]);
            } elseif (!in_array($dataColumn['key'], ["selecao", "list", "publisher", "extend_mult", "selecao_mult", "list_mult"]) && !$dataColumn['update']) {
                unset($data[$dataColumn['column']]);
            }
        }

        return $data;
    }

    /**
     * Impede criação ou alterações de perfis para um nível superior
     * @param array $data
     * @param string $entity
     * @return array
     */
    private static function checkNivelUser(array $data, string $entity)
    {
        //Previne edições de setor e niveis maiores que o seu, além da edição de seu próprio setor
        if ($entity === "login") {
            if (!empty($data['id']) && $data['id'] == $_SESSION['userlogin']['id']) {
                unset($data['setor'], $data['nivel'], $data['status']);
            } else {
                if ($data['setor'] < $_SESSION['userlogin']['setor'])
                    $data['setor'] = $_SESSION['userlogin']['setor'];
                if ($data['setor'] == $_SESSION['userlogin']['setor'] && $data['nivel'] < $_SESSION['userlogin']['nivel'])
                    $data['nivel'] = $_SESSION['userlogin']['nivel'];
            }
        }

        return $data;
    }

    /**
     * Armazena data no banco
     *
     * @param string $entity
     * @param array $data
     * @param array $info
     * @param array $dicionario
     * @return mixed
     */
    private static function storeData(string $entity, array $data, array $info, array $dicionario)
    {
        foreach (["extend", "list", "selecao"] as $e) {
            if ($info[$e]) {
                foreach ($info[$e] as $i) {
                    if (!empty($data[$dicionario[$i]['column']]) && is_array($data[$dicionario[$i]['column']]) && Check::isAssoc($data[$dicionario[$i]['column']]))
                        $data[$dicionario[$i]['column']] = self::storeData($dicionario[$i]['relation'], $data[$dicionario[$i]['column']], Metadados::getInfo($dicionario[$i]['relation']), Metadados::getDicionario($dicionario[$i]['relation']));
                    elseif (!empty($data[$dicionario[$i]['column']]) && is_numeric($data[$dicionario[$i]['column']]))
                        $data[$dicionario[$i]['column']] = (int)$data[$dicionario[$i]['column']];
                    elseif ($e !== "extend")
                        $data[$dicionario[$i]['column']] = null;
                }
            }
        }

        $relation = null;
        $indice = 0;
        foreach (["extend_mult", "list_mult", "selecao_mult"] as $e) {
            if ($info[$e]) {
                foreach ($info[$e] as $idColumn) {

                    $relation[$indice]['data'] = null;
                    $relation[$indice]['relation'] = $dicionario[$idColumn]['relation'];
                    $relation[$indice]['column'] = $dicionario[$idColumn]['column'];

                    if (!empty($data[$dicionario[$idColumn]['column']]) && is_array($data[$dicionario[$idColumn]['column']]) && !is_numeric($data[$dicionario[$idColumn]['column']][0])) {
                        foreach ($data[$dicionario[$idColumn]['column']] as $dataRelation)
                            $relation[$indice]['data'][] = self::storeData($dicionario[$idColumn]['relation'], $dataRelation, Metadados::getInfo($dicionario[$idColumn]['relation']), Metadados::getDicionario($dicionario[$idColumn]['relation']));

                    } elseif (!empty($data[$dicionario[$idColumn]['column']]) && is_array($data[$dicionario[$idColumn]['column']])) {
                        $relation[$indice]['data'] = $data[$dicionario[$idColumn]['column']];
                    }

                    unset($data[$dicionario[$idColumn]['column']]);
                    $indice++;
                }
            }
        }

        $id = (!empty($data['id']) ? self::updateTableData($entity, $data) : self::createTableData($entity, $data));

        if (!$id) {
            self::$error[$entity]['id'] = "Erro ao Salvar no Banco";
        } elseif ($relation) {
            self::createRelationalData($entity, $id, $relation);
            //            Elastic::add($entity, $id);
        }

        return $id;
    }

    /**
     * Valida informações submetidas por um campo multiplo selecionado de um campo relacional
     * ex: selecione uma pessoa, agora selecione um dos endereços dessa pessoa, este endereço selecionado é validado.
     *
     * @param array $dataR
     * @param array $dic
     * @param array $dados
     * @return array
     */
    private static function checkSelecaoUnique(array $dataR, array $dic, array $dados): array
    {
        if (!empty($dic['select'])) {
            foreach ($dic['select'] as $select)
                $dataR[$select . "__" . $dic['column']] = (is_numeric($dados[$select . "__" . $dic['column']]) && $dados[$select . "__" . $dic['column']] > 0 ? (int)$dados[$select . "__" . $dic['column']] : null);
        }

        return $dataR;
    }

    /**
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $dados
     * @param mixed $id
     * @return mixed
     */
    private static function checkDataOne(string $entity, array $dic, $dados = null, $id = null)
    {
        if ($dic['default'] === false && empty($dados)) {
            self::$error[$entity][$dic['column']] = "informe um valor";
            return null;

        } elseif ($dic['key'] === "extend" || is_array($dados)) {
            if (is_numeric($dados) || (is_array($dados) && !empty($dados[0]) && is_numeric($dados[0]))) {
                self::$error[$entity][$dic['column']] = "esperado um objeto, recebido um ID ou Array de ID";
                return null;
            }

            if(!empty($id) && empty($dados['id'])) {
                $read = new Read();
                $read->exeRead(PRE . $entity, "WHERE id = :idd", "idd={$id}");
                if ($read->getResult() && !empty($read->getResult()[0][$dic['column']]))
                    $dados['id'] = $read->getResult()[0][$dic['column']];
            }

            $data = Entity::validateData($dic['relation'], $dados, Metadados::getInfo($dic['relation']), Metadados::getDicionario($dic['relation']));

            if (isset(self::$error[$dic['relation']])) {
                self::$error[$entity][$dic['column']] = self::$error[$dic['relation']];
                unset(self::$error[$dic['relation']]);
                return null;
            }

            return $data;
        }

        return $dados;
    }


    /**
     * @param string $entity
     * @param int $id
     * @param array $relation
     */
    private static function createRelationalData(string $entity, int $id, array $relation)
    {
        $read = new Read();
        $create = new Create();
        foreach ($relation as $data) {
            $entityRelation = PRE . $entity . "_" . $data['relation'] . "_" . $data['column'];
            $del = new Delete();
            $del->exeDelete($entityRelation, "WHERE {$entity}_id = :eid", "eid={$id}");
            if (!empty($data['data'])) {
                foreach ($data['data'] as $idRelation) {
                    $read->exeRead($entityRelation, "WHERE {$entity}_id = :eid && {$data['relation']}_id = :iid", "eid={$id}&iid={$idRelation}");
                    if (!$read->getResult())
                        $create->exeCreate($entityRelation, [$entity . "_id" => $id, $data['relation'] . "_id" => $idRelation]);
                }
            }
        }
    }

    /**
     * @param string $entity
     * @param array $data
     * @return mixed
     */
    private static function createTableData(string $entity, array $data)
    {
        unset($data['id']);
        $create = new Create();
        $create->exeCreate(PRE . $entity, $data);
        return $create->getResult() ? (int) $create->getResult() : null;
    }

    /**
     * @param string $entity
     * @param array $data
     * @return mixed
     */
    private static function updateTableData(string $entity, array $data)
    {
        $read = new Read();
        $read->exeRead(PRE . $entity, "WHERE id=:id", "id={$data['id']}");
        if ($read->getResult()) {
            $up = new Update();
            $up->exeUpdate(PRE . $entity, $data, "WHERE id=:id", "id={$data['id']}");
            if ($up->getResult())
                return (int) $data['id'];
        }

        return self::createTableData($entity, $data);
    }

    /**
     *
     * @param string $entity
     * @param array $dic
     * @param mixed $dados
     * @param mixed $id
     * @return mixed
     */
    private static function checkDataMult(string $entity, array $dic, $dados = null, $id = null)
    {
        if (is_string($dados))
            $dados = json_decode($dados, true);

        if ($dados && is_array($dados)) {
            $results = null;
            foreach ($dados as $dado) {
                if (is_array($dado))
                    $results[] = self::checkDataOne($entity, $dic, $dado, $id);
                elseif (is_numeric($dado))
                    return $dados;
            }

            return $results;
        }

        return null;
    }

    /**
     * @param string $entity
     * @param array $data
     * @param array $dic
     * @param array $dicionario
     * @param array $info
     * @return mixed
     */
    private static function checkData(string $entity, array $data, array $dic, array $dicionario, array $info)
    {
        $data[$dic['column']] = self::checkLink($data, $dic, $dicionario, $info);
        $data[$dic['column']] = self::checkDefaultSet($dic, $data[$dic['column']]);
        self::checkNullSet($entity, $dic, $data[$dic['column']]);
        self::checkType($entity, $dic, $data[$dic['column']]);
        self::checkSize($entity, $dic, $data[$dic['column']]);
        self::checkUnique($entity, $dic, $data[$dic['column']], $data['id'] ?? null);
        self::checkRegular($entity, $dic, $data[$dic['column']]);
        self::checkValidate($entity, $dic, $data[$dic['column']]);
        self::checkValues($entity, $dic, $data[$dic['column']]);

        return $data[$dic['column']];
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