<?php declare(strict_types=1);
namespace App\ProcessRepositories;
use Swoft\Log\Helper\CLog;
use Swoft\Redis\Redis;
use App\Common\DataBaseHandleFunc;
use App\Exception\DataBaseHandleFuncException;

class TopicsProcessRepositories
{
    private static $runProject;
    private static $_instance;
    private static $topicList;
    private static $topicNums;
    private static $maxTimeout;
    private static $maxTimes;
    private static $keyIndex = 0;
    private static $kafkaTopicJobTemp = '';
    private static $kafkaTopicFailJobTemp = '';
    private static $tablePrefix;
    private static $tableRuleConfig = [];
    private static $appLog;
    const KAFKA_TOPIC_JOB_KEY = 'kafka_topic_job_key';
    const KAFKA_TOPIC_FAILE_JOB_KEY = 'kafka_top_job_key';
    const TOPIC_NAME = 'topic_name';
    private static $dbHandleFuncInstance;


    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        self::$runProject = (int) config('project_config.project_id');
        // $tableConfig = config('table_config');
        // $projectTableConfig = $tableConfig[self::$runProject];
        self::$appLog = config('app_log_' . self::$runProject);

        self::$topicList = array_keys(self::$appLog);
        // self::$tablePrefix = $projectTableConfig['table_prefix'];
        self::$topicNums = count(self::$topicList);
        self::$kafkaTopicJobTemp = config('kafka_config.kafka_topic_job');
        self::$kafkaTopicFailJobTemp = config('kafka_config.kafka_topic_fail_job');
        self::$maxTimeout = config('kafka_config.queue_max_timeout');
        self::$tableRuleConfig = config('table_info_' . self::$runProject . '_rule')[self::$runProject];
        self::$maxTimes = config('kafka_config.queue_max_times');
        $projectName = config('project_config.project_name');
        self::$dbHandleFuncInstance = DataBaseHandleFunc::getInstance($projectName, self::$runProject);
    }

    private function getRRNum()
    {
        $i = self::$keyIndex;
        do {
            if (isset($topicList[$i])) break;
            $i = ($i + 1) % self::$topicNums;
        } while ($i != self::$keyIndex);

        self::$keyIndex = ($i + 1) % self::$topicNums;

        return $i;
    }

    private function getHandleKey()
    {
        $index = $this->getRRNum();
        if (isset(self::$topicList[$index])) {
            $topic = self::$topicList[$index];
        } else {
            $topic = self::$topicList[0];
        }
        
        return [
            self::KAFKA_TOPIC_JOB_KEY => sprintf(self::$kafkaTopicJobTemp, self::$runProject, $topic),
            self::KAFKA_TOPIC_FAILE_JOB_KEY => sprintf(self::$kafkaTopicFailJobTemp, self::$runProject, $topic),
            self::TOPIC_NAME => $topic
        ];
    }

    public function topicHandler()
    {
        try {
            $keyArr = $this->getHandleKey();
            $kafkaData = [];
            $logData = Redis::BRPOPLPUSH($keyArr[self::KAFKA_TOPIC_JOB_KEY], $keyArr[self::KAFKA_TOPIC_FAILE_JOB_KEY], self::$maxTimeout);
            if (empty($logData)) return;
            $topicName = $keyArr[self::TOPIC_NAME];
            if (!isset(self::$appLog[$topicName])) {
                throw new \Exception("APP LOG 配置中不存在对应的Record: ". $topicName);
            }

            $logDataDecrypt = unserialize(gzuncompress(unserialize($logData)));
            if (!$this->insertData($logDataDecrypt, $topicName, self::$appLog[$topicName])) {
                throw new \Exception("数据插入失败，对应的Record: ". $keyArr[$topicName]);
            }
            unset($logDataDecrypt);
            Redis::lrem($keyArr[self::KAFKA_TOPIC_FAILE_JOB_KEY], $logData);
            unset($logData);
        } catch (\Exception $e) {
            CLog::error($e->getMessage() . '(' . $e->getLine() .')');
        }
    }

    private function insertData(array $payload, string $recordName, $recordConfig): bool
    {
        $backgrouds = $recordConfig['backgrouds'];
        $tableName = $recordConfig['table'];
        $saveMode = $recordConfig['save_mode'];
        $flag = true;
        try {
            foreach($backgrouds as $bg) {
                $ret = self::$dbHandleFuncInstance->insertData($bg, $saveMode, $tableName, $payload);
                if ($ret == false) $flag = false;
            }
            return $flag;
        } catch(\DataBaseHandleFuncException $e) {
            CLog::error(__CLASS__ . ':' . $e->getMessage());
            return false;
        }
    }
}