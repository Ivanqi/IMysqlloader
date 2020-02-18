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

    private static $_instance;

    public function __construct(string $projectName, int $projectID)
    {
        $this->handleFuncRule = config('database_rule.' . $this->funcIndex);
        $this->dataBaseRuleTransform = DataBaseRuleTransform::getInstance();
        $this->projectName = $projectName;
        $this->projectID = $projectID;
        $this->agentConfig = config('agent_config_' . $this->projectID);
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
       if (!isset($this->handleFuncRule[$bg])) throw new \Exception(__CLASS__ . ": 不存在对应的规则");

       if (!method_exists($this, $this->handleFuncRule[$bg][$this->callFunc])) throw new \Exception(__CLASS__. ": 不存在对应的方法");
       $this->bg = $bg;
       $this->saveMode = $mode;
       $this->tableName = $tableName;
     
       call_user_func_array([$this, $this->handleFuncRule[$bg]], [$data]);
    }

    private function adminHandlerFunc(array $data)
    {
        $tmp = [];
        $needCheckParameter = [];

        if (isset($this->handleFuncRule[$this->needParameter])) {
            $needCheckParameter = $this->handleFuncRule[$this->needParameter];
        }
        $error = false;
        foreach ($data as $v) {
            if (empty($needCheckParameter)) break;
            if (!isset($v[$needCheckParameter[0]]) || !isset($v[$needCheckParameter[1]])) $error = true; break;
            $tmp[$v[$needCheckParameter[0]]][$v[$needCheckParameter[1]]][] = $v;
        }

        if ($error == true || empty($tmp)) {
            throw new \Exception("数据格式异常。请检查数据来源");
        }

        foreach ($tmp as $check1Key => $check1Val) {
            if (!isset($this->agentConfig[$check1Key])) $error == true; break;
            foreach ($check1Val as $check2Key => $val) {
                $dbName = $this->dataBaseRuleTransform($this->bg, $this->projectName, $this->agentConfig[$check1Key], $check2Key);
                $sql = InsertStatementExtension::makeMultiInsertSql($val, $this->tableName, $this->saveMode);
                $ret = DB::db($dbName)->insert($sql);
                if (!$ret) {

                }
            }
        }
        unset($tmp);
        unset($data);
    }

    private function commonHandlerFunc(array $data)
    {
        $dbName = $this->dataBaseRuleTransform($this->bg, $projectName);
        $sql = InsertStatementExtension::makeMultiInsertSql($data, $this->tableName, $this->saveMode);
        $ret = DB::db($dbName)->insert($sql);
        if (!$ret) {

        }

        unset($data);
    }
}