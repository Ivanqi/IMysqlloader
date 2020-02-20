<?php declare(strict_types=1);
use Swoft\Db\DB;
use DataBaseRuleTransform;
use InsertStatementExtension;

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

    private static $_instance;

    public function __construct(string $projectName, int $projectID)
    {
        $this->handleFuncRule = config('database_rule.' . $this->funcIndex);
        $this->dataBaseRuleTransform = DataBaseRuleTransform::getInstance();
        $this->projectName = $projectName;
        $this->projectID = $projectID;
        $this->agentConfig = config('agent_config_' . $this->projectID);
        self::$dbPoolName = config('project_config.db_pool_name');
    }

    public static function getInstance(string $projectName, int $projectID ):  \App\Common\DataBaseHandleFunc
    {
        if (!self::$_instance) {
            self::$_instance = new self($projectName, $projectID);
        }
        return self::$_instance;
    }

    public function insertData(string $bg, string $mode, string $tableName, array $data)
    {
        try {
            if (!isset($this->handleFuncRule[$bg])) throw new \Exception(__CLASS__ . ": 不存在对应的规则");

            if (!method_exists($this, $this->handleFuncRule[$bg][$this->callFunc])) throw new \Exception(__CLASS__. ": 不存在对应的方法");
            $this->bg = $bg;
            $this->saveMode = $mode;
            $this->tableName = $tableName;
     
            call_user_func_array([$this, $this->handleFuncRule[$bg]], [$data]);
        } catch(\Exception $e) {
            throw new \Exception($e->getMessage());
        }
       
    }

    private function adminHandlerFunc(array $data)
    {
        $tmp = [];
        $needCheckParameter = [];

        if (isset($this->handleFuncRule[$this->needParameter])) {
            $needCheckParameter = $this->handleFuncRule[$this->needParameter];
        }
        $error = false;
        $connector = '-';
        foreach ($data as $v) {
            if (empty($needCheckParameter)) break;
            if (!isset($v[$needCheckParameter[0]]) || !isset($v[$needCheckParameter[1]])) $error = true; break;
            $tmp[$v[$needCheckParameter[0]] . $connector . $v[$needCheckParameter[1]]][] = $v;
        }

        if ($error == true || empty($tmp)) {
            throw new \Exception("数据格式异常。请检查数据来源");
        }
        unset($data);
        DB::connection(self::$dbPoolName)->beginTransaction();
        $flag = true;
        try {
            foreach ($tmp as $checkKey => $val) {
                @list($check1Key, $check2Key) = explode($connector, $check1Key);
                if (!isset($this->agentConfig[$check1Key])) $error == true; break;
                $dbName = $this->dataBaseRuleTransform($this->bg, $this->projectName, $this->agentConfig[$check1Key], $check2Key);
                $sql = InsertStatementExtension::makeMultiInsertSql($val, $this->tableName, $this->saveMode);
                $ret = DB::db($dbName)->insert($sql);
                if ($ret == false) $flag = false; 
            }
            if ($flag) {
                DB::connection(self::$dbPoolName)->commit();
            } else {
                DB::connection(self::$dbPoolName)->rollBack();
                throw new \Exception(__CLASS__. ":" . __FUNCTION__ . ", 数据插入失败");
            }
            unset($tmp);
        } catch(\Exception $e){
            DB::connection(self::$dbPoolName)->rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    private function commonHandlerFunc(array $data)
    {
        DB::connection(self::$dbPoolName)->beginTransaction();
        try {
            $dbName = $this->dataBaseRuleTransform($this->bg, $projectName);
            $sql = InsertStatementExtension::makeMultiInsertSql($data, $this->tableName, $this->saveMode);
            $ret = DB::db($dbName)->insert($sql);
            if ($ret) {
                DB::connection(self::$dbPoolName)->commit();
            } else {
                throw new \Exception(__CLASS__. ":" . __FUNCTION__ . ", 数据插入失败");
            }
            unset($data);
        } catch(\Exception $e) {
            DB::connection(self::$dbPoolName)->rollBack();
        }
    }
}