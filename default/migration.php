<?php

use yii\db\Schema;

/* @var $this yii\web\View */
/* @var $tableDefinitions yii\db\TableSchema[] */
/* @var $migrationName string */
/* @var $tableOptions string */


function writeLine($spaceCount, $str)
{
    echo str_repeat(' ', $spaceCount);
    echo $str;
    echo "\n";
}


$tmp = array_keys($tableDefinitions);
$lastTableName = end($tmp);

$tableOptions = addcslashes($tableOptions, "'");

$safeUp = ($safe) ? 'safeUp' : 'up';
$safeDown = ($safe) ? 'safeDown' : 'down';

echo "<?php\n";
?>

use yii\db\Schema;

class <?= $migrationName ?> extends yii\db\Migration
{
    public function <?php echo $safeUp;?>()
    {
<?php

    if($migrationDataType != 'data') {
        if($drop) {
            writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 0');\n");
        }
        foreach ($tableDefinitions as $tableName => $tableDefinition) {

            if($drop) {
                writeLine(8, "\$this->checkTable('{$tableName}');");
            }

            writeLine(8, "\$this->createTable('{$tableName}', [");

            foreach ($tableDefinition['columns'] as $columnName => $columnDefinition) {
                writeLine(12, "'$columnName' => " . implode('', $columnDefinition) . ",");
            }

            $currentTableOptions = $tableOptions;
            if ($tableDefinition['tableComment']) {
                $tableComment = addcslashes($tableDefinition['tableComment'], "'");
                $currentTableOptions .= " COMMENT=\"{$tableComment}\"";
                $currentTableOptions = trim($currentTableOptions);
            }

            writeLine(8, "]" . ($currentTableOptions ? ", '{$currentTableOptions}'" : '') . ");");

            if(!empty($tableDefinition['primary']) && count($tableDefinition['primary']) > 1) {
                writeLine(8, "\$this->addPrimaryKey('PK', '" . $tableName . "', " . json_encode($tableDefinition['primary']) ." );");
            }

            if ($tableDefinition['indexes']) echo "\n";
            foreach ($tableDefinition['indexes'] as $keyName => $keyDefinition) {
                if($keyDefinition['Index_type'] == 'FULLTEXT') continue;
                writeLine(8, "\$this->createIndex('{$keyName}', '{$tableName}', '" . implode(', ', $keyDefinition['columns']) . "', {$keyDefinition['isUnique']});");
            }

            if ($tableName != $lastTableName) echo "\n\n";
        }

        $foreignKeyExists = false;
        foreach ($tableDefinitions as $tableName => $tableDefinition) {
            if ($tableDefinition['foreignKeys']) {
                if (!$foreignKeyExists) { $foreignKeyExists = true; echo "\n"; }
                echo "\n";
            }

            // strange thing but if we want to get the same sql as in "SHOW CREATE TABLE", then we should add foreign keys in reverse order
            foreach (array_reverse($tableDefinition['foreignKeys']) as $foreignKeyName => $foreignKeyDefinition) {
                $onDelete = $foreignKeyDefinition['onDelete'];
                $onUpdate = $foreignKeyDefinition['onUpdate'];
                $onDelete = ($onDelete ? "'{$onDelete}'" : 'null');
                $onUpdate = ($onUpdate ? "'{$onUpdate}'" : 'null');
                writeLine(8, "\$this->addForeignKey('{$foreignKeyName}', '{$tableName}', '{$foreignKeyDefinition['column']}', '{$foreignKeyDefinition['foreignTable']}', '{$foreignKeyDefinition['foreignColumn']}', {$onDelete}, {$onUpdate});");
            }
            if ($tableName != $lastTableName) {
                echo "\n";
            } else {
                echo "\n\n";
            }
        }

        if($migrationDataType == 'full' && !empty($migrationTablesData)) {
            if(!$drop) {
                writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 0');\n");
            }
            foreach ($tableDefinitions as $tableName => $tableDefinition) {
                if(isset($migrationTablesData[$tableName]['data']['up']) && !empty($migrationTablesData[$tableName]['data']['up'])) {
                    writeLine(8, "# {$tableName}");

                    foreach ($migrationTablesData[$tableName]['data']['up'] as $rowKey => $rowDefinition) {
                        writeLine(8, "\$this->insert('{$tableName}', [");

                        foreach ($rowDefinition as $columnName => $columnDefinition) {
                            if ($columnDefinition == NULL) {
                                $columnDefinition = 'NULL';
                            }
                            writeLine(12, "'$columnName' => '" . addcslashes($columnDefinition, "'") . "',");
                        }

                        writeLine(8, "]);");
                    }
                    if ($tableName != $lastTableName) echo "\n";
                }
            }
            if(!$drop) {
                writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 1');");
            }
        }
    } else {
        if(!$drop) {
            writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 0');\n");
        }
        foreach ($tableDefinitions as $tableName => $tableDefinition) {
            if(isset($migrationTablesData[$tableName]['data']['up']) && !empty($migrationTablesData[$tableName]['data']['up'])) {

                writeLine(8, "# {$tableName}");

                foreach ($migrationTablesData[$tableName]['data']['up'] as $rowKey => $rowDefinition) {
                    writeLine(8, "\$this->insert('{$tableName}', [");

                    foreach ($rowDefinition as $columnName => $columnDefinition) {
                        if ($columnDefinition == NULL) {
                            $columnDefinition = 'NULL';
                        }
                        writeLine(12, "'$columnName' => '" . addcslashes($columnDefinition, "'") . "',");
                    }

                    writeLine(8, "]);\n");
                }

                if ($tableName != $lastTableName) echo "\n";
            }
        }
        if(!$drop) {
            writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 1');");
        }
    }
    if($drop) {
        writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 1');");
    }

?>
    }

    public function <?php echo $safeDown;?>()
    {
<?php
        if (count($tableDefinitions) > 0) {
            writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 0');");
            if (count($tableDefinitions) > 1) echo "\n";

            if($migrationDataType == 'data' || $migrationDataType == 'full') {
                foreach ($tableDefinitions as $tableName => $tableDefinition) {
                    if(isset($migrationTablesData[$tableName]['data']['down']) && !empty($migrationTablesData[$tableName]['data']['down'])) {
                        echo "\n";
                        writeLine(8, "# {$tableName}");
                        foreach ($migrationTablesData[$tableName]['data']['down'] as $rowKey => $rowDefinition) {
                            $where = [];
                            foreach ($rowDefinition as $columnName => $columnDefinition) {
                                $where[] = '[['.$columnName.']] = :'.$columnName;
                            }

                            writeLine(8, "\$this->delete('{$tableName}', '".implode(' AND ', $where)."', [");

                            foreach ($rowDefinition as $columnName => $columnDefinition) {
                                if ($columnDefinition == NULL) {
                                    $columnDefinition = 'NULL';
                                }
                                writeLine(12, "'$columnName' => '" . addcslashes($columnDefinition, "'") . "',");
                            }

                            writeLine(8, "]);");
                        }
                        echo "\n";
                    }
                }
                if($migrationDataType == 'full') {
                    echo "\n";
                   foreach (array_reverse($tableDefinitions) as $tableName => $tableDefinition) {
                        writeLine(8, "\$this->dropTable('{$tableName}');");
                    }
                }
            } else {
                foreach (array_reverse($tableDefinitions) as $tableName => $tableDefinition) {
                    writeLine(8, "\$this->dropTable('{$tableName}');");
                }
            }

            if (count($tableDefinitions) > 1) echo "\n";
            writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 1');");
        }
?>
    }
<?php if($drop):?>

    private function checkTable($table) {
        if ($this->getDb()->getSchema()->getTableSchema($table, true) != null) {
            $this->dropTable($table);
        }
    }
<?php endif;?>
}
