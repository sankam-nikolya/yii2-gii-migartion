<?php

use yii\db\Schema;

/* @var $this yii\web\View */
/* @var $tableDefinitions yii\db\TableSchema[] */
/* @var $migrationName string */

function writeLine($spaceCount, $str)
{
    echo str_repeat(' ', $spaceCount);
    echo $str;
    echo "\n";
}


$tmp = array_keys($tableDefinitions);
$lastTableName = end($tmp);

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
        writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 0');\n");
        foreach ($tableDefinitions as $tableName => $tableDefinition) {
            if($drop) {
                writeLine(8, "\$this->execute('DROP TABLE IF EXISTS {$tableName}');");
            }
            writeLine(8, '$this->execute("');

            echo str_repeat(' ', 12);
            $tableDefinition['createTableSql'] = str_replace("\n", "\n".str_repeat(' ', 12), $tableDefinition['createTableSql']);
            echo addcslashes($tableDefinition['createTableSql'], '"');
            echo "\n";

            writeLine(8, '");');
        }
        if($migrationDataType == 'full' && !empty($migrationTablesData)) {
            echo "\n\n";
            foreach ($tableDefinitions as $tableName => $tableDefinition) {
                if(isset($migrationTablesData[$tableName]['sql']['up']) && !empty($migrationTablesData[$tableName]['sql']['up'])) {

                    foreach ($migrationTablesData[$tableName]['sql']['up'] as $rowKey => $rowSql) {
                        writeLine(8, '$this->execute("'.$rowSql.'");');
                    }
                }
                if ($tableName != $lastTableName) echo "\n";
            }
        }
        writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 1');");
    } else {
        writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 0');\n");
        foreach ($tableDefinitions as $tableName => $tableDefinition) {
            if(isset($migrationTablesData[$tableName]['sql']['up']) && !empty($migrationTablesData[$tableName]['sql']['up'])) {

                foreach ($migrationTablesData[$tableName]['sql']['up'] as $rowKey => $rowSql) {
                    writeLine(8, '$this->execute("'.$rowSql.'");');
                }
            }
            if ($tableName != $lastTableName) echo "\n";
        }
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

            if($migrationDataType == 'data' || $migrationDataType == 'full' ) {
                echo "\n";
                foreach ($tableDefinitions as $tableName => $tableDefinition) {
                    if(isset($migrationTablesData[$tableName]['sql']['down']) && !empty($migrationTablesData[$tableName]['sql']['down'])) {

                        foreach ($migrationTablesData[$tableName]['sql']['down'] as $rowKey => $rowSql) {
                            writeLine(8, '$this->execute("'.$rowSql.'");');
                        }
                    }

                    if ($tableName != $lastTableName) echo "\n";
                }

                if($migrationDataType == 'full') {
                    echo "\n";
                    foreach ($tableDefinitions as $tableName => $tableDefinition) {
                        writeLine(8, "\$this->execute('DROP TABLE {$tableName}');");
                        if ($tableName != $lastTableName) echo "\n";
                    }
                }
                echo "\n";

            } else {
                foreach (array_reverse($tableDefinitions) as $tableName => $tableDefinition) {
                    writeLine(8, "\$this->execute('DROP TABLE {$tableName}');");
                }
            }


            if (count($tableDefinitions) > 1) echo "\n";
            writeLine(8, "\$this->execute('SET FOREIGN_KEY_CHECKS = 1');");
        }
?>
    }
}
