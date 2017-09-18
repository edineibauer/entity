<?php

/**
 * <b>CreateTable:</b>
 * Obtem um arquivo JSON e o cria a tabela relacionada a ele
 *
 * @copyright (c) 2017, Edinei J. Bauer
 */

namespace Entity;

use ConnCrud\Delete;
use ConnCrud\Read;
use ConnCrud\TableCrud;
use Helpers\Check;

abstract class EntityManagementData
{
    private $entityJson;
    private $entityDados;
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
     * @param array $entidadeFields
     */
    protected function setEntityArray(array $entity, array $entidadeFields)
    {
        $this->entityDados = !isset($entity[$this->table]) ? array($this->table => $entity) : $entity;
        $this->entityJson = $entidadeFields;
        $this->insertEntity();
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
    protected function setErro($erro, $column)
    {
        $this->erro[$column] = $erro;
    }

    /**
     * @return mixed
     */
    public function getErroManagementData()
    {
        return $this->erro;
    }

    private function insertEntity()
    {
        //Para cada entidade enviada
        foreach ($this->entityDados as $table => $dados) {
            if (isset($dados['id'])) {
                $create = new TableCrud($table);
                $create->load($dados['id']);
                if ($create->exist()) {
                    $dados = $this->validateDados($dados);
                    if (!$this->erro) {
                        $create->setDados($dados);
                        $create->save();
                        $this->idData = $dados['id'];
                    } else {
                        var_dump($this->erro);
                    }
                } else {
                    $this->erro['id'] = "Falhou. Id não encontrado para atualização das informações.";
                    var_dump($this->erro);
                }

            } else {
                $dados = $this->validateDados($dados);
                if (!$this->erro) {
                    $create = new TableCrud($table);
                    $create->loadArray($dados);
                    $this->idData = $create->save();
                } else {
                    var_dump($this->erro);
                }

            }
        }
    }

    private function showResponse()
    {
        if ($this->erro) {
            echo json_encode(array("response" => 2, "mensagem" => "Uma ou mais informações precisam de alteração", "erros" => $this->getErroManagementData()));
        } elseif ($this->idData) {
            echo json_encode(array("response" => 1, "id" => $this->idData, "mensagem" => "informações salvas com sucesso."));
        }
    }

    private function validateDados($dados)
    {
        $update = null;
        $newdados = array();
        foreach ($this->entityJson as $column => $fields) {
            if (!$this->erro) {
                if (!isset($this->entityJson[$column]['key']) || $this->entityJson[$column]['key'] !== "primary") {
                    if (!$update || $this->checkIsUpdated($column)) {
                        $newdados[$column] = $this->checkValue($column, $update, $dados[$column] ?? null);
                    }
                    if ($this->erro) {
                        break;
                    }

                } elseif (isset($dados[$column]) && $dados[$column] > 0) {
                    $update['column'] = $column;
                    $update['value'] = $dados[$column];
                }
            } else {
                var_dump($this->erro);
            }
        }

        return $newdados;
    }

    private function checkIsUpdated($column)
    {
        return isset($this->entityJson[$column]['update']) ? $this->entityJson[$column]['update'] : true;
    }

    private function checkValue($column, $update = null, $value = null)
    {
        $value = $this->checkDefault($this->entityJson[$column], $value);
        $value = $this->checkLink($column, $value);
        $this->checkNull($this->entityJson[$column], $value, $column);
        $this->checkAllowValues($column, $value);
        $this->checkType($column, $value);
        $this->checkSize($column, $value);
        $this->checkValidate($this->entityJson[$column], $value, $column);
        $this->checkRegularExpressionValidate($this->entityJson[$column], $value, $column);
        $this->checkUnique($column, $value, $update);
        $this->checkTagsFieldDefined($column, $value);
        $this->checkFile($column, $value);

        return $value;
    }

    private function checkTagsFieldDefined($column, $value)
    {
        if ($this->haveTag("email", $this->entityJson[$column]['tag'] ?? null)) {
            if (!Check::email($value)) {
                $this->setErro("formato de email inválido", $column);
            }

        } elseif ($this->haveTag("cpf", $this->entityJson[$column]['tag'] ?? null)) {
            if (!Check::cpf($value)) {
                $this->setErro("formato de cpf inválido", $column);
            }

        } elseif ($this->haveTag("cnpj", $this->entityJson[$column]['tag'] ?? null)) {
            if (!Check::cnpj($value)) {
                $this->setErro("formato de cnpj inválido", $column);
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

    private function checkFile($column, $value)
    {
        if ($this->haveTag("cover", $this->entityJson[$column]['tag'] ?? null)) {
            //            $control = new ImageControl();
            //            $control->setTable($this->table);
            //            $control->setId($this->id);
            //            $this->inputs[$name] = $control->getImage();
            //            if ($control->getError()):
            //                $this->setErro("upload de imagem não permitido", $column);
            //            endif;
        }
    }

    private function checkSize($column, $value)
    {
        if ($this->entityJson[$column]['type'] === "varchar" && strlen($value) > $this->entityJson[$column]['size']) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$this->entityJson[$column]['size']}", $column);
        } elseif ($this->entityJson[$column]['type'] === "char" && strlen($value) > 1) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$this->entityJson[$column]['size']}", $column);
        } elseif ($this->entityJson[$column]['type'] === "tinytext" && strlen($value) > 255) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$this->entityJson[$column]['size']}", $column);
        } elseif ($this->entityJson[$column]['type'] === "text" && strlen($value) > 65535) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$this->entityJson[$column]['size']}", $column);
        } elseif ($this->entityJson[$column]['type'] === "mediumtext" && strlen($value) > 16777215) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$this->entityJson[$column]['size']}", $column);
        } elseif ($this->entityJson[$column]['type'] === "longtext" && strlen($value) > 4294967295) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$this->entityJson[$column]['size']}", $column);

        } elseif ($this->entityJson[$column]['type'] === "tinyint") {
            if ($value > (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1) || $value > (pow(2, 8) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($this->entityJson[$column]['type'] === "smallint") {
            if ($value > (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1) || $value > (pow(2, 16) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($this->entityJson[$column]['type'] === "mediumint") {
            if ($value > (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1) || $value > (pow(2, 24) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($this->entityJson[$column]['type'] === "int") {
            if ($value > (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1) || $value > (pow(2, 32) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($this->entityJson[$column]['type'] === "bigint") {
            if ($value > (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1) || $value > (pow(2, 64) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($this->entityJson[$column]['size'] * 2)) - 1), $column);
            }
        }
    }

    private function checkType($column, $value)
    {
        if (!empty($value)) {
            if (in_array($this->entityJson[$column]['type'], array("tinyint", "smallint", "mediumint", "int", "bigint"))) {
                if (!is_numeric($value)) {
                    $this->setErro("valor numérico inválido.", $column);
                }

            } elseif ($this->entityJson[$column]['type'] === "decimal") {
                $size = (isset($this->entityJson[$column]['size']) ? explode(',', str_replace(array('(', ')'), '', $this->entityJson[$column]['size'])) : array(10, 30));
                $val = explode('.', str_replace(',', '.', $value));
                if (strlen($val[1]) > $size[1]) {
                    $this->setErro("valor das casas decimais excedido. Max {$size[1]}", $column);
                } elseif (strlen($val[0]) > $size[0]) {
                    $this->setErro("valor inteiro do valor decimal excedido. Max {$size[0]}", $column);
                }

            } elseif (in_array($this->entityJson[$column]['type'], array("double", "real"))) {
                if (!is_double($value)) {
                    $this->setErro("valor double não válido", $column);
                }

            } elseif ($this->entityJson[$column]['type'] === "float") {
                if (!is_float($value)) {
                    $this->setErro("valor flutuante não é válido", $column);
                }

            } elseif (in_array($this->entityJson[$column]['type'], array("bit", "boolean", "serial"))) {
                if (!is_bool($value)) {
                    $this->setErro("valor boleano inválido. (true ou false)", $column);
                }
            } elseif (in_array($this->entityJson[$column]['type'], array("datetime", "timestamp"))) {
                if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $value)):
                    $this->setErro("formato de data inválido ex válido:(2017-08-23 21:58:00)", $column);
                endif;

            } elseif ($this->entityJson[$column]['type'] === "date") {
                if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $value)):
                    $this->setErro("formato de data inválido ex válido:(2017-08-23)", $column);
                endif;

            } elseif ($this->entityJson[$column]['type'] === "time") {
                if (!preg_match('/\d{2}:\d{2}/i', $value)):
                    $this->setErro("formato de tempo inválido ex válido:(21:58)", $column);
                endif;

                //            } elseif ($this->entityJson[$column]['type'] === "json") {

            }
        }
    }

    private function checkUnique($column, $value, $update = null)
    {
        if ($this->entityJson[$column]['unique']) {
            if ($update) {
                $read = new Read();
                $read->exeRead($this->table, "WHERE {$column} = '{$value}' && {$update['column']} != {$update['value']}");
                if ($read->getResult()) {
                    $this->setErro("campo precisa ser único", $column);
                }
            } else {
                $read = new Read();
                $read->exeRead($this->table, "WHERE {$column} = '{$value}'");
                if ($read->getResult()) {
                    $this->setErro("campo precisa ser único", $column);
                }
            }
        }
    }

    private function checkAllowValues($column, $value)
    {
        if (isset($this->entityJson[$column]['allow']) && !empty($value)) {
            if (!in_array($value, $this->entityJson[$column]['allow'])) {
                $this->setErro("valor não permitido", $column);
            }
        }
    }

    private function checkNull($field, $value, $column)
    {
        if (isset($field['null']) && !$field['null'] && empty($value)) {
            $this->setErro("campo precisa ser preenchido", $column);
        }
    }

    private function checkDefault($field, $value)
    {
        if (isset($field['default']) && empty($value)) {
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

    private function checkLink($column, $value)
    {
        if (isset($this->entityJson[$column]['link'])) {
            return Check::name($this->entityDados[$this->table][$this->entityJson[$column]['link']]);
        }

        return $value;
    }

    private function checkValidate($field, $value, $column)
    {
        if (isset($field['validade']) && !empty($value)):
            if (is_array($field['validade'])) {
                foreach ($field['validade'] as $reg):
                    $this->valida($reg, $value, $column);
                endforeach;
            } else {
                $this->valida($field['validade'], $value, $column);
            }
        endif;
    }

    private function valida($key, $value, $column)
    {
        switch ($key) {
            case "email" :
                if (!\Helpers\Check::email($value)):
                    $this->setErro("formato de email incorreto", $column);
                endif;
                break;
        }
    }

    private function checkRegularExpressionValidate($field, $value, $column)
    {
        //se existir expressão e se o valor não pode ser deixado em branco ou se o valor, valida expressão
        if (isset($field['regular']) && !empty($value)):
            if (is_array($field['regular'])) {
                foreach ($field['regular'] as $reg):
                    $this->validaRegularExpression($reg, $value, $column);
                endforeach;
            } else {
                $this->validaRegularExpression($field['regular'], $value, $column);
            }
        endif;
    }

    private function validaRegularExpression($reg, $value, $column)
    {
        $reg = "/{$reg}/i";
        if (!preg_match($reg, $value)):
            $this->setErro("valor não corresponde ao padrão esperado", $column);
        endif;
    }

    protected function getPre(string $table): string
    {
        return (defined("PRE") ? PRE : "") . $table;
    }
}
