<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Developer Tools                                                |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-present Phalcon Team (https://www.phalconphp.com)   |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file LICENSE.txt.                             |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Andres Gutierrez <andres@phalconphp.com>                      |
  |          Eduar Carvajal <eduar@phalconphp.com>                         |
  |          Serghei Iakovlev <serghei@phalconphp.com>                     |
  +------------------------------------------------------------------------+
 */

namespace Phalcon\Generator;

use Phalcon\Options\OptionsAware as ModelOption;
use Phalcon\Utils;

/**
 * Snippet Class
 *
 * @package Phalcon\Generator
 */
class Snippet
{

    public function getStaticModelMethod($className)
    {
        $template = <<<EOD
    /**
     * @param string \$class
     * @return %s
     */
    public static function model(\$class = __CLASS__)
    {
        return parent::model(\$class);
    }
EOD;

        return PHP_EOL . sprintf($template, $className) . PHP_EOL;
    }

    public function getDatabaseSource($source)
    {
        $getSource = '    public $useDb = \'%s\';';

        return PHP_EOL . sprintf($getSource, $source) . PHP_EOL;
    }

    public function getModelSource($source)
    {
        //         $getSource = <<<EOD
        //     /**
        //      * Returns table name mapped in the model.
        //      *
        //      * @return string
        //      */
        //     public function getSource()
        //     {
        //         return '%s';
        //     }
        // EOD;
        //
        $getSource = '    public $useTable = \'%s\';';

        return PHP_EOL . sprintf($getSource, $source) . PHP_EOL;
    }

    public function getSetter($originalFieldName, $fieldName, $type, $setterName)
    {
        $templateSetter = <<<EOD
    /**
     * Method to set the value of field %s
     *
     * @param %s \$%s
     * @return \$this
     */
    public function set%s(\$%s)
    {
        \$this->%s = \$%s;

        return \$this;
    }
EOD;

        return PHP_EOL . sprintf(
            $templateSetter,
            $originalFieldName,
            $type,
            $fieldName,
            $setterName,
            $fieldName,
            $fieldName,
            $fieldName
        ) . PHP_EOL;
    }

    public function getValidateInclusion($fieldName, $varItems)
    {
        $templateValidateInclusion = <<<EOD
        \$this->validate(
            new InclusionIn(
                [
                    'field'    => '%s',
                    'domain'   => [%s],
                    'required' => true,
                ]
            )
        );
EOD;

        return PHP_EOL . sprintf($templateValidateInclusion, $fieldName, $varItems) . PHP_EOL;
    }

    public function getValidationsMethod(array $pieces)
    {
        $templateValidations = <<<EOD
    /**
     * Validations and business logic
     *
     * @return boolean
     */
    public function validation()
    {
        \$validator = new Validation();

%s
    }
EOD;

        return PHP_EOL . sprintf($templateValidations, join('', $pieces)) . PHP_EOL;
    }

    /**
     * @param ModelOption $modelOptions
     * @return string
     */
    public function getClass(
        $namespace,
        $useDefinition,
        $classDoc = '',
        $abstract = '',
        $modelOptions,
        $extends = '',
        $content,
        $license = ''
    ) {
        $templateCode = <<<EOD
<?php

%s%s%s%s%sclass %s extends %s
{
%s
}
EOD;

$useDefinition .= 'use Phalcon\Mvc\Model\Transaction;' . PHP_EOL;
        return sprintf(
            $templateCode,
            $license,
            $namespace,
            $useDefinition,
            $classDoc,
            $abstract,
            $modelOptions->getOption('className'),
            $extends,
            $content
        )
            . PHP_EOL;
    }

    public function getClassDoc($className, $namespace = '')
    {
        if (!empty($namespace)) {
            $namespace = str_replace('namespace ', '', $namespace);
            $namespace = str_replace(';', '', $namespace);
            $namespace = str_replace(["\r", "\n"], '', $namespace);

            $namespace = PHP_EOL . ' * @package ' . $namespace;
        }

        $classDoc = <<<EOD
/**
 * %s
 * %s
 */
EOD;

        return sprintf($classDoc, $className, $namespace) . PHP_EOL;
    }

    public function getValidateEmail($fieldName)
    {
        $templateValidateEmail = <<<EOD
        \$validator->add(
            '%s',
            new EmailValidator(
                [
                    'model'   => \$this,
                    'message' => 'Please enter a correct email address',
                ]
            )
        );
EOD;

        return sprintf($templateValidateEmail, $fieldName) . PHP_EOL . PHP_EOL;
    }

    public function getValidationEnd()
    {
        $templateValidationFailed = <<<EOD
        return \$this->validate(\$validator);
EOD;

        return $templateValidationFailed;
    }

    public function getAttributes(
        $type,
        $visibility,
        \Phalcon\Db\ColumnInterface $field,
        $annotate = false,
        $customFieldName = null
    ) {
        $fieldName = $customFieldName ? : $field->getName();

        if ($annotate) {
            $templateAttributes = <<<EOD
    /**
     *
     * @var %s%s%s
     * @Column(column="%s", type="%s"%s, nullable=%s)
     */
    %s \$%s;
EOD;

            return PHP_EOL . sprintf(
                $templateAttributes,
                $type,
                $field->isPrimary() ? PHP_EOL . '     * @Primary' : '',
                $field->isAutoIncrement() ? PHP_EOL . '     * @Identity' : '',
                $field->getName(),
                $type,
                $field->getSize() ? ', length=' . $field->getSize() : '',
                $field->isNotNull() ? 'false' : 'true',
                $visibility,
                $fieldName
            ) . PHP_EOL;
        } else {
            $templateAttributes = <<<EOD
    /**
     *
     * @var %s
     */
    %s \$%s;
EOD;

            return PHP_EOL . sprintf($templateAttributes, $type, $visibility, $fieldName) . PHP_EOL;
        }
    }

    public function getGetterMap($fieldName, $type, $setterName, $typeMap)
    {
        $templateGetterMap = <<<EOD
    /**
     * Returns the value of field %s
     *
     * @return %s
     */
    public function get%s()
    {
        if (\$this->%s) {
            return new %s(\$this->%s);
        } else {
           return null;
        }
    }
EOD;

        return PHP_EOL . sprintf(
            $templateGetterMap,
            $fieldName,
            $type,
            $setterName,
            $fieldName,
            $typeMap,
            $fieldName
        ) . PHP_EOL;
    }

    public function getGetter($fieldName, $type, $getterName)
    {
        $templateGetter = <<<EOD
    /**
     * Returns the value of field %s
     *
     * @return %s
     */
    public function get%s()
    {
        return \$this->%s;
    }
EOD;

        return PHP_EOL . sprintf($templateGetter, $fieldName, $type, $getterName, $fieldName) . PHP_EOL;
    }

    public function getInitialize(array $pieces)
    {
        $templateInitialize = <<<EOD
    /**
     * Initialize method for model.
     */
    public function initialize()
    {
%s
    }
EOD;

        return PHP_EOL . sprintf($templateInitialize, rtrim(join('', $pieces))) . PHP_EOL;
    }

    public function getModelFind($className)
    {
        $templateFind = <<<EOD
    /**
     * Allows to query a set of records that match the specified conditions
     *
     * @param mixed \$parameters
     * @return %s[]|%s|\Phalcon\Mvc\Model\ResultSetInterface
     */
    public static function find(\$parameters = null)
    {
        return parent::find(\$parameters);
    }
EOD;

        return PHP_EOL . sprintf($templateFind, $className, $className) . PHP_EOL;
    }

    public function getModelFindFirst($className)
    {
        $templateFind = <<<EOD
    /**
     * Allows to query the first record that match the specified conditions
     *
     * @param mixed \$parameters
     * @return %s|\Phalcon\Mvc\Model\ResultInterface
     */
    public static function findFirst(\$parameters = null)
    {
        return parent::findFirst(\$parameters);
    }
EOD;

        return PHP_EOL . sprintf($templateFind, $className, $className) . PHP_EOL;
    }

    /**
     * Builds a PHP syntax with all the options in the array
     *
     * @param  array $options
     * @return string PHP syntax
     */
    public function getRelationOptions(array $options = null)
    {
        if (empty($options)) {
            return 'NULL';
        }

        $values = [];
        foreach ($options as $name => $val) {
            if (is_bool($val)) {
                $val = $val ? 'true' : 'false';
            } elseif (!is_numeric($val)) {
                $val = "'{$val}'";
            }

            $values[] = sprintf('\'%s\' => %s', $name, $val);
        }

        $syntax = '[' . join(',', $values) . ']';

        return $syntax;
    }

    /**
     * @param \Phalcon\Db\ColumnInterface[] $fields
     * @param bool $camelize
     * @return string
     */
    public function getColumnMap($fields, $camelize = false)
    {
        $template = <<<EOD
    /**
     * Independent Column Mapping.
     * Keys are the real names in the table and the values their names in the application
     *
     * @return array
     */
    public function columnMap()
    {
        return [
            %s
        ];
    }
EOD;

        $contents = [];
        foreach ($fields as $field) {
            $name = $field->getName();
            $contents[] = sprintf('\'%s\' => \'%s\'', $name, $camelize ? Utils::lowerCamelize($name) : $name);
        }

        return PHP_EOL . sprintf($template, join(",\n            ", $contents)) . PHP_EOL;
    }

    public function getMigrationMorph($className, $table, $tableDefinition)
    {
        $template = <<<EOD
use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Db\Reference;
use Phalcon\Mvc\Model\Migration;

/**
 * Class %s
 */
class %s extends Migration
{
    /**
     * Define the table structure
     *
     * @return void
     */
    public function morph()
    {
        \$this->morphTable('%s', [
%s
EOD;

        return sprintf(
            $template,
            $className,
            $className,
            $table,
            $this->getMigrationDefinition('columns', $tableDefinition)
        );
    }

    public function getMigrationDefinition($name, $definition)
    {
        $template = <<<EOD
                '%s' => [
                    %s
                ],

EOD;

        return sprintf($template, $name, join(",\n                    ", $definition));
    }

    public function getMigrationUp()
    {
        $template = <<<EOD

    /**
     * Run the migrations
     *
     * @return void
     */
    public function up()
    {

EOD;

        return $template;
    }

    public function getMigrationDown()
    {
        $template = <<<EOD

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down()
    {

EOD;

        return $template;
    }

    public function getMigrationBatchInsert($table, $allFields)
    {
        $template = <<<EOD
        \$this->batchInsert('%s', [
                %s
            ]
        );
EOD;

        return sprintf($template, $table, join(",\n                ", $allFields));
    }

    public function getMigrationAfterCreateTable($table, $allFields)
    {
        $template = <<<EOD

    /**
     * This method is called after the table was created
     *
     * @return void
     */
     public function afterCreateTable()
     {
        \$this->batchInsert('%s', [
                %s
            ]
        );
     }
EOD;

        return sprintf($template, $table, join(",\n                ", $allFields));
    }

    public function getMigrationBatchDelete($table)
    {
        $template = <<<EOD
        \$this->batchDelete('%s');
EOD;

        return sprintf($template, $table);
    }

    public function getColumnDefinition($field, $fieldDefinition)
    {
        $template = <<<EOD
new Column(
                        '%s',
                        [
                            %s
                        ]
                    )
EOD;

        return sprintf($template, $field, join(",\n                            ", $fieldDefinition));
    }

    public function getIndexDefinition($indexName, $indexDefinition, $indexType = null)
    {
        $template = <<<EOD
new Index('%s', [%s], %s)
EOD;

        return sprintf($template, $indexName, join(", ", $indexDefinition), $indexType ? "'$indexType'" : 'null');
    }

    public function getReferenceDefinition($constraintName, $referenceDefinition)
    {
        $template = <<<EOD
new Reference(
                        '%s',
                        [
                            %s
                        ]
                    )
EOD;

        return sprintf($template, $constraintName, join(",\n                            ", $referenceDefinition));
    }

    public function getUse($class)
    {
        $templateUse = 'use %s;';

        return sprintf($templateUse, $class);
    }

    public function getUseAs($class, $alias)
    {
        $templateUseAs = 'use %s as %s;';

        return sprintf($templateUseAs, $class, $alias);
    }

    public function getThisMethod($method, $params)
    {
        $templateThis = "        \$this->%s(%s);" . PHP_EOL;

        return sprintf($templateThis, $method, '"' . $params . '"');
    }

    public function getRelation($relation, $column1, $entity, $column2, $alias)
    {
        $templateRelation = "        \$this->%s('%s', '%s', '%s', %s);" . PHP_EOL;

        return sprintf($templateRelation, $relation, $column1, $entity, $column2, $alias);
    }

    public function getRules($rules = [])
    {
        $lengthTempl = <<<EOT
            ['%s', 'length', ['max'=>%s, 'messageMaximum'=>'字段过长']],
EOT;


        $tempLine = <<<EOD
            %s
EOD;
        $template = <<<EOD
    /**
     * rules define
     * @return array
     */
    public function rules()
    {
        return [
%s
        ];
    }
EOD;
        $str = '';
        foreach ($rules as $key => $fields) {
            if (empty($fields)) {
                continue;
            }

            if ($key === 'length') {
                foreach ($fields as $field => $options) {
                    $str .= vsprintf($lengthTempl, [$field, $options['max']]) . PHP_EOL;
                }
                continue;
            }

            $str .= sprintf($tempLine, "['" . join(",", $fields) . "', '" . $key . "']," . PHP_EOL);
        }

        return PHP_EOL . sprintf($template, $str) . PHP_EOL;
    }

    /**
     * 获取
     * @param $fieldMaps
     * @return string
     */
    public function getColumnLabels($fieldMaps = [])
    {
        $template = <<<EOT
    /**
     * @return array
     */
    public function columnLabels() : array
    {
        return [
            %s
        ];
    }
EOT;

        $line = <<<EOT
            '%s'=>'%s',
EOT;

        $strs = '';
        foreach ($fieldMaps as $k => $v) {
            $strs .= vsprintf($line, [$k, $v]) . PHP_EOL;
        }

        return vsprintf($template, [$strs]);
    }

    /**
     * @return string
     */
    public function getDefaultSkeletonMethod()
    {
        $template = <<<EOT
    /**
     * 创建记录
     * @param array \$params
     * @param null|Transaction \$transaction
     * @return int
     */
    public function add(array \$params, ?Transaction \$transaction = null): int
    {
        \$model = new self();
        if (\$transaction != null) {
            \$model->setTransaction(\$transaction);
        }
        \$model->scenario = 'insert';

        if (!\$model->create(\$params)) {
            \$this->logger->error("创建{\$this->useTable}记录失败:" . \$this->getMessageAsString(\$model));
            return 0;
        }
        return \$model->id;
    }

    /**
     * AR方式根据id删除指定记录
     * @param int \$id
     * @param null|Transaction \$transaction
     * @return bool
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public function removeRecordByID(int \$id, ?Transaction \$transaction = null): bool
    {
        \$obj = self::findFirst([
            'conditions' => 'id=?0',
            'bind' => [\$id]
        ]);
        if (!\$obj) {
            \$this->logger->error('不存在的记录');
            throw new \App\Components\ModelException('不存在的记录', -1);
        }

        if (\$transaction != null) {
            \$obj->setTransaction(\$transaction);
        }

        \$obj->scenario = 'update';
        \$obj->status = 99;
        if (!\$obj->update()) {
            \$this->logger->error("删除{\$this->useTable}表数据失败:" . \$this->getMessageAsString(\$obj));
            return false;
        }
        return true;
    }

    /**
     * AR方式根据id更新指定记录
     * @param int \$id
     * @param array \$params
     * @param null|Transaction \$transaction
     * @return bool
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public function updateRecordByID(int \$id, array \$params, ?Transaction \$transaction = null): bool
    {
        \$obj = self::findFirst([
            'conditions' => 'id=?0',
            'bind' => [\$id]
        ]);
        if (!\$obj) {
            \$this->logger->error('不存在的记录');
            throw new \App\Components\ModelException('不存在的记录', -1);
        }
        if (\$transaction !== null) {
            \$obj->setTransaction(\$transaction);
        }

        \$obj->scenario = 'update';
        if (!\$obj->update(\$params)) {
            \$this->logger->error("更新{\$this->useTable}记录失败:" . \$this->getMessageAsString(\$obj));
            return false;
        }
        return true;
    }

    /**
     * 直接通过id删除指定记录
     * @param int \$id
     * @return bool
     */
    public function removeByID(int \$id): bool
    {
        \$result = \$this->writeConnection->updateAsDict(\$this->useTable, ['status' => 99], [
            'conditions' => 'id=?',
            'bind'       => [\$id],
        ]);

        return \$result;
    }

    /**
     * 直接通过id更新指定记录
     * @param int \$id
     * @param array \$params
     * @return bool
     */
    public function updateByID(int \$id, array \$params): bool
    {
        \$result = \$this->writeConnection->updateAsDict(\$this->useTable, \$params, [
            'conditions' => 'id=?',
            'bind'       => [\$id],
        ]);

        return \$result;
    }

    /**
     * 获取指定id的数据
     * @param int \$id
     * @return array
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public function getInfoById(int \$id): array
    {
        // self::cached(\KeyDef::\$testKeyDef, [\$id]);
        \$obj = self::findFirst([
            'conditions' => 'id=?0',
            'bind'       => [\$id],
        ]);
        if (!\$obj) {
            return [];
        }

        return \$obj->toArray();
    }

    /**
     * patch Info demo
     *
     * @param array \$list
     * @param string \$columnName
     * @param string \$patchColumn
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public function patchInfo(array &\$list, string \$columnName = 'xxx_id', string \$patchColumn = 'xxx_info'): void
    {
        \$ids = \xLab\Phalcon\Collection\ZArray::collectField(\$list, \$columnName);
        \$ids = array_filter(\$ids, function (\$id, \$key) {
            return \$id > 0;
        }, ARRAY_FILTER_USE_BOTH);
        \$ids = array_values(\$ids);
        if (empty(\$ids)) {
            foreach (\$list as &\$v) {
                \$v[\$patchColumn] = [];
            }

            return;
        }

        \$data = \$this->getInfoByIDs(\$ids);
        // generate map data
        // 这里的id根据实际情况替换
        \$map = \xLab\Phalcon\Collection\ZArray::getMapData(\$data, 'id');

        // patch
        foreach (\$list as \$k => &\$v) {
            if (isset(\$map[\$v[\$columnName]])) {
                \$v[\$patchColumn] = \$map[\$v[\$columnName]];
            } else {
                \$v[\$patchColumn] = [];
            }
        }
    }

    /**
     * @param array \$IDs
     * @return array
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public function getInfoByIDs(array \$IDs): array
    {
        // 这里的cache key一定跟getInfoByID一致，但是cached里面不用传参数
        // self::cached(\KeyDef::\$testKeyDef);
        \$data = self::find([
            'in' => ['id', \$IDs],
        ])->toArray();

        if (!\$data) {
            return [];
        }

        return \$data;
    }

    /**
     * @param array \$params
     * @return array
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public function getList(array \$params = []): array
    {
        // 创建依赖
        //\$dependency = new MemCacheDependency(\KeyDef::\$testDependencyDef);
        // 清除依赖缓存
        //\$dependency->refresh();
        // 设置缓存
        //self::cache(\$dependency, \KeyDef::\$testDependencyDef[1]);

        if (empty(\$params['page']) || \$params['page'] < 1) {
            \$params['page'] = 1;
        }
        if (empty(\$params['limit']) || \$params['limit'] < 1 || \$params['limit'] > MAX_PAGE_SIZE) {
            \$params['limit'] = self::PAGE_SIZE;
        }
        \$offset = (\$params['page'] - 1) * \$params['limit'];
        \$pageInfo = [
            'current_page' => (int)\$params['page'],
            'per_pages' => (int)\$params['limit'],
            'total' => 0
        ];

        \$bind = [];
        \$conditions = [];

        // demo code
        if (!empty(\$params['status'])) {
            // array_push(\$conditions, 'status<:status:');
            // \$bind['status'] = self::STATUS_DELETED;
        }

        \$obj = self::findFirst([
            'conditions' => implode(' and ', \$conditions),
            'bind' => \$bind,
            'columns' => 'count(id) as total'
        ]);
        if (\$obj->total == 0) {
            return ['list' => [], 'pageinfo' => \$pageInfo];
        }
        \$pageInfo['total'] = (int)\$obj->total;

        \$list = self::find([
            'conditions' => implode(' and ', \$conditions),
            'bind' => \$bind,
            'columns' => '*', // demo code
            'order' => 'id desc',
            'offset' => \$offset,
            'limit' => \$params['limit']
        ])->toArray();

        return ['list' => \$list, 'pageinfo' => \$pageInfo];
    }

    /**
     * [getList demo, 记得更新本注释]
     *
     * @param array \$params
     * @return array
     */
    public function getListBySQL(array \$params = []): array
    {
        if (empty(\$params['page']) || \$params['page'] < 1) {
            \$params['page'] = 1;
        }
        if (empty(\$params['limit']) || \$params['limit'] < 1 || \$params['limit'] > MAX_PAGE_SIZE) {
            \$params['limit'] = self::PAGE_SIZE;
        }
        \$offset = (\$params['page'] - 1) * \$params['limit'];
        \$pageInfo = [
            'current_page' => (int)\$params['page'],
            'per_pages' => (int)\$params['limit'],
            'total' => 0
        ];

        \$sql      = "SELECT * FROM {\$this->useDb}.{\$this->useTable} a";
        \$sqlCount = "SELECT count(a.id) as count FROM {\$this->useDb}.{\$this->useTable} a";

        \$conditions = [];
        \$binds     = [];
        \$joins     = [];
        if (!empty(\$params['xxx'])) {
            \$joins[]     = "  ";
            \$conditions[] = "";
            \$binds       = array_merge(\$binds, []);
        }

        if (!empty(\$params['id'])) {
            \$conditions[] = " a.id = ? ";
            \$binds[]     = \$params['id'];
        }
        if (!empty(\$params['status'])) {
            \$conditions[] = " a.status =? ";
            \$binds[]     = \$params['status'];
        } else {
            \$conditions[] = " a.status != ? ";
            \$binds[]     = self::STATUS_DELETED;
        }

        if (!empty(\$joins)) {
            \$sqlCount .= " " . implode(' ', \$joins);
            \$sql      .= " " . implode(' ', \$joins);
        }
        if (!empty(\$conditions)) {
            \$sqlCount .= " where " . implode(' and ', \$conditions);
            \$sql      .= " where " . implode(' and ', \$conditions);
        }

        \$sql   .= " order by a.id desc limit {\$offset}, " . self::PAGE_SIZE;
        \$count = \$this->readConnection->fetchOne(\$sqlCount, \Phalcon\Db::FETCH_ASSOC, \$binds);

        \$pageInfo['total'] = \$count['count'];

        \$list = \$this->readConnection->fetchAll(\$sql, \Phalcon\Db::FETCH_ASSOC, \$binds);

        if (!\$list) {
            return ['list' => [], 'pageinfo' => \$pageInfo];
        }

        return ['list' => \$list, 'pageinfo' => \$pageInfo];
    }
EOT;

        return $template;
    }

    /**
     * @param $namespace
     * @param $use
     * @param $className
     * @param $modelName
     * @return string
     */
    public function getServiceSkeleton($namespace, $use, $className, $modelName)
    {
        $template = <<<EOT
<?php
namespace %s;

use %s;
use Phalcon\Mvc\Model\Transaction;

class %s extends \App\Components\ModuleServiceBase
{
    /**
     * 创建记录
     * @param array \$params
     * @param null|Transaction \$transaction
     * @return int
     * @throws \Exception
     */
    public static function add(array \$params = [], ?Transaction \$transaction = null): int
    {
        return %s::model()->add(\$params, \$transaction);
    }

    /**
     * 通过id更新指定记录（多了一次查询操作），支持事务传递
     * @param int \$id
     * @param array \$params
     * @param null|Transaction \$transaction
     * @return bool
     */
    public static function updateRecordByID(int \$id, array \$params, ?Transaction \$transaction = null): bool
    {
        return %s::model()->updateRecordByID(\$id, \$params, \$transaction);
    }

    /**
     * 通过id删除指定记录（多了一次查询操作），支持事务传递
     * @param int \$id
     * @param null|Transaction \$transaction
     * @return bool
     */
    public static function removeRecordByID(int \$id, ?Transaction \$transaction = null): bool
    {
       return %s::model()->removeRecordByID(\$id, \$transaction);
    }

    /**
     * 通过id直接更新指定的记录
     * @param int \$id
     * @param array \$params
     * @return bool
     */
    public static function updateByID(int \$id, array \$params): bool
    {
        return %s::model()->updateByID(\$id, \$params);
    }

    /**
     * 通过id直接删除指定记录
     * @param int \$id
     * @return bool
     */
    public static function removeByID(int \$id): bool
    {
        return %s::model()->removeByID(\$id);
    }

    /**
     * 通过id获取指定记录的数据
     * @param int \$id
     * @return array|mixed
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public static function getInfoById(int \$id): array
    {
        return %s::model()->getInfoById(\$id);
    }

    /**
     * 获取多个id的数据
     * @param array \$ids
     * @return array|mixed
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public static function getInfoByIds(array \$ids): array
    {
        if (empty(\$ids)) {
            return [];
        }
        \$ids = array_values(array_unique(\$ids));
        return %s::model()->getInfoByIds(\$ids);
    }

    /**
     * @param \$list
     * @param string \$columnName
     * @param string \$patchColumn
     * @throws \xLab\Phalcon\Mvc\Exception
     */
    public static function patchInfo(&\$list, string \$columnName = 'xxx_id', string \$patchColumn = 'xxx_info')
    {
        %s::model()->patchInfo(\$list, \$columnName, \$patchColumn);
    }

    /**
     * @param array \$params
     * @return array
     */
    public static function getList(array \$params = []): array
    {
        return %s::model()->getList(\$params);
    }

    /**
     * @param array \$params
     * @return array
     */
    public static function getListBySQL(array \$params = []): array
    {
        return %s::model()->getListBySQL(\$params);
    }
}
EOT;
        return vsprintf($template, [
            $namespace, $use, $className,
            $modelName, $modelName, $modelName,
            $modelName, $modelName, $modelName, $modelName,
            $modelName, $modelName, $modelName, $modelName, $modelName
        ]);
    }
}
