<?php

namespace Entity;

use ConnCrud\Read;
use Helpers\Check;

class Validate
{
    /**
     * Valida valor a ser inserido na meta
     *
     * @param Meta $m
     * @param $value
     * @return mixed
     */
    public static function meta(Meta $m)
    {
        if ($m->getColumn() !== "id") {
            self::checkDefaultSet($m);
            if (!empty($m->getValue()) && !in_array($m->getKey(), ["extend", "extend_add", "list", "selecao", "checkbox_rel"])) {
                self::checkRegular($m);
                self::convertValues($m);
                self::checkType($m);
                self::checkSize($m);
                self::checkValidate($m);
                self::checkValues($m);
            }
        }
    }

    /**
     * @param Dicionario $d
     */
    public static function dicionario(Dicionario $d)
    {
        if (Entity::checkPermission($d->getEntity(), $d->search(0)->getValue())) {
            foreach ($d->getDicionario() as $m) {
                if ($m->getColumn() !== "id" && !in_array($m->getKey(), ["extend", "extend_add", "list", "selecao", "checkbox_rel"])) {
                    self::checkLink($d, $m);
                    self::checkUnique($d, $m);

                    if ($m->getKey() === "link" && $m->getError()) {
                        $d->getRelevant()->setError($m->getError());
                        $m->setError(null);
                        $m->setValue(null, false);
                    }

                    if ($m->getError())
                        $m->setValue(null, false);
                }
            }

            foreach ($d->getDicionario() as $m)
                self::checkDefaultPattern($d, $m);

        } else {
            $d->search(0)->setError("Permissão Negada");
        }
    }

    /**
     * @param string $entity
     * @param mixed $id
     * @return mixed
     */
    public static function update(string $entity, $id = null)
    {
        if (empty($id))
            return false;

        $read = new Read();
        $read->exeRead($entity, "WHERE id = :id", "id={$id}");
        return $read->getResult() ? true : false;
    }

    /**
     * Verifica se o campo é do tipo link, se for, linka o valor ao título
     *
     * @param Dicionario $d
     * @param Meta $m
     */
    private static function checkLink(Dicionario $d, Meta $m)
    {
        if ($m->getKey() === "link") {
            if (!empty($d->getRelevant()->getValue()))
                $m->setValue(Check::name($d->getRelevant()->getValue()));
        }
    }

    /**
     * Verifica se o campo é do tipo link, se for, linka o valor ao título
     *
     * @param Dicionario $d
     * @param Meta $m
     */
    private static function checkDefaultPattern(Dicionario $d, Meta $m)
    {
        if (preg_match('/{\$/', $m->getDefault())) {
            $newDefault = "";
            $error = false;
            foreach (explode('{$', $m->getDefault()) as $i => $expressao) {
                if ($i > 0) {
                    $variable = explode('}', $expressao);
                    $base = $variable[1];
                    $variable = trim(strtolower($variable[0]));
                    $mod = null;
                    $param = null;
                    if (preg_match('/\|/i', $variable)) {
                        $mod = explode('|', $variable);
                        $variable = $mod[0];
                        $mod = $mod[1];
                        if (preg_match('/\(/i', $mod) && preg_match('/\)/i', $mod)) {
                            $param = explode('(', $mod);
                            $mod = $param[0];
                            $param = explode(')', $param[1])[0];
                            if (preg_match('/,/i', $param))
                                $param = explode(',', $param);
                        }
                    }

                    if ($value = self::getValueFromVarible($d, $variable))
                        $newDefault .= ($mod ? self::proccessFunction($value, $mod, $param) : $value);

                    $newDefault .= $base;

                } else {
                    $newDefault .= $expressao;
                }
            }

            $m->setValue($newDefault);
        }

    }

    private static function proccessFunction($value, $mod = null, $param = null)
    {
        switch ($mod) {
            case 'str_pad':
                return str_pad($value, !empty($param[0]) ? $param[0] : 2, !empty($param[1]) ? $param[1] : '0', !empty($param[2]) && $param[2] === "right" ? STR_PAD_RIGHT : STR_PAD_LEFT);
                break;
            default:
                return $value;
        }
    }

    /**
     * @param Dicionario $d
     * @param string $variable
     * @return mixed
     */
    private static function getValueFromVarible(Dicionario $d, string $variable)
    {
        if (preg_match('/./i', $variable)) {
            foreach ($variables = explode('.', $variable) as $i => $variable) {
                if ($m = $d->search($variable)) {
                    if ($m->getError()) {
                        return null;
                    } elseif ($i === count($variables) - 1) {
                        return !empty($m->getAllow()['names']) ? $m->getAllow()['names'][array_search($m->getValue(), $m->getAllow()['values'])] : $m->getValue();
                    } elseif (!empty($m->getRelation()) && !empty($m->getValue())) {
                        $d = new Dicionario($m->getRelation());
                        $d->setData($m->getValue());
                    } else {
                        return null;
                    }
                    //                } elseif (!empty($m->getSelect()) && in_array($variable, $m->getSelect())) {
                    //                    if ($meta = $d->search($variable . "__" . $variables[$i - 1])) {
                    //                        if (!empty($meta->getValue()) && !empty($meta->getRelation())) {
                    //                            $d = new Dicionario($meta->getRelation());
                    //                            $d->setData($meta->getValue());
                    //                        } else {
                    //                            return null;
                    //                        }
                    //                    } else {
                    //                        return null;
                    //                    }
                } else {
                    return null;
                }
            }

        } else {
            if ($m = $d->search($variable))
                return $m->getValue();
            else
                return null;
        }
    }

    /**
     * Verifica se precisa alterar de modo padrão a informação deste campo
     *
     * @param Meta $m
     * @param mixed $value
     */
    protected static function checkDefaultSet(Meta $m)
    {
        if ($m->getValue() === "")
            $m->setValue(null, false);

        if ($m->getValue() === null) {
            if ($m->getDefault() === false)
                $m->setError("Preencha este Campo");
            elseif (!empty($m->getDefault()))
                $m->setValue($m->getDefault(), false);
        }
    }

    /**
     * Verifica se o tipo do campo é o desejado
     *
     * @param Meta $m
     */
    private static function convertValues(Meta $m)
    {
        if ($m->getType() === "json" && is_array($m->getValue()))
            $m->setValue(json_encode($m->getValue()), false);

        if ($m->getFormat() === "password" || $m->getFormat() === "passwordRequired") {
            $m->setValue(Check::password($m->getValue()), false);

        } elseif ($m->getFormat() === "valor" && strlen($m->getValue()) > 2 && !preg_match('/\./i', $m->getValue())){
            if(strlen($m->getValue()) === 3)
                $formatado = (substr($m->getValue(), 0, 1) . "." . substr($m->getValue(), 1, 2));
            else
                $formatado = (substr($m->getValue(), 0, strlen($m->getValue()) - 2) . "." . substr($m->getValue(), -2, 2));

            $m->setValue($formatado, false);
        }

        if ($m->getKey() === "link")
            $m->setValue(Check::name($m->getValue()), false);
    }

    /**
     * Verifica se o tipo do campo é o desejado
     *
     * @param Meta $m
     */
    private static function checkType(Meta $m)
    {
        if (in_array($m->getType(), ["tinyint", "smallint", "mediumint", "int", "bigint"])) {
            if (!is_numeric($m->getValue()))
                $m->setError("número inválido");

        } elseif ($m->getType() === "decimal") {

        } elseif (in_array($m->getType(), array("double", "real", "float"))) {
            if (!is_numeric($m->getValue()))
                $m->setError("valor não é um número");

        } elseif (in_array($m->getType(), array("bit", "boolean", "serial"))) {
            if (!is_bool($m->getValue()))
                $m->setError("valor boleano inválido. (true ou false)");

        } elseif (in_array($m->getType(), array("datetime", "timestamp"))) {
            if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]+\d{2}:\d{2}/i', $m->getValue()))
                $m->setError("formato de data inválido ex válido:(2017-08-23 21:58:00)");

        } elseif ($m->getType() === "date") {
            if (!preg_match('/\d{4}-\d{2}-\d{2}/i', $m->getValue()))
                $m->setError("formato de data inválido ex válido:(2017-08-23)");

        } elseif ($m->getType() === "time") {
            if (!preg_match('/\d{2}:\d{2}/i', $m->getValue()))
                $m->setError("formato de tempo inválido ex válido:(21:58)");

        } elseif ($m->getType() === "json" && !Check::isJson($m->getValue())) {
            $m->setError("formato json inválido");
        }
    }

    /**
     * Verifica se o tamanho do valor corresponde ao desejado
     *
     * @param Meta $m
     */
    private static function checkSize(Meta $m)
    {
        if ($m->getSize()) {
            $length = strlen($m->getValue());
            if ($m->getType() === "varchar" && $length > $m->getSize())
                $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");

            elseif ($m->getType() === "char" && $length > 1)
                $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");

            elseif ($m->getType() === "tinytext" && ($length > 255 || $length > $m->getSize()))
                $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");

            elseif ($m->getType() === "text" && ($length > 65535 || $length > $m->getSize()))
                $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");

            elseif ($m->getType() === "mediumtext" && ($length > 16777215 || $length > $m->getSize()))
                $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");

            elseif ($m->getType() === "longtext" && ($length > 4294967295 || $length > $m->getSize()))
                $m->setError("tamanho máximo de caracteres excedido. Max {$m->getSize()}");

            elseif ($m->getType() === "tinyint" && ($m->getValue() > self::intLength($m->getSize()) || $m->getValue() > self::intLength(8)))
                $m->setError("numero excedeu seu limite. Max " . self::intLength($m->getSize()));

            elseif ($m->getType() === "smallint" && ($m->getValue() > self::intLength($m->getSize()) || $m->getValue() > self::intLength(16)))
                $m->setError("numero excedeu seu limite. Max " . self::intLength($m->getSize()));

            elseif ($m->getType() === "mediumint" && ($m->getValue() > self::intLength($m->getSize()) || $m->getValue() > self::intLength(24)))
                $m->setError("numero excedeu seu limite. Max " . self::intLength($m->getSize()));

            elseif ($m->getType() === "int" && !in_array($m->getKey(), ["extend", "extend_add", "list", "list_mult", "selecao", "selecao_mult", "checkbox_rel", "extend_mult", "checkbox_mult"]) && ($m->getValue() > self::intLength($m->getSize()) || $m->getValue() > self::intLength(32)))
                $m->setError("numero excedeu seu limite. Max " . self::intLength($m->getSize()));

            elseif ($m->getType() === "bigint" && ($m->getValue() > self::intLength($m->getSize()) || $m->getValue() > self::intLength(64)))
                $m->setError("numero excedeu seu limite. Max " . self::intLength($m->getSize()));
        }
    }

    /**
     * @param int $value
     * @return int
     */
    private static function intLength(int $value): int
    {
        return (pow(2, ($value * 2)) - 1);
    }

    /**
     * Verifica se o valor precisa ser único
     *
     * @param Dicionario $d
     * @param Meta $m
     */
    private static function checkUnique(Dicionario $d, Meta $m)
    {
        if ($m->getUnique()) {
            $read = new Read();
            $read->exeRead($d->getEntity(), "WHERE {$m->getColumn()} = '{$m->getValue()}'" . (!empty($d->search("id")->getValue()) ? " && id != " . $d->search("id")->getValue() : ""));
            if ($read->getResult())
                $m->setError("Valor já existe, informe outro");
        }
    }

    /**
     * Verifica se existe expressão regular, e se existe, aplica a verificação
     *
     * @param Meta $m
     */
    private static function checkRegular(Meta $m)
    {
        if (!empty($m->getAllow()['regex']) && is_string($m->getValue()) && !preg_match($m->getAllow()['regex'], $m->getValue()))
            $m->setError("formato não permitido.");
    }

    /**
     * Verifica se o campo precisa de validação pré-formatada
     *
     * @param Meta $m
     */
    private static function checkValidate(Meta $m)
    {
        if (!empty($m->getAllow()['validate'])) {
            if ($m->getAllow()['validate'] === "email" && !Check::email($m->getValue()))
                $m->setError("email inválido.");

            elseif ($m->getAllow()['validate'] === "cpf" && !Check::cpf($m->getValue()))
                $m->setError("CPF inválido.");

            elseif ($m->getAllow()['validate'] === "cnpj" && !Check::cnpj($m->getValue()))
                $m->setError("CNPJ inválido.");
        }
    }

    /**
     * Verifica se existem valores exatos permitidos
     *
     * @param Meta $m
     */
    private static function checkValues(Meta $m)
    {
        if ($m->getType() === "json") {
            if (!empty($m->getValue()) && !empty($m->getAllow()['values'])) {
                if (in_array($m->getFormat(), ["sources", "source"])) {
                    foreach (json_decode($m->getValue(), true) as $v) {
                        if (!in_array(pathinfo($v['url'], PATHINFO_EXTENSION), $m->getAllow()['values']))
                            $m->setError("valor não é permitido");
                    }
                } else {
                    foreach (json_decode($m->getValue(), true) as $item) {
                        if (!empty($item) && !in_array($item, $m->getAllow()['values']))
                            $m->setError("valor não é permitido");
                    }
                }
            }
        } elseif (!empty($m->getAllow()['values']) && $m->getKey() !== "information" && !empty($m->getValue()) && !in_array($m->getValue(), $m->getAllow()['values'])) {
            $m->setError("valor não é permitido");
        }
    }
}