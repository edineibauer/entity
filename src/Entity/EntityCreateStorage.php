<?php

/**
 * <b>CreateTable:</b>
 * Obtem um arquivo JSON e o cria a tabela relacionada a ele
 *
 * @copyright (c) 2017, Edinei J. Bauer
 */

namespace Entity;

use ConnCrud\SqlCommand;

abstract class EntityCreateStorage extends EntityManagementData
{
    private $library;
    private $entityName;
    private $data;

    protected function createStorageEntity($entityName, $library, $data)
    {
        $this->entityName = $entityName;
        $this->library = $library;
        parent::setTable($this->entityName);
        $this->data = $data;

        if(!$this->existEntityStorage($entityName)) {
            $this->prepareCommandToCreateTable();
            $this->createKeys();
        }
    }

    private function existEntityStorage($entity)
    {
        $sqlTest = new SqlCommand();
        $sqlTest->exeCommand("SHOW TABLES LIKE '" . parent::getPre($entity) . "'");

        return $sqlTest->getRowCount() > 0;
    }

    private function prepareCommandToCreateTable()
    {
        $string = "";
        if ($this->data && is_array($this->data)) {
            foreach ($this->data as $column => $dados) {
                $string = (empty($string) ? "CREATE TABLE IF NOT EXISTS `" . parent::getPre($this->entityName) . "` (" : $string . ", ")
                    . "`{$column}` {$dados['type']}" . (isset($dados['size']) ? "({$dados['size']}) " : " ")
                    . (isset($dados['null']) && !$dados['null'] ? "NOT NULL " : "")
                    . (isset($dados['default']) ? $this->prepareDefault($dados['default']) : (!isset($dados['null']) || $dados['null'] ? "DEFAULT NULL" : ""));
            }

            $string .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8";
        }

        $this->exeSql($string);
    }

    private function createKeys()
    {
        if ($this->data && is_array($this->data)) {
            $sql = new SqlCommand();
            foreach ($this->data as $column => $dados) {
                if($dados['unique']) {
                    $sql->exeCommand("ALTER TABLE `" . PRE . $this->entityName . "` ADD UNIQUE KEY `{$column}` (`{$column}`)");
                }
                if($dados['indice']) {
                    $sql->exeCommand("ALTER TABLE `" . PRE . $this->entityName . "` ADD KEY `{$column}` (`{$column}`)");
                }

                if(!empty($dados['key'])) {
                    switch ($dados['key']) {
                        case "primary":
                            $this->exeSql("ALTER TABLE `" . parent::getPre($this->entityName) . "` ADD PRIMARY KEY (`{$column}`), MODIFY `{$column}` int(11) NOT NULL AUTO_INCREMENT");
                            break;
                        case "fk":
                            if (isset($dados['key_delete']) && isset($dados['key_update']) && isset($dados['table'])) {
                                if (!$this->existEntityStorage($dados['table'])) {
                                    new Entity($dados['table'], $this->library);
                                }

                                $this->exeSql("ALTER TABLE `" . parent::getPre($this->entityName) . "` ADD KEY `{$column}` (`{$column}`)");
                                $this->exeSql("ALTER TABLE `" . parent::getPre($this->entityName) . "` ADD CONSTRAINT `" . parent::getPre($column . "_" . $this->entityName) . "` FOREIGN KEY (`{$column}`) REFERENCES `" . parent::getPre($dados['table']) . "` (`id`) ON DELETE " . strtoupper($dados['key_delete']) . " ON UPDATE " . strtoupper($dados['key_update']));
                            }
                            break;
                    }
                }
            }
        }
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
}
