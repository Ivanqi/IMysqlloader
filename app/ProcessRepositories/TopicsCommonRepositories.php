<?php declare(strict_types=1);
namespace App\ProcessRepositories;
use App\Common\DataBaseHandleFunc;
use App\Exception\DataBaseHandleFuncException;

class TopicsCommonRepositories
{
    const KAFKA_TOPIC_JOB_KEY = 'kafka_topic_job_key';
    const KAFKA_TOPIC_FAILE_JOB_KEY = 'kafka_top_job_key';
    const TOPIC_NAME = 'topic_name';
    public static $appLog;
    private static $keyIndex = 0;
    private static $kafkaTopicJobTemp = '';
    private static $kafkaTopicFailJobTemp = '';
    private static $_instance;
    public static $runProject;
    private static $topicList;
    public static $topicNums;
    private static $projectType;
    private static $dataMaxChunkLimit = 0;
    private static $dbHandleFuncInstance;


    public static function getInstance($kafkaTopicJobTemp, $kafkaTopicFailJobTemp)
    {
        if (!self::$_instance) {
            self::$_instance = new self($kafkaTopicJobTemp, $kafkaTopicFailJobTemp);
        }
        return self::$_instance;
    }

    public function __construct($kafkaTopicJobTemp, $kafkaTopicFailJobTemp)
    {
        self::$runProject = (int) config('project_config.project_id');
        self::$appLog = config('app_log_' . self::$runProject);
        self::$topicList = array_keys(self::$appLog);
        self::$topicNums = count(self::$topicList);
        self::$kafkaTopicJobTemp = $kafkaTopicJobTemp;
        self::$kafkaTopicFailJobTemp = $kafkaTopicFailJobTemp;
        self::$dataMaxChunkLimit = (int) config('project_config.data_max_chunk_limit');
        $projectType = config('project_config.project_type');
        $projectType = explode(config('project_config.project_type_delimiter'), $projectType);
        self::$projectType = array_flip($projectType);
        $projectName = config('project_config.project_name');       
        self::$dbHandleFuncInstance = DataBaseHandleFunc::getInstance($projectName, self::$runProject);

    }

    private function getRRNum()
    {
        $i = self::$keyIndex;
        do {
            if (isset(self::$topicList[$i])) break;
            $i = ($i + 1) % self::$topicNums;
        } while ($i != self::$keyIndex);

        self::$keyIndex = ($i + 1) % self::$topicNums;
        return $i;
    }

    public function getTopicsKey()
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

    public function insertData(array $payload, string $recordName, $recordConfig): bool
    {
        $payloadNum = count($payload);
        if ($payloadNum > self::$dataMaxChunkLimit) {
            $payloadChunk = array_chunk($payload, self::$dataMaxChunkLimit);
            foreach($payloadChunk as $payload) {
                $ret = $this->insert($payload, $recordName, $recordConfig);
                if ($ret == false) return $ret;
            }
            return true;
        } else {
            return $this->insert($payload, $recordName, $recordConfig);
        }
    }

    private function insert(array $payload, string $recordName, $recordConfig) {
        $backgrouds = $recordConfig['backgrouds'];
        $tableName = $recordConfig['table'];
        $saveMode = $recordConfig['save_mode'];
        $flag = true;
        try {
            foreach($backgrouds as $bg) {
                if (!isset(self::$projectType[$bg])) continue;
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