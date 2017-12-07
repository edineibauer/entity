<?php

namespace Entity;

use Helpers\Date;
use Helpers\DateTime;
use Helpers\Helper;
use Helpers\Time;

class Entity
{
    private $entity;
    private $data = [];
    private $metadados;
    private $erro;

    public function __construct($entity)
    {
        $this->entity = $entity;
        $this->metadados = Metadados::getStructInfo($entity);
        $this->setDefaultFieldsToEntity();
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        foreach ($data as $field => $value) {
            $this->setDataToEntity($field, $value);
        }
    }

    public function set($field, $value)
    {
        $this->setDataToEntity($field, $value);
    }

    /**
     * @param string $erro
     * @param int $line
     * @param string $field
     * @param mixed $modelControl
     */
    private function setErro(string $erro, string $field, $modelControl = null)
    {
        $this->erro[($modelControl ? $modelControl . "." : "") . $field] = $erro;
    }

    /**
     * informa valor à um field
     * @param string $field
     * @param string $value
     */
    public function __set($field, $value)
    {
        $this->setDataToEntity($field, $value);
    }

    /**
     * @return array
     */
    public function getMetadados(): array
    {
        return $this->metadados;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->getDataOnly();
    }

    /**
     * @return array
     */
    public function systemGetEditableData(): array
    {
        $data = [];
        foreach ($this->data as $column => $value) {
            if (!in_array($this->metadados['struct'][$column]['key'], array('primary', 'extend_mult', 'list_mult'))) {
                $data[$column] = $value;
            }
        }

        return $data;
    }


    /**
     * @return array
     */
    public function systemGetRelationalColumn(): array
    {
        return $this->getRelationalColumnEntity();
    }

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @return mixed
     */
    public function getErro()
    {
        return $this->erro;
    }

    public function get(string $column)
    {
        return $this->data[$column] ?? null;
    }

    /**
     * retorna a data da entidade, da forma como ela esta
     * @return array
     */
    public function systemGetData(): array
    {
        return $this->data;
    }

    /**
     * obtem valor de um field
     * @param string $column
     * @return mixed
     */
    public function __get(string $column)
    {
        return $this->data[$column] ?? null;
    }

    /**
     * Armazena esta entidade e retorna o id de armazenamento
     * ou retorna null caso haja algum problema
     * @return mixed
     */
    public function save()
    {
        $id = DataBase::add($this);
        $this->erro = DataBase::getErro();

        return $id;
    }

    /**
     * Deleta dados da entidade
     * @param mixed $id
    */
    public function delete($id = null)
    {
        if($id) {
            $this->load($id);
        }
        DataBase::delete($this);
        $this->erro = DataBase::getErro();
    }

    /**
     * Carrega uma entidade para a memória a partir de um id
     * @param int $id
     */
    public function load(int $id)
    {
        $data = DataBase::get($this, $id);
        $this->erro = DataBase::getErro();
        $this->data = $data->systemGetData();
    }

    /**
     * Carrega uma entidade semelhante para a memória a partir de um id
     * @param mixed $id
     */
    public function duplicate($id = null)
    {
        if($id) {
            $this->load($id);
        }

        if($this->data[$this->metadados['info']['primary']]) {
            $data = DataBase::copy($this);
            $this->erro = DataBase::getErro();
            $this->data = $data->systemGetData();
        } else {
            $this->erro = "Não é possível duplicar uma entidade ('{$this->entity}') sem um 'id'.";
        }
    }

    /**
     * @param mixed $field
     * @param mixed $value
     */
    private function setDataToEntity($field, $value)
    {
        try {
            if($field === $this->metadados['info']['primary'] && $this->data[$field] && $this->data[$field] > 0 && !is_null($value)) {
                throw new \Exception("Chave primaria não pode ser modificada.");
            }
            if (!is_string($field)) {
                throw new \Exception("Esperava um field string, outro valor informado.");
            }
            if (array_key_exists($field, $this->data)) {
                $this->data[$field] = $this->checkValueField($value, $this->metadados['struct'][$field], $this->data[$field]);
            }
        } catch (\Exception $e) {
            $this->setErro($e->getMessage(), $field);
        }
    }

    /**
     * converte o dado em seu tipo
     * @param mixed $value
     * @param array $metadados
     * @param mixed $currentValue
     * @return mixed
     */
    private function checkValueField($value, array $metadados, $currentValue = null)
    {
        try {
            if ($metadados['null'] && $value === "") {
                $value = null;

            } elseif (!$metadados['null'] && $value === "") {
                $value = $currentValue;

            } else {
                $value = $this->checkType($value, $metadados, $currentValue);
                $value = $this->checkSize($value, $metadados['type'], $metadados['key'], $metadados['title'], $metadados['size']);

                if ($metadados['null'] && $value === "") {
                    $value = null;

                } elseif (!$metadados['null'] && $value === "") {
                    $value = $currentValue;

                } else {

                    if (!empty($metadados['allow']) && is_array($metadados['allow']) && !in_array($value, $metadados['allow'])) {
                        throw new \Exception("Valores permitidos. [" . implode(', ', $metadados['allow']) . "]");
                    }

                    if (!empty($metadados['regular']) && !preg_match("/" . preg_quote($metadados['regular']) . "/i", $value)) {
                        throw new \Exception($metadados['title'] . " não obedeceu o formato desejado");
                    }
                }
            }

        } catch (\Exception $e) {
            $value = $currentValue;
            $this->setErro($e->getMessage(), $metadados['column']);
        }

        return $value;
    }

    /**
     * Verifica o tipo de valor a ser inserido no field da entity
     * transformando o valor no tipo esperado.
     * @param mixed $value
     * @param array $metadados
     * @param mixed $current
     * @return mixed
     * @throws \Exception
     */
    private function checkType($value, array $metadados, $current = null)
    {
        $type = $metadados['type'];
        $title = $metadados['title'];

        if (in_array($metadados['key'], array("extend_mult", "list_mult"))) {
            $value = $this->checkDataforFkMult($value, $title, $metadados, $current);

        } elseif (in_array($metadados['key'], array("extend", "list"))) {
            $value = $this->checkDataforFk($value, $title, $metadados, $current);

        } else {
            if (is_array($value) || is_object($value)) {
                throw new \Exception($title . " esperava um valor, não um array ou objeto");
            }

            if (in_array($type, array("float", "real", "double"))) {
                if (!is_numeric($value)) {
                    throw new \Exception($title . " esperava um valor float.");
                }
                $value = (float)$value;

            } elseif (in_array($type, array("tinyint", "int", "mediumint", "longint", "bigint"))) {
                if (!is_numeric($value)) {
                    if (is_bool($value)) {
                        $value = $value ? 1 : 0;
                    } elseif (is_string($value)) {
                        $value = $value === "false" ? 0 : 1;
                    } else {
                        throw new \Exception($title . " esperava um valor inteiro.");
                    }
                } else {
                    $value = (int)$value;
                }

            } elseif (in_array($type, array("bool", "boolean"))) {
                if (!is_bool($value)) {
                    if (is_numeric($value)) {
                        $value = $value < 1 ? false : true;
                    } elseif (is_null($value)) {
                        $value = false;
                    } elseif (is_string($value)) {
                        $value = $value === "false" ? false : true;
                    } else {
                        throw new \Exception($title . " esperava um valor boleano.");
                    }
                }
                $value = (bool)$value;

            } elseif ($type === "date") {
                $data = new Date();
                $value = $data->getDate($value);

            } elseif ($type === "time") {
                $data = new Time();
                $value = $data->getTime($value);

            } elseif ($type === "datetime") {
                $data = new DateTime();
                $value = $data->getDateTime($value);

            } else {
                $value = (string)$value;
            }
        }

        return $value;
    }

    /**
     * Aplica os campos que a entidade tem, isso evita novas entradas,
     * limitando os fields utilizados aos fields da entity
     */
    private function setDefaultFieldsToEntity()
    {
        foreach ($this->metadados['struct'] as $field => $metadados) {
            $this->data[$field] = $this->checkDefault($metadados['default']);
        }
    }

    /**
     * Aplica os valores padrões de cada field da entidade,
     * para quando não há valor
     * @param mixed $default
     * @return mixed
     */
    private function checkDefault($default)
    {
        if (!is_string($default)) {
            return $default;

        } elseif (!empty($default)) {
            switch ($default) {
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
                    return $default;
            }
        }

        return null;
    }


    /**
     * Valida o tamanho do valor a ser inserido em um field da entity,
     * evitando assim campos fora do tamanho desejado.
     * @param mixed $value
     * @param string $type
     * @param mixed $size
     * @param mixed $key
     * @param string $title
     * @return mixed
     * @throws \Exception
     */
    private function checkSize($value, string $type, string $key, string $title, $size = null)
    {
        if (!in_array($key, array("list", "list_mult", "extend", "extend_mult"))) {
            $size = empty($size) ? null : $size;
            $text = ["char" => $size ?? 1, "tinytext" => $size ?? 255, "text" => $size ?? 65535, "mediumtext" => $size ?? 16777215, "longtext" => $size ?? 4294967295, "varchar" => $size];
            $int = ["tinyint" => $size ?? 4, "smallint" => $size ?? 8, "mediumint" => $size ?? 12, "int" => ($size ?? 16), "bigint" => $size ?? 32];

            if (array_key_exists($type, $text)) {
                if (strlen($value) > $text[$type]) {
                    $value = substr($value, 0, $size);
                }

            } elseif (array_key_exists($type, $int)) {
                if ($value > (pow(2, ($int[$type] * 2)) - 1)) {
                    throw new \Exception($title . " deve ser menor que " . (pow(2, ($int[$type] * 2)) - 1) . ".");
                }
            }
        }

        return $value;
    }

    /**
     * @param mixed $objeto
     * @param string $field
     * @param bool $recursivo
     * @return mixed
     * @throws \Exception $e
     */
    private function checkObjectEntity($objeto, string $field, bool $recursivo = false)
    {
        if (is_null($objeto)) {
            return null;
        } elseif (is_array($objeto)) {
            if ($recursivo) {
                throw new \Exception($this->metadados['struct'][$field]['title'] . " esperava um objeto Entity, mas foi encontrado um array.");
            } else {
                $dados = null;
                foreach ($objeto as $item) {
                    $dados[] = $this->checkObjectEntity($item, $field, true);
                }
                return $dados;
            }
        } elseif (is_object($objeto) && is_a($objeto, "Entity\Entity")) {
            return $objeto->getData();
        }

        throw new \Exception($this->metadados['struct'][$field]['title'] . " esperava um objeto Entity.");
    }


    /**
     * @param mixed $value
     * @param string $title
     * @param array $metadados
     * @param mixed $current
     * @return mixed
     * @throws \Exception
     */
    private function checkDataforFkMult($value, string $title, array $metadados, $current = null)
    {
        if (!is_array($value)) {
            $data = (isset($current) && is_array($current) ? $current : []);
            $data[] = $this->checkDataforFk($value, $title, $metadados, $current);

        } else {
            $data = [];
            $indice = 0;
            foreach ($value as $i => $item) {
                if ($i !== $indice) {
                    $data = (isset($current) && is_array($current) ? $current : []);
                    $data[] = $this->checkDataforFk($value, $title, $metadados, $current);
                }

                try {
                    $data[] = $this->checkDataforFk($item, $title, $metadados, $current);
                } catch (\Exception $ex) {
                    $this->setErro($ex->getMessage(), $metadados['column']);
                }

                $indice++;
            }
        }

        return $data;
    }

    /**
     * @param mixed $value
     * @param string $title
     * @param array $metadados
     * @return mixed
     * @throws \Exception $e
     */
    private function checkDataforFk($value, string $title, array $metadados, $current = null)
    {
        if (empty($value)) {
            return null;

        } elseif (is_array($value)) {
            $obj = (!empty($current) && is_a($current, 'Entity\Entity') ? $current : new Entity($metadados['table']));
            $obj->setData($value);
            if ($obj->getErro()) {
                foreach ($obj->getErro() as $column => $mensagem) {
                    $this->setErro($mensagem, $column, $metadados['column']);
                }
                return $current;
            }
            return $obj;

        } elseif (is_numeric($value)) {
            return DataBase::get($metadados['table'], $value);

        } elseif (is_string($value)) {
            return DataBase::get($metadados['table'], $value, Metadados::getInfo($metadados['table'])['title']);

        } elseif (!is_object($value) || !is_a($value, "Entity\Entity") || $metadados['table'] !== $value->getEntity()) {
            throw new \Exception($title . " esperava um Objeto do tipo {$metadados['table']}.");
        }

        return $value;
    }

    /**
     * Converte o array de erros de uma entidade no formato string
     * @param array $erro
     * @return string
     */
    private function erroToString(array $erro): string
    {
        $string = "";
        foreach ($erro as $field => $dados) {
            $string .= "Erro: {$field} => {$dados['mensagem']} #line {$dados['line']}" . PHP_EOL;
        }

        return $string;
    }

    /**
     * obtem uma lista de todos os dados desta entidade em formato json
     * @return array
     */
    private function getDataOnly(): array
    {
        $data = [];
        foreach ($this->data as $field => $value) {
            try {
                if (in_array($this->metadados['struct'][$field]['key'], array('extend_mult', 'list_mult', 'list', 'extend'))) {
                    $data[$field] = $this->checkObjectEntity($value, $field);
                } else {
                    $data[$field] = $value;
                }
            } catch (\Exception $e) {
                $this->setErro($e->getMessage(), $field);
            }
        }

        return $data;
    }

    /**
     * Obtem uma lista dos campos que devem ser salvos nesta entidade
     * @return array
     */
    private function getEditableDataEntity(): array
    {
        $data = [];
        foreach ($this->data as $column => $value) {
            if (!in_array($this->metadados['struct'][$column]['key'], array('primary', 'extend_mult', 'list_mult'))) {
                $data[$column] = $value;
            }
        }

        return $data;
    }

    /**
     * Obtem uma lista das relações multiplas desta entidade
     * @return array
     */
    private function getRelationalColumnEntity(): array
    {
        if (!empty($this->metadados['info']['extend_mult']) && !empty($this->metadados['info']['list_mult'])) {
            return array_merge($this->metadados['info']['extend_mult'], $this->metadados['info']['list_mult']);

        } elseif (!empty($this->metadados['info']['list_mult'])) {
            return $this->metadados['info']['list_mult'];

        } elseif (!empty($this->metadados['info']['extend_mult'])) {
            return $this->metadados['info']['extend_mult'];
        }

        return [];
    }
}