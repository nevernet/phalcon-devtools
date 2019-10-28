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

namespace Phalcon\Builder;

use Phalcon\Db\Adapter\Pdo;
use Phalcon\Db\Column;
use Phalcon\Db\ReferenceInterface;
use Phalcon\Exception\InvalidArgumentException;
use Phalcon\Exception\InvalidParameterException;
use Phalcon\Exception\RuntimeException;
use Phalcon\Exception\WriteFileException;
use Phalcon\Generator\Snippet;
use Phalcon\Options\OptionsAware as ModelOption;
use Phalcon\Text;
use Phalcon\Utils;
use Phalcon\Validation;
use Phalcon\Validation\Validator\Email as EmailValidator;
use ReflectionClass;
use Phalcon\Script\Color;
use Phalcon\Db;

/**
 * ModelBuilderComponent
 *
 * Builder to generate models
 *
 * @package Phalcon\Builder
 */
class Model extends Component
{
    /**
     * Options container
     * @var ModelOption
     */
    protected $modelOptions;
    /**
     * Map of scalar data objects
     * @var array
     */
    private $typeMap = [
        //'Date' => 'Date',
        //'Decimal' => 'Decimal'
    ];

    /**
     * Create Builder object
     *
     * @param array $options
     * @throws InvalidArgumentException
     */
    public function __construct(array $options)
    {
        $this->modelOptions = new ModelOption($options);

        if (!$this->modelOptions->hasOption('name')) {
            throw new InvalidArgumentException('Please, specify the table name');
        }

        $this->modelOptions->setNotDefinedOption('camelize', false);
        $this->modelOptions->setNotDefinedOption('force', false);
        $this->modelOptions->setNotDefinedOption(
            'className',
            Utils::lowerCamelizeWithDelimiter($options['name'], '_-')
        );
        $this->modelOptions->setNotDefinedOption('fileName', Utils::lowerCamelizeWithDelimiter($options['name'], '_-'));
        $this->modelOptions->setNotDefinedOption('abstract', false);
        $this->modelOptions->setNotDefinedOption('annotate', false);
        if ($this->modelOptions->getOption('abstract')) {
            $this->modelOptions->setOption('className', 'Abstract' . $this->modelOptions->getOption('className'));
        }

        parent::__construct($options);
        $this->modelOptions->setOption('config', $this->modelOptions->getOption('config'));

        $this->modelOptions->setOption('snippet', new Snippet());
    }

    /**
     * @return ModelOption
     */
    public function getModelOptions()
    {
        return $this->modelOptions;
    }

    /**
     * We should expect schema to be string|null
     * OptionsAware throws when getting null option values
     * so we need to handle shouldInitSchema logic with the raw $option array
     *
     * Should setSchema in initialize() only if:
     * - $option['schema'] !== ''
     *
     * @return bool
     */
    public function shouldInitSchema()
    {
        return !isset($this->modelOptions->getOptions()['schema'])
            || $this->modelOptions->getOptions()['schema'] !== '';
    }

    /**
     * @return string
     */
    public function getSchema()
    {
        if ($this->modelOptions->hasOption('schema') && !empty($this->modelOptions->getOption('schema'))) {
            $schema = $this->modelOptions->getOption('schema');
        } else {
            $schema = Utils::resolveDbSchema($this->modelOptions->getOption('config')->database);
        }

        if (!empty($schema)) {
            return $schema;
        }

        throw new RuntimeException('Cannot find valid schema.  Set schema argument or set in config.');
    }

    /**
     * Module build
     *
     * @return mixed
     */
    public function build()
    {
        $config = $this->modelOptions->getOption('config');

        /* @var $snippet Snippet */
        $snippet = $this->modelOptions->getOption('snippet');
        $schema = $this->getSchema();

        if ($this->modelOptions->hasOption('directory')) {
            $this->path->setRootPath($this->modelOptions->getOption('directory'));
        }

        $methodRawCode = [];
        $this->setModelsDir();
        $this->setModelPath();

        // support custom db
        $customDb = $this->modelOptions->getOption('db');
        if ($customDb != null) {
            $config->database = $config->$customDb;
        }

        if (!isset($config->database)) {
            throw new BuilderException('Database configuration cannot be loaded from your config file.');
        }

        $modelPath = $this->modelOptions->getOption('modelPath');
        $directoryName = dirname($modelPath);
        // 自动创建目录，不用人工提前创建
        if(!file_exists($directoryName)) {
            mkdir($directoryName, 0755, true);
        }

        $this->checkDataBaseParam();

        if (isset($config->devtools->loader)) {
            /** @noinspection PhpIncludeInspection */
            require_once $config->devtools->loader;
        }

        $namespace = '';
        $serviceNamespace = '';
        if ($this->modelOptions->hasOption('namespace') &&
            $this->checkNamespace($this->modelOptions->getOption('namespace'))) {
            $namespace = 'namespace ' . $this->modelOptions->getOption('namespace') . ';' . PHP_EOL . PHP_EOL;

            // 生成 service namespace
            $_x = explode('\\', $this->modelOptions->getOption('namespace'));
            array_pop($_x);
            $_x[] = 'Services';
            $serviceNamespace = implode('\\', $_x);
        }

        $genDocMethods     = $this->modelOptions->getValidOptionOrDefault('genDocMethods', false);
        $useSettersGetters = $this->modelOptions->getValidOptionOrDefault('genSettersGetters', false);

        $adapter = $config->database->adapter;
        $this->isSupportedAdapter($adapter);

        $adapter = 'Mysql';
        if (isset($config->database->adapter)) {
            $adapter = $config->database->adapter;
        }

        if (is_object($config->database)) {
            $configArray = $config->database->toArray();
        } else {
            $configArray = $config->database;
        }

        // An array for use statements
        $uses = [];

        $adapterName = 'Phalcon\Db\Adapter\Pdo\\' . $adapter;
        unset($configArray['adapter']);
        /** @var Pdo $db */
        $db = new $adapterName($configArray);

        $initialize = [];

        if ($this->shouldInitSchema()) {
            $initialize['schema'] = $snippet->getThisMethod('setSchema', $schema);
        }

        $initialize['source'] = $snippet->getThisMethod('setSource', $this->modelOptions->getOption('name'));

        $table = $this->modelOptions->getOption('name');

        if (!$db->tableExists($table, $schema)) {
            throw new InvalidArgumentException(sprintf('Table "%s" does not exist.', $table));
        }

        // 包含注释信息的字段
        $fullFields = $db->fetchAll(sprintf("SHOW FULL COLUMNS FROM %s.%s", $schema, $table), Db::FETCH_ASSOC);
        $fullFieldsMap = [];
        foreach($fullFields as $v) {
            $fullFieldsMap[$v['Field']] = $v;
        }
        $fields = $db->describeColumns($table, $schema);
        $referenceList = $this->getReferenceList($schema, $db);

        foreach ($referenceList as $tableName => $references) {
            foreach ($references as $reference) {
                if ($reference->getReferencedTable() != $this->modelOptions->getOption('name')) {
                    continue;
                }

                $entityNamespace = '';
                if ($this->modelOptions->hasOption('namespace')) {
                    $entityNamespace = $this->modelOptions->getOption('namespace')."\\";
                }

                $refColumns   = $reference->getReferencedColumns();
                $columns      = $reference->getColumns();
                $initialize[] = $snippet->getRelation(
                    'hasMany',
                    $this->modelOptions->getOption('camelize') ? Utils::lowerCamelize($refColumns[0]) : $refColumns[0],
                    $entityNamespace . Text::camelize($tableName, '_-'),
                    $this->modelOptions->getOption('camelize') ? Utils::lowerCamelize($columns[0]) : $columns[0],
                    "['alias' => '" . Text::camelize($tableName, '_-') . "']"
                );
            }
        }

        foreach ($db->describeReferences($this->modelOptions->getOption('name'), $schema) as $reference) {
            $entityNamespace = '';
            if ($this->modelOptions->hasOption('namespace')) {
                $entityNamespace = $this->modelOptions->getOption('namespace')."\\";
            }

            $refColumns   = $reference->getReferencedColumns();
            $columns      = $reference->getColumns();
            $initialize[] = $snippet->getRelation(
                'belongsTo',
                $this->modelOptions->getOption('camelize') ? Utils::lowerCamelize($columns[0]) : $columns[0],
                $entityNamespace . Utils::camelize($reference->getReferencedTable()),
                $this->modelOptions->getOption('camelize') ? Utils::lowerCamelize($refColumns[0]) : $refColumns[0],
                "['alias' => '" . Text::camelize($reference->getReferencedTable(), '_-') . "']"
            );
        }

        $alreadyInitialized  = false;
        $alreadyValidations  = false;
        $alreadyFind         = false;
        $alreadyFindFirst    = false;
        $alreadyColumnMapped = false;
        $alreadyGetSourced   = false;
        $attributes          = [];

        $fullClassName = $this->modelOptions->getOption('className');
        if ($this->modelOptions->hasOption('namespace')) {
            $fullClassName = $this->modelOptions->getOption('namespace').'\\'.$fullClassName;
        }

        if (file_exists($modelPath)) {
            try {
                $possibleMethods = [];
                if ($useSettersGetters) {
                    foreach ($fields as $field) {
                        /** @var \Phalcon\Db\Column $field */
                        $methodName = Text::camelize($field->getName(), '_-');

                        $possibleMethods['set' . $methodName] = true;
                        $possibleMethods['get' . $methodName] = true;
                    }
                }

                $possibleMethods['getSource'] = true;

                /** @noinspection PhpIncludeInspection */
                require_once $modelPath;

                $linesCode     = file($modelPath);
                // $fullClassName = $this->modelOptions->getOption('className');
                // if ($this->modelOptions->hasOption('namespace')) {
                //     $fullClassName = $this->modelOptions->getOption('namespace').'\\'.$fullClassName;
                // }
                /* @var $reflection \ReflectionClass */
                $reflection = new \ReflectionClass($fullClassName);
                $hasMethodNames = [];
                foreach ($reflection->getMethods() as $method) {
                    if ($method->getDeclaringClass()->getName() != $fullClassName) {
                        continue;
                    }

                    $methodName = $method->getName();
                    $hasMethodNames[] = $methodName;
                    if (isset($possibleMethods[$methodName])) {
                        continue;
                    }

                    $indent = PHP_EOL;
                    if ($method->getDocComment()) {
                        $firstLine = $linesCode[$method->getStartLine() - 1];
                        preg_match('#^\s+#', $firstLine, $matches);
                        if (isset($matches[0])) {
                            $indent .= $matches[0];
                        }
                    }

                    $methodDeclaration = join(
                        '',
                        array_slice(
                            $linesCode,
                            $method->getStartLine() - 1,
                            $method->getEndLine() - $method->getStartLine() + 1
                        )
                    );

                    $methodRawCode[$methodName] = $indent . $method->getDocComment() . PHP_EOL . $methodDeclaration;

                    switch ($methodName) {
                        case 'initialize':
                            $alreadyInitialized = true;
                            break;
                        case 'validation':
                            $alreadyValidations = true;
                            break;
                        case 'find':
                            $alreadyFind = true;
                            break;
                        case 'findFirst':
                            $alreadyFindFirst = true;
                            break;
                        case 'columnMap':
                            $alreadyColumnMapped = true;
                            break;
                        case 'getSource':
                            $alreadyGetSourced = true;
                            break;
                    }
                }

                // 检查方法是否存在
                $preparedMethods = ['add', 'removeRecordByID', 'updateRecordByID', 'removeByID', 'updateByID', 'getInfoById', 'patchInfo', 'getInfoByIDs', 'getList', 'getListBySQL'];
                $diffMethodNames = array_diff($preparedMethods, $hasMethodNames);
                if(count($diffMethodNames) > 0) {
                    // print Color::error(sprintf("%s缺少方法: %s", $fullClassName, implode(',', $diffMethodNames)));
                    print sprintf("%s是老的Model，考虑替换，缺少方法:%s\n", $fullClassName, implode(',', $diffMethodNames));
                }

                $possibleFields = [];
                $preparedFields = []; // 需要的字段
                foreach ($fields as $field) {
                    $possibleFields[$field->getName()] = true;
                    $preparedFields[] = $field->getName();
                }
                if (method_exists($reflection, 'getReflectionConstants')) {
                    foreach ($reflection->getReflectionConstants() as $constant) {
                        if ($constant->getDeclaringClass()->getName() != $fullClassName) {
                            continue;
                        }
                        $constantsPreg = '/^(\s*)const(\s+)'.$constant->getName().'([\s=;]+)/';
                        $endLine = $startLine = 0;
                        foreach ($linesCode as $line => $code) {
                            if (preg_match($constantsPreg, $code)) {
                                $startLine = $line;
                                break;
                            }
                        }
                        if (!empty($startLine)) {
                            $countLines = count($linesCode);
                            for ($i = $startLine; $i < $countLines; $i++) {
                                if (preg_match('/;(\s*)$/', $linesCode[$i])) {
                                    $endLine = $i;
                                    break;
                                }
                            }
                        }

                        if (!empty($startLine) && !empty($endLine)) {
                            $constantDeclaration = join(
                                '',
                                array_slice(
                                    $linesCode,
                                    $startLine,
                                    $endLine - $startLine + 1
                                )
                            );
                            $attributes[] = PHP_EOL . "    " . $constant->getDocComment() .
                                PHP_EOL . $constantDeclaration;
                        }
                    }
                }

                $hasFieldNames = [];
                foreach ($reflection->getProperties() as $propertie) {
                    $propertieName = $propertie->getName();
                    $hasFieldNames[] = $propertieName;

                    if ($propertie->getDeclaringClass()->getName() != $fullClassName ||
                        !empty($possibleFields[$propertieName])) {
                        continue;
                    }
                    $modifiersPreg = '';
                    switch ($propertie->getModifiers()) {
                        case \ReflectionProperty::IS_PUBLIC:
                            $modifiersPreg = '^(\s*)public(\s+)';
                            break;
                        case \ReflectionProperty::IS_PRIVATE:
                            $modifiersPreg = '^(\s*)private(\s+)';
                            break;
                        case \ReflectionProperty::IS_PROTECTED:
                            $modifiersPreg = '^(\s*)protected(\s+)';
                            break;
                        case \ReflectionProperty::IS_STATIC + \ReflectionProperty::IS_PUBLIC:
                            $modifiersPreg = '^(\s*)(public?)(\s+)static(\s+)';
                            break;
                        case \ReflectionProperty::IS_STATIC + \ReflectionProperty::IS_PROTECTED:
                            $modifiersPreg = '^(\s*)protected(\s+)static(\s+)';
                            break;
                        case \ReflectionProperty::IS_STATIC + \ReflectionProperty::IS_PRIVATE:
                            $modifiersPreg = '^(\s*)private(\s+)static(\s+)';
                            break;
                    }
                    $modifiersPreg = '/' . $modifiersPreg . '\$' . $propertieName . '([\s=;]+)/';
                    $endLine = $startLine = 0;
                    foreach ($linesCode as $line => $code) {
                        if (preg_match($modifiersPreg, $code)) {
                            $startLine = $line;
                            break;
                        }
                    }
                    if (!empty($startLine)) {
                        $countLines = count($linesCode);
                        for ($i = $startLine; $i < $countLines; $i++) {
                            if (preg_match('/;(\s*)$/', $linesCode[$i])) {
                                $endLine = $i;
                                break;
                            }
                        }
                    }
                    if (!empty($startLine) && !empty($endLine)) {
                        $propertieDeclaration = join(
                            '',
                            array_slice(
                                $linesCode,
                                $startLine,
                                $endLine - $startLine + 1
                            )
                        );
                        $attributes[] = PHP_EOL . "    " . $propertie->getDocComment() . PHP_EOL .
                            $propertieDeclaration;
                    }
                }

                $diffFieldNames = array_diff($preparedFields, $hasFieldNames);
                if(count($diffFieldNames) > 0) {
                    print Color::error(sprintf("%s缺少的字段: %s", $fullClassName, implode(',', $diffFieldNames)));
                }
                $diffFieldNames = array_diff($hasFieldNames, $preparedFields);
                // 去除默认的字段.
                $_diffExcluded = 'useDb,useTable,msg,instance,cacheKey,cacheParams,cacheLifetime,dependency,inParams,hydrationMode,cache,readConnection,writeConnection,scenario,validators,logger,staticLogger';
                $diffExcluded = explode(',', $_diffExcluded);
                foreach($diffFieldNames as $k=>$v) {
                    if(substr($v, 0, 1) === '_' || in_array($v, $diffExcluded)) {
                        unset($diffFieldNames[$k]);
                    }
                }
                if(count($diffFieldNames) > 0) {
                    print Color::error(sprintf("db里面已经删除，但是%s还存在的字段: %s", $fullClassName, implode(',', $diffFieldNames)));
                }

            } catch (\Exception $e) {
                throw new RuntimeException(
                    sprintf(
                        'Failed to create the model "%s". Error: %s',
                        $this->modelOptions->getOption('className'),
                        $e->getMessage()
                    )
                );
            }
        }

        $validations = [];
        foreach ($fields as $field) {
            /* @var $field \Phalcon\Db\Column */
            if ($field->getType() === Column::TYPE_CHAR) {
                if ($this->modelOptions->getOption('camelize')) {
                    $fieldName = Utils::lowerCamelize(Utils::camelize($field->getName(), '_-'));
                } else {
                    $fieldName = Utils::lowerCamelize(Utils::camelize($field->getName(), '-'));
                }
                $domain = [];
                if (preg_match('/\((.*)\)/', $field->getType(), $matches)) {
                    foreach (explode(',', $matches[1]) as $item) {
                        $domain[] = $item;
                    }
                }
                if (count($domain)) {
                    $varItems      = join(', ', $domain);
                    $validations[] = $snippet->getValidateInclusion($fieldName, $varItems);
                }
            }
            if ($field->getName() == 'email') {
                if ($this->modelOptions->getOption('camelize')) {
                    $fieldName = Utils::lowerCamelize(Utils::camelize($field->getName(), '_-'));
                } else {
                    $fieldName = Utils::lowerCamelize(Utils::camelize($field->getName(), '-'));
                }
                $validations[] = $snippet->getValidateEmail($fieldName);
                $uses[]        = $snippet->getUseAs(EmailValidator::class, 'EmailValidator');
            }
        }
        if (count($validations)) {
            $validations[] = $snippet->getValidationEnd();
        }

        // Check if there has been an extender class
        $extends = $this->modelOptions->getValidOptionOrDefault('extends', '\Phalcon\Mvc\Model');

        // Check if there have been any excluded fields
        $exclude = [];
        if ($this->modelOptions->hasOption('excludeFields')) {
            $keys = explode(',', $this->modelOptions->getOption('excludeFields'));
            if (count($keys) > 0) {
                foreach ($keys as $key) {
                    $exclude[trim($key)] = '';
                }
            }
        }

        $setters           = [];
        $getters           = [];
        $rules             = []; //customized rules for validation
        $rules['integer']  = [];
        $rules['required'] = [];
        $rules['length']   = [];

        foreach ($fields as $field) {
            /* @var $field \Phalcon\Db\Column */
            if (array_key_exists(strtolower($field->getName()), $exclude)) {
                continue;
            }
            $type         = $this->getPHPType($field->getType());
            $fieldName    = Utils::lowerCamelizeWithDelimiter($field->getName(), '-', true);
            $fieldName    = $this->modelOptions->getOption('camelize') ? Utils::lowerCamelize($fieldName) : $fieldName;
            $fieldComment = $fullFieldsMap[$field->getName()]['Comment'];
            $attributes[] = $snippet->getAttributes(
                $type,
                $useSettersGetters ? 'protected' : 'public',
                $field,
                $this->modelOptions->getOption('annotate'),
                $fieldName,
                $fieldComment
            );

            if ($useSettersGetters) {
                $methodName = Utils::camelize($field->getName(), '_-');
                $setters[]  = $snippet->getSetter($field->getName(), $fieldName, $type, $methodName);

                if (isset($this->typeMap[$type])) {
                    $getters[] = $snippet->getGetterMap($fieldName, $type, $methodName, $this->typeMap[$type]);
                } else {
                    $getters[] = $snippet->getGetter($fieldName, $type, $methodName);
                }
            }

            // added to customized rules
            if ($type == 'integer' && $field->isNotNull() && !$field->isPrimary()) {
                $rules['integer'][] = $fieldName;
            }
            if ($field->isNotNull() && !$field->isPrimary()) {
                $rules['required'][] = $fieldName;
            }

            if ($field->getSize()>0 && $field->isNotNull() && !$field->isNumeric()) {
                $rules['length'][$fieldName] = ['max'=>$field->getSize()];
            }
        }

        $validationsCode = '';
        if ($alreadyValidations == false && count($validations) > 0) {
            $validationsCode = $snippet->getValidationsMethod($validations);
            $uses[]          = $snippet->getUse(Validation::class);
        }

        $initCode = '';
        if ($alreadyInitialized == false && count($initialize) > 0) {
            // 不需要phalcon 默认的initialize
            // $initCode = $snippet->getInitialize($initialize);
        }

        $license = '';
        if (file_exists('license.txt')) {
            $license = trim(file_get_contents('license.txt')) . PHP_EOL . PHP_EOL;
        }

        $dbSourceCode = '';
        if (false == $alreadyGetSourced) {
            // $methodRawCode [] =    $snippet->getModelSource($this->modelOptions->getOption('name'));
            // echo "   $customDb \n\n";
            $dbSourceCode .= $snippet->getDatabaseSource($config->database->dbname);
            $dbSourceCode .= $snippet->getModelSource($this->modelOptions->getOption('name'));
        }

        $className          = $this->modelOptions->getOption('className');
        $serviceClassName   = $className.'Service';
        $dbStaticMethodCode = $snippet->getStaticModelMethod($className);
        $dbStaticMethodCode .= $snippet->getRules($rules);
        $dbStaticMethodCode .= $snippet->getDefaultSkeletonMethod();

        if (false == $alreadyFind) {
            // $methodRawCode [] =    $snippet->getModelFind(   $this->modelOptions->getOption('className'));
        }

        if (false == $alreadyFindFirst) {
            // $methodRawCode [] =    $snippet->getModelFindFirst(   $this->modelOptions->getOption('className'));
        }

        $content = $dbSourceCode;
        $content .= join('', $attributes);
        $content .= $dbStaticMethodCode;

        if ($useSettersGetters) {
            $content .= join('', $setters) . join('', $getters);
        }

        $content .= $validationsCode . $initCode;
        foreach ($methodRawCode as $methodCode) {
            $content .= $methodCode;
        }

        $classDoc = '';
        if ($genDocMethods) {
            $classDoc = $snippet->getClassDoc($this->modelOptions->getOption('className'), $namespace);
        }

        if ($this->modelOptions->hasOption('mapColumn') && $this->modelOptions->getOption('mapColumn')
            && false == $alreadyColumnMapped) {
            $content .= $snippet->getColumnMap($fields, $this->modelOptions->getOption('camelize'));
        }

        $useDefinition = '';
        if (!empty($uses)) {
            usort($uses, function ($a, $b) {
                return strlen($a) - strlen($b);
            });

            $useDefinition = join("\n", $uses) . PHP_EOL . PHP_EOL;
        }

        $abstract = ($this->modelOptions->getOption('abstract') ? 'abstract ' : '');

        $code = $snippet->getClass(
            $namespace,
            $useDefinition,
            $classDoc,
            $abstract,
            $this->modelOptions,
            $extends,
            $content,
            $license
        );

        // !$this->modelOptions->getOption('force')
        if (file_exists($modelPath)) {
            print sprintf("file exist: %s\n", $fullClassName);
            // throw new WriteFileException(sprintf('file exist', $modelPath));
        } else {
            if (file_exists($modelPath) && !is_writable($modelPath)) {
                throw new WriteFileException(sprintf('Unable to write to %s. Check write-access of a file.', $modelPath));
            }

            if (!file_put_contents($modelPath, $code)) {
                throw new WriteFileException(sprintf('Unable to write to %s', $modelPath));
            } else {
                print sprintf("Model Created: %s\n", $fullClassName);
            }
        }

        // 写入service
        $serviceUseCode = $this->modelOptions->getOption('namespace').'\\'.$className;
        $serviceCode = $snippet->getServiceSkeleton($serviceNamespace, $serviceUseCode, $serviceClassName, $className);
        $fullServiceClassName = $serviceNamespace."\\".$serviceClassName;

        $servicePath = dirname(dirname($modelPath)).DIRECTORY_SEPARATOR.'Services'.DIRECTORY_SEPARATOR.$serviceClassName.'.php';
        $servicePathName = dirname($servicePath);
        // 自动创建servicePathName
        if(!file_exists($servicePathName)) {
            mkdir($servicePathName, 0755, true);
        }
        if(file_exists($servicePath)) {
            print sprintf("file exist: %s\n", $fullServiceClassName);
        } else {
            if (!file_put_contents($servicePath, $serviceCode)) {
                throw new WriteFileException(sprintf('Unable to write to %s', $servicePath));
            }
            print sprintf("Service Created: %s\n", $fullServiceClassName);
        }

        // if ($this->isConsole()) {
        //     $msgSuccess = ($this->modelOptions->getOption('abstract') ? 'Abstract ' : '');
        //     $msgSuccess .= 'Model "%s" was successfully created.';
        //     $this->notifySuccess(sprintf($msgSuccess, Text::camelize($this->modelOptions->getOption('name'), '_-')));
        // }
    }

    /**
     * Set path to folder where models are
     *
     * @throw InvalidParameterException
     */
    protected function setModelsDir()
    {
        if ($this->modelOptions->hasOption('modelsDir')) {
            $this->modelOptions->setOption(
                'modelsDir',
                rtrim($this->modelOptions->getOption('modelsDir'), '/\\') . DIRECTORY_SEPARATOR
            );

            return;
        }

        if ($modelsDir = $this->modelOptions->getOption('config')->path('application.modelsDir')) {
            $this->modelOptions->setOption('modelsDir', rtrim($modelsDir, '/\\') . DIRECTORY_SEPARATOR);

            return;
        }

        throw new InvalidParameterException("Builder doesn't know where is the models directory.");
    }

    /**
     * Set path to model
     *
     * @throw WriteFileException
     */
    protected function setModelPath()
    {
        $modelPath = $this->modelOptions->getOption('modelsDir');

        if (false == $this->isAbsolutePath($modelPath)) {
            $modelPath = $this->path->getRootPath($modelPath);
        }

        $modelPath .= $this->modelOptions->getOption('className') . '.php';

        // if (file_exists($modelPath) && !$this->modelOptions->getOption('force')) {
        //     throw new WriteFileException(sprintf(
        //         'The model file "%s.php" already exists in models dir',
        //         $this->modelOptions->getOption('className')
        //     ));
        // }

        $this->modelOptions->setOption('modelPath', $modelPath);
    }

    /**
     * @throw InvalidParameterException
     */
    protected function checkDataBaseParam()
    {
        if (!isset($this->modelOptions->getOption('config')->database)) {
            throw new InvalidParameterException('Database configuration cannot be loaded from your config file.');
        }

        if (!isset($this->modelOptions->getOption('config')->database->adapter)) {
            throw new InvalidParameterException(
                "Adapter was not found in the config. " .
                "Please specify a config variable [database][adapter]"
            );
        }
    }

    /**
     * Get reference list from option
     *
     * @param string $schema
     * @param Pdo $db
     * @return array
     */
    protected function getReferenceList($schema, Pdo $db)
    {
        if ($this->modelOptions->hasOption('referenceList')) {
            return $this->modelOptions->getOption('referenceList');
        }

        $referenceList = [];
        foreach ($db->listTables($schema) as $name) {
            $referenceList [$name] = $db->describeReferences($name, $schema);
        }

        return $referenceList;
    }

    protected function getEntityClassName(ReferenceInterface $reference, $namespace)
    {
        $referencedTable = Utils::camelize($reference->getReferencedTable());
        $fqcn            = "{$namespace
            }\\{$referencedTable
            }";

        return $fqcn;
    }

    /**
     * Returns the associated PHP type
     *
     * @param  string $type
     * @return string
     */
    protected function getPHPType($type)
    {
        switch ($type) {
            case Column::TYPE_INTEGER:
            case Column::TYPE_BIGINTEGER:
                return 'integer';
                break;
            case Column::TYPE_DECIMAL:
            case Column::TYPE_FLOAT:
                return 'double';
                break;
            case Column::TYPE_DATE:
            case Column::TYPE_VARCHAR:
            case Column::TYPE_DATETIME:
            case Column::TYPE_CHAR:
            case Column::TYPE_TEXT:
                return 'string';
                break;
            default:
                return 'string';
                break;
        }
    }
}
