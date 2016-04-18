<?php

namespace sankam\gii\migration;

use Yii;
use yii\gii\CodeFile;
use yii\db\Schema;

/**
 * Migration generator for Gii.
 */
class Generator extends \yii\gii\Generator
{
    public $tableName;
    public $migrationName;

    public $migrationPath = '@app/migrations';
    public $db = 'db';
    public $useTablePrefix = true;
    public $tableOptions = '';
    public $useSafe = false;
    public $dropTable = true;

    public $migrationDataType = 'structure';


    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'Migration Generator';
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return 'This generator generates a migration file for the specified database table.';
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        // set $migrationName on init so that it can be loaded via $_POST later on preview and generation
        // if we don't set it, it will be different every time, and generator will give an error
        $this->migrationName = 'm' . gmdate('ymd_His') . '_create';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            [['db', 'migrationPath', 'tableName', 'migrationName', 'migrationDataType'], 'filter', 'filter' => 'trim'],
            [['db', 'migrationPath', 'tableName', 'migrationName', 'migrationDataType'], 'required'],
            [['useTablePrefix', 'useSafe', 'dropTable'], 'boolean'],
            [['useTablePrefix', 'tableOptions'], 'safe'],
            [['migrationPath'], 'filter', 'filter' => function($value) { return trim($value, '\\'); }],
            [['migrationName'], 'match', 'pattern' => '/^\w+$/', 'message' => 'The migration name should contain letters, digits and/or underscore characters only.'],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), [
            'tableName' => 'Table Name',
            'migrationName' => 'Migration Name',
            'migrationPath' => 'Migration Path',
            'db' => 'Database Connection ID',
            'useSafe' => 'Safe Mode',
            'migrationDataType' => 'Migration Data',
            'dropTable' => 'Drop Tables',
        ]);
    }

    /**
     * @inheritdoc
     */
    public function hints()
    {
        return array_merge(parent::hints(), [
            'tableName' => 'You can use "*" for all tables, or "," to separate tables',
            'migrationName' => 'Note that you should check the result migration code after generation and perform some corrections if needed, mostly due to inconsistency between MySQL and PHP types.',
            'useSafe' => 'DB logic implemented in "Safe Mode" will be enclosed within a DB transaction',
            'migrationDataType' => 'Please select the data you want to include in the migration',
            'dropTable' => 'Drop Table if exists in DB'
        ]);
    }

    /**
     * @inheritdoc
     */
    public function stickyAttributes()
    {
        return array_merge(parent::stickyAttributes(), ['migrationPath', 'db', 'useTablePrefix', 'tableOptions', 'useSafe', 'migrationDataType', 'dropTable']);
    }

    /**
     * @return array options for type drop-down
     */
    public function optsMigrationDataType()
    {
        return [
            'structure' => 'Schema',
            'data' => 'Data',
            'full' => 'Schema & Data',
        ];
    }

    /**
     * @inheritdoc
     */
    protected function getDbConnection()
    {
        return Yii::$app->get($this->db, false);
    }

    /**
     * @inheritdoc
     */
    public function autoCompleteData()
    {
        $db = $this->getDbConnection();
        if ($db !== null) {
            return [
                'tableName' => function () use ($db) {
                    return $db->getSchema()->getTableNames();
                },
            ];
        } else {
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function generate()
    {
        $migrationName = $this->migrationName;

        $files = [];
        $db = $this->getDbConnection();

        $tableNames = explode(',', $this->tableName);
        $tableNames = array_map('trim', $tableNames);
        if ($this->tableName == '*') {
            $tableNames = $db->schema->getTableNames();
        }

        $params = [
            'tableDefinitions' => $this->getTableDefinitions($tableNames),
            'migrationName' => $migrationName,
            'tableOptions' => $this->tableOptions,
            'migrationDataType' => $this->migrationDataType,
            'migrationTablesData' => $this->getTablesData($tableNames),
            'safe' => $this->useSafe,
            'drop' => (!empty($this->dropTable)) ? true : false,
        ];

        $files[] = new CodeFile(
            Yii::getAlias(str_replace('\\', '/', $this->migrationPath)) . '/' . $migrationName . '.php',
            $this->render('migration.php', $params)
        );

        return $files;
    }


    /**
     * Returns information about table definitions
     * @param string[] $tableNames
     * @return array Information about table, columns, indexes and foreign keys
     */
    protected function getTableDefinitions($tableNames)
    {
        $db = $this->getDbConnection();

        $tableDefinitions = [];
        foreach ($tableNames as $tableName) {
            $table = $db->getTableSchema($tableName);
            if (!$table) continue;

            $createTableSql = $this->getCreateTableSql($tableName);


            $tableDefinition = [];
            $tableDefinition['primary'] = $this->getPrimaryKeys($tableName);
            $tableDefinition['columns'] = $this->getColumns($table, count($tableDefinition['primary']));
            $tableDefinition['indexes'] = $this->getIndexes($tableName);
            $tableDefinition['foreignKeys'] = $this->getForeignKeys($createTableSql);
            $tableDefinition['tableComment'] = $this->getTableComment($createTableSql);

            if ($this->useTablePrefix) {
                $tableName = $this->getTableAlias($tableName);

                foreach ($db->schema->getTableNames() as $dbTableName) {
                    $dbTableAlias = $this->getTableAlias($dbTableName);
                    $createTableSql = str_replace('`'.$dbTableName.'`', $dbTableAlias, $createTableSql);
                }
            }

            $tableDefinition['createTableSql'] = $createTableSql;

            $tableDefinitions[$tableName] = $tableDefinition;
        }

        return $tableDefinitions;
    }

    /**
     * Gets the CREATE TABLE sql string.
     * @param string $tableName
     * @return string the result of 'SHOW CREATE TABLE'
     */
    protected function getCreateTableSql($tableName)
    {
        $db = $this->getDbConnection();

        $row = $db->createCommand('SHOW CREATE TABLE ' . $db->quoteTableName($tableName))->queryOne();
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }

        return $sql;
    }

    /**
     * @param \yii\db\TableSchema $table
     * @return array Information about columns
     */
    protected function getColumns($table, $primary = 0)
    {
        $columns = [];
        foreach ($table->columns as $name => $column) {
            $columnDefinition = [];
            $additional = '';

            if ($column->isPrimaryKey && $column->autoIncrement) {
                if ($column->type == Schema::TYPE_INTEGER) {
                    $columnDefinition[] = '$this->primaryKey()';
                } elseif ($column->type == Schema::TYPE_BIGINT) {
                    $columnDefinition[] = '$this->bigPrimaryKey()';
                } else {
                    $typeFunction = $this->getTypeFunction($column);
                    $columnDefinition[] = $typeFunction;
                    if($primary < 2) {
                        $additional .= ' PRIMARY KEY';
                    }
                }
            } else {
                $typeFunction = $this->getTypeFunction($column);
                $columnDefinition[] = $typeFunction;

                if ($column->isPrimaryKey) {
                    if($primary < 2) {
                        $additional .= ' PRIMARY KEY';
                    }
                }
            }

            if ($column->defaultValue !== null) {
                $columnDefinition[] = "->defaultValue('" . $column->defaultValue . "')";
            }

            if (!$column->allowNull) {
                $columnDefinition[] = '->notNull()';
            }

            if ($column->comment) {
                $additional .= " COMMENT \"" . addcslashes($column->comment, "'") . "\"";
            }

            if ($additional) {
                $columnDefinition[] = " . '{$additional}'";
            }

            $columns[$name] = $columnDefinition;
        }

        return $columns;
    }

    /**
     * Gets the Table Data.
     * @param string $tableName
     * @return string the result of 'SHOW CREATE TABLE'
     */
    protected function getTablesData($tableNames)
    {
        if($this->migrationDataType == 'structure') {
            return false;
        }

        $data = [];

        foreach ($tableNames as $tableName) {
            $data[$this->getTableAlias($tableName)] = $this->getTableData($tableName);
        }

        return $data;
    }

    /**
     * Gets the Table Data SQL.
     * @param string $tableName
     */
    protected function getTableData($tableName)
    {
        $db = $this->getDbConnection();

        $data = [];

        $primary = $this->getPrimaryKeys($tableName);

        $aliasTableName = $this->getTableAlias($tableName);

        $rows = $db->createCommand('SELECT * FROM ' .$aliasTableName)->queryAll();

        if(!empty($rows)) {
            foreach ($rows as $key => $row_data) {
                $pre_data = '';

                $data['data']['up'][] = $row_data;
                $pre_data = $db->createCommand()->insert($aliasTableName, $row_data)->getRawSql().";";
                $data['sql']['up'][] = str_replace('`'.$tableName.'`', $aliasTableName, $pre_data);

                if(!empty($primary)) {
                    $where = [];
                    foreach ($primary as $key => $primary_key) {
                        $where[$primary_key] = $row_data[$primary_key];
                    }
                } else {
                    $where = $row_data;
                }

                $data['data']['down'][] = $where;
                $pre_data = $db->createCommand()->delete($aliasTableName, $where)->getRawSql().";";
                $data['sql']['down'][] = str_replace('`'.$tableName.'`', $aliasTableName, $pre_data);
            }
        }

        return $data;
    }

    /**
     * Returns the type function which will be called in migration class to define column type
     * @param \yii\db\ColumnSchema $column
     * @return string Type function like "$this->integer()"
     */
    protected function getTypeFunction($column)
    {
        $typeFunctions = [
            Schema::TYPE_PK => 'primaryKey',
            Schema::TYPE_BIGPK => 'bigPrimaryKey',
            Schema::TYPE_STRING => 'string',
            //Schema::TYPE_TEXT => 'text',
            //Schema::TYPE_SMALLINT => 'smallInteger',
            Schema::TYPE_INTEGER => 'integer',
            Schema::TYPE_BIGINT => 'bigInteger',
            Schema::TYPE_FLOAT => 'float',
            Schema::TYPE_DOUBLE => 'double',
            Schema::TYPE_DECIMAL => 'decimal',
            Schema::TYPE_DATETIME => 'dateTime',
            Schema::TYPE_TIMESTAMP => 'timestamp',
            Schema::TYPE_TIME => 'time',
            Schema::TYPE_DATE => 'date',
            //Schema::TYPE_BINARY => 'binary',
            Schema::TYPE_BOOLEAN => 'boolean',
            Schema::TYPE_MONEY => 'money',
        ];


        $typeFunction = '$this->unknownType()';
        if (isset($typeFunctions[$column->type])
                && ($column->type != Schema::TYPE_STRING || preg_match('/^varchar/i', $column->dbType))
        ) {
            $typeFunction = '$this->'.$typeFunctions[$column->type]
                . '('
                . ($column->size ? $column->size : '')
                . ($column->scale ? ', '.$column->scale : '')
                . ')';

            // special fix for integer - don't set default integer length
            if ($typeFunction == '$this->integer(11)') {
                $typeFunction = '$this->integer()';
            }
        } else {
            $db = $this->getDbConnection();
            $dbType = $column->dbType;
            $dbType = strtoupper($dbType);
            if (isset($db->getSchema()->typeMap[$dbType])) {
                if ($column->size) $dbType .= '('.$column->size.')';
            }
            $typeFunction = "\$this->getDb()->getSchema()->createColumnSchemaBuilder('{$dbType}')";
        }

        return $typeFunction;
    }

    /**
     * @param string $tableName
     * @return array Information about table indexes
     */
    protected function getIndexes($tableName)
    {
        $db = $this->getDbConnection();
        $indexesData = $db->createCommand('SHOW INDEX FROM ' . $db->quoteTableName($tableName))->queryAll();

        $indexes = [];
        foreach ($indexesData as $row) {
            if ($row['Key_name'] == 'PRIMARY') continue;

            $indexes[ $row['Key_name'] ]['isUnique'] = ((int)$row['Non_unique'] ? 'false' : 'true');
            $indexes[ $row['Key_name'] ]['columns'][ (int)$row['Seq_in_index'] - 1 ] = $row['Column_name'];
            $indexes[ $row['Key_name'] ]['Index_type'] = $row['Index_type'];
        }

        return $indexes;
    }

    /**
     * @param string $tableName
     * @return array Information about table indexes
     */
    protected function getPrimaryKeys($tableName)
    {
        $db = $this->getDbConnection();
        $indexesData = $db->createCommand('SHOW INDEX FROM ' . $db->quoteTableName($tableName))->queryAll();

        $keys = [];
        foreach ($indexesData as $row) {
            if ($row['Key_name'] == 'PRIMARY') {
                $keys[] = $row['Column_name'];
            } else {
                continue;
            }
        }

        return $keys;
    }

    /**
     * @param string $createTableSql CREATE TABLE sql string which is returned by getCreateTableSql() function
     * @return array Information about table foreign keys
     */
    protected function getForeignKeys($createTableSql)
    {
        $foreignKeys = [];
        preg_match_all("/CONSTRAINT ([^\(\)]+) FOREIGN KEY \(([^\(\)]+)\) REFERENCES ([^\(\)]+) \(([^\(\)]+)\)( ON DELETE (CASCADE|RESTRICT|SET NULL|NO ACTION))?( ON UPDATE (CASCADE|RESTRICT|SET NULL|NO ACTION))?/msui", $createTableSql, $matches);

        foreach ($matches[1] as $i => $foreignKey) {
            $column = $matches[2][$i];
            $foreignTable = $matches[3][$i];
            $foreignColumn = $matches[4][$i];

            $column = str_replace('`', '', $column);
            $foreignKey = str_replace('`', '', $foreignKey);
            $foreignTable = str_replace('`', '', $foreignTable);
            $foreignColumn = str_replace('`', '', $foreignColumn);

            $foreignTable = $this->getTableAlias($foreignTable);

            $foreignKeys[$foreignKey] = [
                'column' => $column,
                'foreignTable' => $foreignTable,
                'foreignColumn' => $foreignColumn,
                'onDelete' => $matches[6][$i],
                'onUpdate' => $matches[8][$i],
            ];
        }

        return $foreignKeys;
    }

    /**
     * @param string $createTableSql CREATE TABLE sql string which is returned by getCreateTableSql() function
     * @return string table comment
     */
    protected function getTableComment($createTableSql)
    {
        preg_match_all("/^CREATE TABLE [^\(]*\(.*\)[^\)]*COMMENT='(.*)'[^\)]*$/msui", $createTableSql, $matches);
        $tableComment = (isset($matches[1][0]) ? $matches[1][0] : '');
        return $tableComment;
    }

    /**
     * Converts real table name into table alias with '%' sign instead of $db->tablePrefix string
     * Example: db_prefix_my_table => {{%my_table}}
     * @param string $tableName
     * @return string table alias
     */
    protected function getTableAlias($tableName)
    {
        $db = $this->getDbConnection();
        $tableName = str_replace($db->tablePrefix, '%', $tableName);
        if (strpos($tableName, '%') === false) {
            $tableName = '%'.$tableName;
        }

        $tableName = '{{' . $tableName . '}}';
        return $tableName;
    }
}
