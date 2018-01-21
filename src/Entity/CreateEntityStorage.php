<?php

namespace Entity;

use ConnCrud\SqlCommand;

class CreateEntityStorage
{
    private $entityName;
    private $data;

    /**
     * @param string $entityName
     * @param array $data
    */
    public function __construct(string $entityName, array $data)
    {
        $this->entityName = $entityName;
        $this->data = $data;

        if (!$this->existEntityStorage($entityName)) {
            $this->prepareCommandToCreateTable();
            $this->createKeys();
        }
    }

    private function existEntityStorage($entity)
    {
        $sqlTest = new SqlCommand();
        $sqlTest->exeCommand("SHOW TABLES LIKE '" . $this->getPre($entity) . "'");

        return $sqlTest->getRowCount() > 0;
    }

    private function prepareCommandToCreateTable()
    {
        $string = "";
        if ($this->data && is_array($this->data)) {
            foreach ($this->data as $column => $dados) {
                if ($this->notIsMult($dados['key'] ?? null)) {
                    $string .= (empty($string) ? "CREATE TABLE IF NOT EXISTS `" . $this->getPre($this->entityName) . "` (" : ", ")
                        . "`{$column}` {$dados['type']}" . (isset($dados['size']) && !empty($dados['size']) ? "({$dados['size']}) " : " ")
                        . (!$dados['null'] ? "NOT NULL " : "")
                        . (isset($dados['default']) && !empty($dados['default']) ? $this->prepareDefault($dados['default']) : ($dados['null'] ? "DEFAULT NULL" : ""));
                }
            }

            $string .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8";
        }

        $this->exeSql($string);
    }

    private function notIsMult($key = null)
    {
        return !($key && ($key === "list_mult" || $key === "extend_mult"));
    }

    private function createKeys()
    {
        if ($this->data && is_array($this->data)) {
            $sql = new SqlCommand();
            foreach ($this->data as $column => $dados) {
                if ($dados['unique']) {
                    $sql->exeCommand("ALTER TABLE `" . PRE . $this->entityName . "` ADD UNIQUE KEY `unique_{$dados['identificador']}` (`{$column}`)");
                }
                if ($dados['indice']) {
                    $sql->exeCommand("ALTER TABLE `" . PRE . $this->entityName . "` ADD KEY `index_{$dados['identificador']}` (`{$column}`)");
                }

                if (!empty($dados['key'])) {
                    if ($dados['key'] === "primary") {
                        $this->exeSql("ALTER TABLE `" . $this->getPre($this->entityName) . "` ADD PRIMARY KEY (`{$column}`), MODIFY `{$column}` int(11) NOT NULL AUTO_INCREMENT");

                    } elseif (in_array($dados['key'], array("extend", "extend_mult", "list", "list_mult"))) {

                        if (isset($dados['key_delete']) && isset($dados['key_update']) && !empty($dados['table'])) {
                            if (!$this->existEntityStorage($dados['table'])) {
                                new Entity($dados['table']);
                            }

                            if ($dados['key'] === "extend" || $dados['key'] === "list") {
                                $this->createIndexFk($this->entityName, $column, $dados['table'], $dados['key_delete'], $dados['key_update']);
                            } else {
                                $this->createRelationalTable($dados);
                            }
                        }
                    }
                }
            }
        }
    }

    private function createRelationalTable($dados)
    {
        $table = $this->entityName . "_" . $dados['table'];

        $string = "CREATE TABLE IF NOT EXISTS `" . $this->getPre($table) . "` ("
            . "`{$this->entityName}_id` INT(11) NOT NULL,"
            . "`{$dados['table']}_id` INT(11) NOT NULL"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8";

        $this->exeSql($string);

        $this->createIndexFk($table, $this->entityName."_id", $this->entityName, $dados['key_delete'], $dados['key_update']);
        $this->createIndexFk($table, $dados['table']."_id", $dados['table'], $dados['key_delete'], $dados['key_update']);
    }

    private function createIndexFk($table, $column, $tableTarget, $delete, $update)
    {
        $exe = new SqlCommand();
        $exe->exeCommand("ALTER TABLE `" . $this->getPre($table) . "` ADD KEY `fk_{$column}` (`{$column}`)");
        $exe->exeCommand("ALTER TABLE `" . $this->getPre($table) . "` ADD CONSTRAINT `" . $this->getPre($column . "_" . $table) . "` FOREIGN KEY (`{$column}`) REFERENCES `" . $this->getPre($tableTarget) . "` (`id`) ON DELETE " . strtoupper($delete) . " ON UPDATE " . strtoupper($update));
    }

    private function prepareDefault($default)
    {
        if ($default === 'datetime' || $default === 'date' || $default === 'time') {
            return "";
        }

        if (is_numeric($default)) {
            return "DEFAULT {$default}";
        }
        return "DEFAULT '{$default}'";
    }

    private function exeSql($sql)
    {
        $exe = new SqlCommand();
        $exe->exeCommand($sql);
    }

    private function getPre(string $table): string
    {
        return (defined("PRE") && !preg_match("/^" . PRE . "/i", $table) ? PRE : "") . $table;
    }
}