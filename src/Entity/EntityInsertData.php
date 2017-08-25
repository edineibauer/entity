<?php

/**
 * <b>CreateTable:</b>
 * Obtem um arquivo JSON e o cria a tabela relacionada a ele
 *
 * @copyright (c) 2017, Edinei J. Bauer
 */

namespace Entity;

use ConnCrud\TableCrud;
use Helpers\Check;

abstract class EntityInsertData
{
    private $entity;
    private $table;
    private $erro;

    /**
     * @param mixed $table
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * @param mixed $erro
     */
    protected function setErro($erro, $column)
    {
        $this->erro[$column] = $erro;
    }

    /**
     * @return mixed
     */
    public function getErro()
    {
        $erro = "";
        foreach ($this->erro as $erro) {
            $erro .= "<p style='float:left;padding:5px 10px'>{$erro}</p>";
        }
        return $erro;
    }

    /**
     * @param array $entity
     */
    protected function setEntityArray(array $entity)
    {
        if(!isset($entity[$this->table])) {
            $this->entity[$this->table] = $entity;
        } else {
            $this->entity = $entity;
        }
        $this->insertEntity();
    }

    /**
     * @param string $entity
     */
    protected function setEntityJson(string $entity)
    {
        $this->setEntityArray(json_decode($entity, true));
    }

    private function insertEntity()
    {
        foreach ($this->entity as $this->table => $dados) {

            $json = new Entity($this->table);
            $json = $json->getEntity();

            foreach ($json as $column => $fields) {
                if (!isset($json[$column]['key']) || $json[$column]['key'] !== "primary") {
                    $this->entity[$this->table][$column] = $this->checkValue($json, $column, $dados[$column] ?? null);
                    if ($this->erro) {
                        break;
                    }
                }
            }
        }

        if (!$this->erro) {
            $this->save($this->entity);
        } else {
            var_dump($this->erro);
        }
    }

    private function save($entity)
    {
        foreach ($entity as $table => $dados) {
            $create = new TableCrud($table);
            $create->loadArray($dados);
            $create->save();
        }
    }

    private function checkValue($json, $column, $value = null)
    {
        $value = $this->checkDefault($json[$column], $value);
        if (!$this->erro) {
            $value = $this->checkLink($column, $value, $json);
        }
        if (!$this->erro) {
            $this->checkNull($json[$column], $value, $column);
        }
        if (!$this->erro) {
            $this->checkAllowValues($column, $value, $json);
        }
        if (!$this->erro) {
            $this->checkType($column, $value, $json);
        }
        if (!$this->erro) {
            $this->checkSize($column, $value, $json);
        }
        if (!$this->erro) {
            $this->checkRegularExpressionValidate($json[$column], $value, $column);
        }
        if (!$this->erro) {
            $this->checkUnique($column, $value, $json);
        }
        if (!$this->erro) {
            $this->checkTagsFieldDefined($column, $value, $json);
        }
        if (!$this->erro) {
            $this->checkFile($column, $value, $json);
        }
        return $value;
    }

    private function checkTagsFieldDefined($column, $value, $json)
    {
        if ($this->haveTag("email", $json[$column]['tag'] ?? null)) {
            if (!Check::email($value)) {
                $this->setErro("formato de email inválido", $column);
            }

        } elseif ($this->haveTag("cpf", $json[$column]['tag'] ?? null)) {
            if (!Check::cpf($value)) {
                $this->setErro("formato de cpf inválido", $column);
            }

        } elseif ($this->haveTag("cnpj", $json[$column]['tag'] ?? null)) {
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

    private function checkFile($column, $value, $json)
    {
        if ($this->haveTag("cover", $json[$column]['tag'] ?? null)) {
            //            $control = new ImageControl();
            //            $control->setTable($this->table);
            //            $control->setId($this->id);
            //            $this->inputs[$name] = $control->getImage();
            //            if ($control->getError()):
            //                $this->setErro("upload de imagem não permitido", $column);
            //            endif;
        }
    }

    private function checkSize($column, $value, $json)
    {
        if ($json[$column]['type'] === "varchar" && strlen($value) > $json[$column]['size']) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$json[$column]['size']}", $column);
        } elseif ($json[$column]['type'] === "char" && strlen($value) > 1) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$json[$column]['size']}", $column);
        } elseif ($json[$column]['type'] === "tinytext" && strlen($value) > 255) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$json[$column]['size']}", $column);
        } elseif ($json[$column]['type'] === "text" && strlen($value) > 65535) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$json[$column]['size']}", $column);
        } elseif ($json[$column]['type'] === "mediumtext" && strlen($value) > 16777215) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$json[$column]['size']}", $column);
        } elseif ($json[$column]['type'] === "longtext" && strlen($value) > 4294967295) {
            $this->setErro("tamanho máximo de caracteres excedido. Max {$json[$column]['size']}", $column);

        } elseif ($json[$column]['type'] === "tinyint") {
            if ($value > (pow(2, ($json[$column]['size'] * 2)) - 1) || $value > (pow(2, 8) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($json[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($json[$column]['type'] === "smallint") {
            if ($value > (pow(2, ($json[$column]['size'] * 2)) - 1) || $value > (pow(2, 16) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($json[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($json[$column]['type'] === "mediumint") {
            if ($value > (pow(2, ($json[$column]['size'] * 2)) - 1) || $value > (pow(2, 24) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($json[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($json[$column]['type'] === "int") {
            if ($value > (pow(2, ($json[$column]['size'] * 2)) - 1) || $value > (pow(2, 32) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($json[$column]['size'] * 2)) - 1), $column);
            }
        } elseif ($json[$column]['type'] === "bigint") {
            if ($value > (pow(2, ($json[$column]['size'] * 2)) - 1) || $value > (pow(2, 64) - 1)) {
                $this->setErro("numero excedeu seu limite. Max " . (pow(2, ($json[$column]['size'] * 2)) - 1), $column);
            }
        }
    }

    private function checkType($column, $value, $json)
    {
        if (!empty($value)) {
            if (in_array($json[$column]['type'], array("tinyint", "smallint", "mediumint", "int", "bigint"))) {
                if (!is_numeric($value)) {
                    $this->setErro("valor numérico inválido.", $column);
                }

            } elseif ($json[$column]['type'] === "decimal") {
                $size = (isset($json[$column]['size']) ? explode(',', str_replace(array('(', ')'), '', $json[$column]['size'])) : array(10, 30));
                $val = explode('.', str_replace(',', '.', $value));
                if (strlen($val[1]) > $size[1]) {
                    $this->setErro("valor das casas decimais excedido. Max {$size[1]}", $column);
                } elseif (strlen($val[0]) > $size[0]) {
                    $this->setErro("valor inteiro do valor decimal excedido. Max {$size[0]}", $column);
                }

            } elseif (in_array($json[$column]['type'], array("double", "real"))) {
                if (!is_double($value)) {
                    $this->setErro("valor double não válido", $column);
                }

            } elseif ($json[$column]['type'] === "float") {
                if (!is_float($value)) {
                    $this->setErro("valor flutuante não é válido", $column);
                }

            } elseif (in_array($json[$column]['type'], array("bit", "boolean", "serial"))) {
                if (!is_bool($value)) {
                    $this->setErro("valor boleano inválido. (true ou false)", $column);
                }
            } elseif (in_array($json[$column]['type'], array("datetime", "timestamp"))) {
                if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $value)):
                    $this->setErro("formato de data inválido ex válido:(2017-08-23 21:58:00)", $column);
                endif;

            } elseif ($json[$column]['type'] === "date") {
                if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $value)):
                    $this->setErro("formato de data inválido ex válido:(2017-08-23)", $column);
                endif;

            } elseif ($json[$column]['type'] === "time") {
                if (!preg_match('/\d{2}:\d{2}/i', $value)):
                    $this->setErro("formato de tempo inválido ex válido:(21:58)", $column);
                endif;

            } elseif ($json[$column]['type'] === "json") {

            }
        }
    }

    private function checkUnique($column, $value, $json)
    {
        if (isset($json[$column]['key']) && $json[$column]['key'] === 'unique') {
            $table = new TableCrud($this->table);
            $table->load($column, $value);
            if ($table->exist()) {
                $this->setErro("campo precisa ser único", $column);
            }
        }
    }

    private function checkAllowValues($column, $value, $json)
    {
        if (isset($json[$column]['allow']) && !empty($value)) {
            if (!in_array($value, $json[$column]['allow'])) {
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

    private function checkLink($column, $value, $json)
    {
        if (isset($json[$column]['link'])) {
            return Check::name($this->entity[$this->table][$json[$column]['link']]);
        }

        return $value;
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
