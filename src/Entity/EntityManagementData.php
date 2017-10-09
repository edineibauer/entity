<?php

/**
 * <b>CreateTable:</b>
 * Obtem um arquivo JSON e o cria a tabela relacionada a ele
 *
 * @copyright (c) 2017, Edinei J. Bauer
 */

namespace Entity;

use ConnCrud\Create;
use ConnCrud\Delete;
use ConnCrud\Read;
use ConnCrud\TableCrud;
use Helpers\Check;

abstract class EntityManagementData
{
    private $extendMult;
    private $table;
    private $erro;
    private $idData;

    public function deleteEntityData($id)
    {
        $del = new Delete();
        $del->exeDelete($this->table, "WHERE id = :id", "id={$id}");
    }

    /**
     * @param array $entity
     * @param array $entidadeStruct
     */
    protected function setEntityArray(array $entity, array $entidadeStruct)
    {
        $entity = !isset($entity[$this->table]) ? array($this->table => $entity) : $entity;
        $this->idData = $this->insertEntity($entity, $entidadeStruct);
        $this->showResponse();
    }

    /**
     * @param mixed $table
     */
    protected function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * @param mixed $erro
     * @param mixed $column
     */
    protected function setErro($erro, $column, $table)
    {
        $this->erro[$table][$column] = $erro;
    }

    /**
     * @return mixed
     */
    public function getErroManagementData()
    {
        return $this->erro;
    }

    private function insertEntity($entityDados, $entityStruct)
    {
        $idRetorno = null;

        foreach ($entityDados as $table => $dados) {
            $id = $this->getPrimaryKeyValue($table, $dados);
            $dados = $this->validateDados($dados, $entityStruct, $id, $table);

            if ($id['value']) {
                $idRetorno = $this->updateTableDados($table, $dados, $id);

            } else {

                $idRetorno = $this->createTableDados($table, $dados);
            }

            $this->createRelationalInfo($table, $idRetorno, $this->extendMult);
        }

        return $idRetorno;
    }

    private function createRelationalInfo($table, $idRetorno, $extend = null)
    {
        if($extend && is_array($extend)) {
            $create = new Create();
            foreach ($extend as $tableExtend => $ids) {
                foreach ($ids as $id) {
                    $create->exeCreate(PRE . $table . "_" . $tableExtend, array($table."_id" => $idRetorno, $tableExtend."_id" => $id));
                }
            }
        }
    }

    private function createTableDados($table, $dados)
    {
        if (isset($dados['id'])) {
            unset($dados['id']);
        }

        if (!$this->erro) {
            $create = new TableCrud($table);
            $create->loadArray($dados);
            return $create->save();
        }

        return null;
    }

    private function updateTableDados($table, $dados, $id)
    {
        $create = new TableCrud($table);
        $create->load($id['value']);
        if ($create->exist()) {
            if (!$this->erro) {
                unset($dados[$id['column']]);
                $create->setDados($dados);
                $create->save();
                return $id['value'];
            }
        } else {
            $this->setErro("Falhou. Id não encontrado para atualização das informações", 'id', $table);
        }

        return null;
    }

    private function showResponse()
    {
        if ($this->erro) {
            echo json_encode(array("response" => 2, "mensagem" => "Uma ou mais informações precisam de alteração", "erros" => $this->getErroManagementData()));
        } elseif ($this->idData) {
            echo json_encode(array("response" => 1, "id" => $this->idData, "mensagem" => "Salvo"));
        }
    }

    private function validateDados($dados, $struct, $id, $table)
    {
        $newdados = array();

        foreach ($struct as $column => $fields) {
            if (!$this->erro && (!$id['value'] || $fields['update']) && $fields['key'] !== "primary") {
                $newdados[$column] = $this->checkValue($dados, $column, $fields, $table, $id);
            }
        }

        return $newdados;
    }

    private function getPrimaryKeyValue($table, $dados)
    {
        $entityInfo = new EntityInfo($table);
        $entityInfo = $entityInfo->getJsonInfoEntity();
        if (isset($entityInfo['primary']) && !empty($entityInfo['primary']) && isset($dados[$entityInfo['primary']]) && !empty($dados[$entityInfo['primary']])) {
            $id['column'] = $entityInfo['primary'];
            $id['value'] = $dados[$entityInfo['primary']] ?? null;
            $id['value'] = !empty($id['value']) && $id['value'] > 0 ? $id['value'] : null;
            return $id;
        }
        return array("column" => null, "value" => null);
    }

    private function checkValue($dados, $column, $fields, $table, $id = null)
    {
        $value = $dados[$column] ?? null;
        if (in_array($fields['key'], array("extend", "extend_mult", "list", "list_mult")) && !empty($fields['table'])) {
            $value = $this->insertDataIntoExtend($fields, $value);

        } else {

            $value = $this->checkDefault($fields, $value);
            $value = $this->checkLink($fields, $dados, $value);
            $value = $this->checkNull($fields, $value, $column, $table);
            $this->checkAllowValues($fields, $column, $value, $table);
            $this->checkType($fields, $column, $value, $table);
            $this->checkSize($fields, $column, $value, $table);
            $this->checkValidate($fields, $value, $column, $table);
            $this->checkRegularExpressionValidate($fields, $value, $column, $table);
            $this->checkUnique($fields, $column, $value, $table, $id);
            $this->checkTagsFieldDefined($fields, $column, $value, $table);
            $this->checkFile($fields, $column, $value);
        }

        return $value;
    }

    private function insertDataIntoExtend($fields, $dados = null)
    {
        if ($dados && is_array($dados)) {
            if (in_array($fields['key'], array("extend_mult", "list_mult"))) {
                foreach ($dados as $dado) {
                    if(is_array($dado)) {
                        $this->extendMult[$fields['table']][] = $this->prepareInsertDataExtend($dado, $fields['table']);
                    } elseif(is_numeric($dado)) {
                        $this->extendMult[$fields['table']][] = $dado;
                    }
                }

            } else {
                return $this->prepareInsertDataExtend($dados, $fields['table']);
            }
        }
        return null;
    }

    private function prepareInsertDataExtend($dados, $table)
    {
        $struct = new Entity($table);
        $dados = !isset($dados[$table]) ? array($table => $dados) : $dados;

        return $this->insertEntity($dados, $struct->getJsonStructEntity());
    }

    private function checkTagsFieldDefined($fields, $column, $value, $table)
    {
        if ($this->haveTag("email", $fields['tag'] ?? null)) {
            if (!Check::email($value)) {
                $this->setErro("formato de email inválido", $column, $table);
            }

        } elseif ($this->haveTag("cpf", $fields['tag'] ?? null)) {
            if (!Check::cpf($value)) {
                $this->setErro("formato de cpf inválido", $column, $table);
            }

        } elseif ($this->haveTag("cnpj", $fields['tag'] ?? null)) {
            if (!Check::cnpj($value)) {
                $this->setErro("formato de cnpj inválido", $column, $table);
            }
        }
    }

    private function haveTag($target, $list = null)
    {
        if ($list) {
            return (is_array($list) && in_array($target, $list)) || (!is_array($list) && $list === $target);
        }

        return false;
    }

    private function checkFile($fields, $column, $value)
    {
        if ($this->haveTag("cover", $fields['tag'] ?? null)) {
            //            $control = new ImageControl();
            //            $control->setTable($this->table);
            //            $control->setId($this->id);
            //            $this->inputs[$name] = $control->getImage();
            //            if ($control->getError()):
            //                $this->setErro("upload de imagem não permitido", $column);
            //            endif;
        }
    }

    private function checkSize($fields, $column, $value, $table)
    {
        if ($fields['type'] === "varchar" && strlen($value) > $fields['size']) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$fields['size']}", $column, $table);
        } elseif ($fields['type'] === "char" && strlen($value) > 1) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$fields['size']}", $column, $table);
        } elseif ($fields['type'] === "tinytext" && strlen($value) > 255) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$fields['size']}", $column, $table);
        } elseif ($fields['type'] === "text" && strlen($value) > 65535) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$fields['size']}", $column, $table);
        } elseif ($fields['type'] === "mediumtext" && strlen($value) > 16777215) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$fields['size']}", $column, $table);
        } elseif ($fields['type'] === "longtext" && strlen($value) > 4294967295) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$fields['size']}", $column, $table);

        } elseif ($fields['type'] === "tinyint") {
            if ($value > (pow(2, ($fields['size'] * 2)) - 1) || $value > (pow(2, 8) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($fields['size'] * 2)) - 1), $column, $table);
            }
        } elseif ($fields['type'] === "smallint") {
            if ($value > (pow(2, ($fields['size'] * 2)) - 1) || $value > (pow(2, 16) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($fields['size'] * 2)) - 1), $column, $table);
            }
        } elseif ($fields['type'] === "mediumint") {
            if ($value > (pow(2, ($fields['size'] * 2)) - 1) || $value > (pow(2, 24) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($fields['size'] * 2)) - 1), $column, $table);
            }
        } elseif ($fields['type'] === "int") {
            if ($value > (pow(2, ($fields['size'] * 2)) - 1) || $value > (pow(2, 32) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($fields['size'] * 2)) - 1), $column, $table);
            }
        } elseif ($fields['type'] === "bigint") {
            if ($value > (pow(2, ($fields['size'] * 2)) - 1) || $value > (pow(2, 64) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($fields['size'] * 2)) - 1), $column, $table);
            }
        }
    }

    private function checkType($fields, $column, $value, $table)
    {
        if (!empty($value)) {
            if (in_array($fields['type'], array("tinyint", "smallint", "mediumint", "int", "bigint"))) {
                if (!is_numeric($value)) {
                    $this->setErro("valor numérico inválido.", $column, $table);
                }

            } elseif ($fields['type'] === "decimal") {
                $size = (isset($fields['size']) ? explode(',', str_replace(array('(', ')'), '', $fields['size'])) : array(10, 30));
                $val = explode('.', str_replace(',', '.', $value));
                if (strlen($val[1]) > $size[1]) {
                    $this->setErro("valor das casas decimais excedido. Max {$size[1]}", $column, $table);
                } elseif (strlen($val[0]) > $size[0]) {
                    $this->setErro("valor inteiro do valor decimal excedido. Max {$size[0]}", $column, $table);
                }

            } elseif (in_array($fields['type'], array("double", "real"))) {
                if (!is_double($value)) {
                    $this->setErro("valor double não válido", $column, $table);
                }

            } elseif ($fields['type'] === "float") {
                if (!is_float($value)) {
                    $this->setErro("valor flutuante não é válido", $column, $table);
                }

            } elseif (in_array($fields['type'], array("bit", "boolean", "serial"))) {
                if (!is_bool($value)) {
                    $this->setErro("valor boleano inválido. (true ou false)", $column, $table);
                }
            } elseif (in_array($fields['type'], array("datetime", "timestamp"))) {
                if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $value)):
                    $this->setErro("formato de data inválido ex válido:(2017-08-23 21:58:00)", $column, $table);
                endif;

            } elseif ($fields['type'] === "date") {
                if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $value)):
                    $this->setErro("formato de data inválido ex válido:(2017-08-23)", $column, $table);
                endif;

            } elseif ($fields['type'] === "time") {
                if (!preg_match('/\d{2}:\d{2}/i', $value)):
                    $this->setErro("formato de tempo inválido ex válido:(21:58)", $column, $table);
                endif;

                //            } elseif ($fields['type'] === "json") {

            }
        }
    }

    private function checkUnique($fields, $column, $value, $table, $id = null)
    {
        if ($fields['unique']) {
            $read = new Read();
            $read->exeRead($this->table, "WHERE {$column} = '{$value}'" . ($id['value'] ? " && {$id['column']} != {$id['value']}" : ""));
            if ($read->getResult()) {
                $this->setErro("campo precisa ser único", $column, $table);
            }
        }
    }

    private function checkAllowValues($fields, $column, $value, $table)
    {
        if (!empty($fields['allow']) && !empty($value)) {
            if (!in_array($value, $fields['allow'])) {
                $this->setErro("valor não permitido", $column, $table);
            }
        }
    }

    private function checkNull($field, $value, $column, $table)
    {
        if (!$field['null'] && empty($value)) {
            $this->setErro("campo precisa ser preenchido", $column, $table);
        } elseif ($field['null'] && empty($value)) {
            return null;
        }

        return $value;
    }

    private function checkDefault($field, $value)
    {
        if (empty($value)) {
            switch ($field['default']) {
                case "datetime":
                    return date("Y-m-d H:i:s");
                    break;
                case "date":
                    return date("Y-m-d");
                    break;
                case "time":
                    return date("H:i:s");
                    break;
                default:
                    return $field['default'];
            }
        }

        return $value;
    }

    private function checkLink($fields, $dados, $value)
    {
        if (isset($fields['link'])) {
            return Check::name($dados[$fields['link']]);
        }

        return $value;
    }

    private function checkValidate($field, $value, $column, $table)
    {
        if (isset($field['validade']) && !empty($value)):
            if (is_array($field['validade'])) {
                foreach ($field['validade'] as $reg):
                    $this->valida($reg, $value, $column, $table);
                endforeach;
            } else {
                $this->valida($field['validade'], $value, $column, $table);
            }
        endif;
    }

    private function valida($key, $value, $column, $table)
    {
        switch ($key) {
            case "email" :
                if (!\Helpers\Check::email($value)):
                    $this->setErro("formato de email incorreto", $column, $table);
                endif;
                break;
        }
    }

    private function checkRegularExpressionValidate($field, $value, $column, $table)
    {
        //se existir expressão e se o valor não pode ser deixado em branco ou se o valor, valida expressão
        if (!empty($field['regular']) && !empty($value) && is_string($value)):
            if (is_array($field['regular'])) {
                foreach ($field['regular'] as $reg):
                    $this->validaRegularExpression($reg, $value, $column, $table);
                endforeach;
            } else {
                $this->validaRegularExpression($field['regular'], $value, $column, $table);
            }
        endif;
    }

    private function validaRegularExpression($reg, $value, $column, $table)
    {
        $reg = "/{$reg}/i";
        if (!preg_match($reg, $value)):
            $this->setErro("valor não corresponde ao padrão esperado", $column, $table);
        endif;
    }

    protected function getPre(string $table): string
    {
        return (defined("PRE") && !preg_match("/^" . PRE . "/i", $table) ? PRE : "") . $table;
    }
}
