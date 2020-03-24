<?php declare(strict_types=1);
namespace App\Common;
use Swoft\Db\DB;
use App\Exception\DataBaseHandleFuncException;

class DataBaseHandleFunc
{
    private $funcIndex = 'handlerFunc';
    private $callFunc = 'func';
    private $handleFuncRule = [];
    private $saveMode = '';
    private $needParameter = 'need_parameter';
    private $dataBaseRuleTransform = NULL;
    private $projectName = '';
    private $projectID = '';
    private $agentConfig = [];
    private $bg;
    private $tableName = '';
    private $dbPoolName = '';
    private $insertStatementExtensionIns;

    private static $_instance;

    public function __construct(string $projectName, int $projectID)
    {
        $this->handleFuncRule = config('database_rule.' . $this->funcIndex);
        $this->dataBaseRuleTransform = DataBaseRuleTransform::getInstance();
        $this->projectName = $projectName;
        $this->projectID = $projectID;
        $this->agentConfig = config('agent_config_' . $this->projectID);
        $this->dbPoolName = config('project_config.db_pool_name');
        $this->insertStatementExtensionIns = InsertStatementExtension::getInstance();
    }

    public static function getInstance(string $projectName, int $projectID )
    {
        if (!self::$_instance) {
            self::$_instance = new self($projectName, $projectID);
        }
        return self::$_instance;
    }

    public function insertData(string $bg, string $mode, string $tableName, array $data)
    {
        try {
            if (!isset($this->handleFuncRule[$bg])) throw new DataBaseHandleFuncException(__CLASS__ . ":" . DataBaseHandleFuncException::NO_RULE);

            if (!method_exists($this, $this->handleFuncRule[$bg][$this->callFunc])) throw new DataBaseHandleFuncException(__CLASS__. ": " . DataBaseHandleFuncException::NO_METHOD);
            $this->bg = $bg;
            $this->saveMode = $mode;
            $this->tableName = $tableName;
            $func = $this->handleFuncRule[$bg][$this->callFunc];
            \call_user_func_array([$this, $func], [$data]);
            return true;
        } catch(\Exception $e) {
            throw new DataBaseHandleFuncException($e->getMessage());
        }
    }

    public function adminHandlerFunc($data)
    {
        $tmp = [];
        $needCheckParameter = [];

        if (isset($this->handleFuncRule[$this->bg][$this->needParameter])) {
            $needCheckParameter = $this->handleFuncRule[$this->bg][$this->needParameter];
        }

        if (empty($needCheckParameter)) {
            throw new DataBaseHandleFuncException(DataBaseHandleFuncException::DATA_FORMAT_EXCEPTION);
        }

        $error = false;
        $connector = '-';
        foreach ($data as $v) {
            if (!isset($v[$needCheckParameter[0]]) || !isset($v[$needCheckParameter[1]])) {
                $error = true; 
                break;
            }
            $key = $v[$needCheckParameter[0]] . $connector . $v[$needCheckParameter[1]];
            $tmp[$key][] = $v;
        }
        if ($error == true || empty($tmp)) {
            throw new DataBaseHandleFuncException(DataBaseHandleFuncException::DATA_FORMAT_EXCEPTION);
        }
        unset($data);
        DB::connection($this->dbPoolName)->beginTransaction();
        $flag = true;
        try {
            foreach ($tmp as $checkKey => $val) {
                @list($check1Key, $check2Key) = explode($connector, $checkKey);
                if (!isset($this->agentConfig[$check1Key])) {
                    $error = true; 
                    break;
                }
                $dbName = $this->dataBaseRuleTransform->getDBName($this->bg, $this->projectName, $this->agentConfig[$check1Key], $check2Key);
                $sql = $this->insertStatementExtensionIns->makeMultiInsertSql($val, $this->tableName, $this->saveMode);
                $ret = DB::db($dbName)->insert($sql);
                if ($ret == false) $flag = false; 
            }
            unset($tmp);
            if ($flag) {
                DB::connection($this->dbPoolName)->commit();
            } else {
                DB::connection($this->dbPoolName)->rollBack();
                throw new DataBaseHandleFuncException(__CLASS__. ":" . __FUNCTION__ . ", " . DataBaseHandleFuncException::DATA_WRITE_FAILED);
            }
        } catch(\Exception $e){
            unset($tmp);
            DB::connection($this->dbPoolName)->rollBack();
            throw new DataBaseHandleFuncException($e->getMessage());
        }
    }

    public function commonHandlerFunc($data)
    {
        DB::connection($this->dbPoolName)->beginTransaction();
        try {
            $dbName = $this->dataBaseRuleTransform->getDBName($this->bg, $this->projectName);
            $sql = $this->insertStatementExtensionIns->makeMultiInsertSql($data, $this->tableName, $this->saveMode);
            $ret = DB::db($dbName)->insert($sql);
            if ($ret) {
                DB::connection($this->dbPoolName)->commit();
            } else {
                throw new DataBaseHandleFuncException(__CLASS__. ":" . __FUNCTION__ . ", " . DataBaseHandleFuncException::DATA_WRITE_FAILED);
            }
            unset($data);
        } catch(\Exception $e) {
            DB::connection($this->dbPoolName)->rollBack();
            throw new DataBaseHandleFuncException($e->getMessage());
        }
    }
}