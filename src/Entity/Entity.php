<?php

namespace Entity;

use Helpers\Date;
use Helpers\DateTime;
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
        $this->metadados = Metadados::getStruct($entity);
        $this->setDefaultFieldsToEntity();
    }

    /**
     * @param string $erro
     * @param int $line
     * @param string $field
     */
    public function setErro(string $erro, int $line, string $field)
    {
        $this->erro[$field]['mensagem'] = $erro;
        $this->erro[$field]['line'] = $line;
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
    public function getData(): array
    {
        return $this->getDataOnly();
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

    /**
     * obtem valor de um field
     * @param string $field
     * @return mixed
     */
    public function __get(string $field)
    {
        return $this->data[$field] ?? null;
    }

    /**
     * @param mixed $field
     * @param mixed $value
     */
    private function setDataToEntity($field, $value)
    {
        try {
            if (!is_string($field)) {
                throw new \Exception("Esperava um field string, outro valor informado.");
            }
            if (array_key_exists($field, $this->data)) {
                $this->data[$field] = $this->checkValueField($value, $this->metadados[$field], $this->data[$field]);
            }
        } catch (\Exception $e) {
            $this->setErro($e->getMessage(), $e->getLine(), $field);
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
            if ($metadados['null'] && empty($value)) {
                $value = null;

            } elseif (!$metadados['null'] && empty($value)) {
                $value = $currentValue;

            } else {
                $value = $this->checkType($value, $metadados);
                $value = $this->checkSize($value, $metadados['type'], $metadados['key'], $metadados['title'], $metadados['size']);

                if (empty($value)) {
                    $value = $currentValue;
                } else {

                    if (!empty($metadados['allow']) && is_array($metadados['allow']) && !in_array($value, $metadados['allow'])) {
                        throw new \Exception($metadados['title'] . " não possue um dos valores permitidos. [" . implode(', ', $metadados['allow']) . "]");
                    }

                    if (!empty($metadados['regular']) && !preg_match("/" . preg_quote($metadados['regular']) . "/i", $value)) {
                        throw new \Exception($metadados['title'] . " não obedeceu o formato desejado");
                    }
                }
            }

        } catch (\Exception $e) {
            $value = $currentValue;
            $this->setErro($e->getMessage(), $e->getLine(), $metadados['column']);
        }

        return $value;
    }

    /**
     * Verifica o tipo de valor a ser inserido no field da entity
     * transformando o valor no tipo esperado.
     * @param mixed $value
     * @param array $metadados
     * @return mixed
     * @throws \Exception
     */
    private function checkType($value, array $metadados)
    {
        $type = $metadados['type'];
        $title = $metadados['title'];

        if (in_array($metadados['key'], array("extend", "extend_mult", "list", "list_mult"))) {

            if (in_array($metadados['key'], array("extend_mult", "list_mult"))) {
                $value = $this->checkDataforFkMult($value, $title, $metadados);
            } else {
                $value = $this->checkDataforFk($value, $title, $metadados);
            }
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
                    if(is_bool($value)) {
                        $value = $value ? 1 : 0;
                    } else {
                        throw new \Exception($title . " esperava um valor inteiro.");
                    }
                }
                $value = (int)$value;

            } elseif (in_array($type, array("bool", "boolean"))) {
                if (!is_bool($value)) {
                    if (is_numeric($value)) {
                        $value = $value < 1 ? false : true;
                    } elseif (is_null($value)) {
                        $value = false;
                    } elseif (is_string($value)) {
                        $value = true;
                    } else {
                        throw new \Exception($title . " esperava um valor boleano.");
                    }
                }
                $value = (bool)$value;

            } elseif($type === "date") {
                $data = new Date();
                $value = $data->getDate($value);

            } elseif($type === "time") {
                $data = new Time();
                $value = $data->getTime($value);

            } elseif($type === "datetime") {
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
        foreach ($this->metadados as $field => $metadados) {
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
            $text = ["char" => $size ?? 1, "tinytext" => $size ?? 255, "text" => $size ?? 65535, "mediumtext" => $size ?? 16777215, "longtext" => $size ?? 4294967295, "varchar" => $size];
            $int = ["tinyint" => $size ?? 4, "smallint" => $size ?? 8, "mediumint" => $size ?? 12, "int" => $size ?? 16, "bigint" => $size ?? 32];

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

    private function getDataOnly()
    {
        $data = null;
        foreach ($this->data as $field => $value) {
            try {
                if (in_array($this->metadados[$field]['key'], array('extend_mult', 'list_mult', 'list', 'extend'))) {
                    $data[$field] = $this->checkObjectEntity($value, $field);
                } else {
                    $data[$field] = $value;
                }
            } catch (\Exception $e) {
                $this->setErro($e->getMessage(), $e->getLine(), $field);
            }
        }

        return $data;
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
                throw new \Exception($this->metadados[$field]['title'] . " esperava um objeto Entity, mas foi encontrado um array.");
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

        throw new \Exception($this->metadados[$field]['title'] . " esperava um objeto Entity.");
    }


    /**
     * @param mixed $value
     * @param string $title
     * @param array $metadados
     * @return mixed
     * @throws \Exception
     */
    private function checkDataforFkMult($value, string $title, array $metadados)
    {
        if (!is_array($value)) {
            return array(0 => $this->checkDataforFk($value, $title, $metadados));
        }

        $data = [];
        $indice = 0;
        foreach ($value as $i => $item) {
            if ($i !== $indice) {
                return array(0 => $this->checkDataforFk($value, $title, $metadados));
            }

            try {
                $data[] = $this->checkDataforFk($item, $title, $metadados);
            } catch (\Exception $ex) {
                $this->setErro($ex->getMessage(), $ex->getLine(), $metadados['column']);
            }

            $indice++;
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
    private function checkDataforFk($value, string $title, array $metadados)
    {
        if (is_null($value)) {
            return null;
        } elseif (is_array($value)) {
            $obj = new Entity($metadados['table']);
            $obj->setData($value);
            if ($obj->getErro()) {
                throw new \Exception("Esperava um objeto Entity válido. " . PHP_EOL . $this->erroToString($obj->getErro()));
            }
            return $obj;

        } elseif (is_numeric($value)) {
            return $this->getEntityFromId($value, $metadados['table']);

        } elseif (!is_object($value) || !is_a($value, "Entity\Entity") || $metadados['table'] !== $value->getEntity()) {
            throw new \Exception($title . " esperava um Objeto Entity -> {$metadados['table']}.");
        }

        return $value;
    }

    private function erroToString(array $erro) :string
    {
        $string = "";
        foreach ($erro as $field => $dados) {
            $string .= "Erro: {$field} => {$dados['mensagem']} #line {$dados['line']}" . PHP_EOL;
        }

        return $string;
    }

    /**
     * @param int $id
     * @param string $table
     * @return Entity
     */
    private function getEntityFromId(int $id, string $table): Entity
    {
        $objData = new DataBase($table);
        return $objData->get($id);
    }
}