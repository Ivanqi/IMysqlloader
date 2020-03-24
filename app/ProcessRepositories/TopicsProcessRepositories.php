<?php declare(strict_types=1);
namespace App\ProcessRepositories;
use Swoft\Log\Helper\CLog;
use Swoft\Redis\Redis;

class TopicsProcessRepositories
{
    private static $_instance;
    private static $maxTimeout;
    private static $tablePrefix;
    private static $appLog;
   
    private static $topicsCommonRepositories;

    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        self::$topicsCommonRepositories = TopicsCommonRepositories::getInstance(config('kafka_config.kafka_topic_job'), config('kafka_config.kafka_topic_fail_job'));
        self::$maxTimeout = config('kafka_config.queue_max_timeout');
    }

    public function topicHandler()
    {
        $keyArr = self::$topicsCommonRepositories->getTopicsKey();
        $topicName = $keyArr[TopicsCommonRepositories::TOPIC_NAME];
        $logData = Redis::BRPOPLPUSH($keyArr[TopicsCommonRepositories::KAFKA_TOPIC_JOB_KEY], $keyArr[TopicsCommonRepositories::KAFKA_TOPIC_FAILE_JOB_KEY], self::$maxTimeout);
        try {
            if (empty($logData)) return;
            $appLog = self::$topicsCommonRepositories::$appLog;
            if (!isset($appLog[$topicName])) {
                throw new \Exception("APP LOG 配置中不存在对应的Record: ". $topicName);
            }
            $logDataDecrypt = unserialize(gzuncompress(unserialize($logData)));
            if (!self::$topicsCommonRepositories->insertData($logDataDecrypt, $topicName, $appLog[$topicName])) {
                throw new \Exception("数据插入失败，对应的Record: ". $keyArr[$topicName]);
            }
            unset($logDataDecrypt);
            Redis::lrem($keyArr[TopicsCommonRepositories::KAFKA_TOPIC_FAILE_JOB_KEY], $logData);
        } catch (\Exception $e) {
            ErrorHandlingRepositories::getInstance()->setEorrorMessage($topicName, $logData);
            CLog::error($e->getMessage() . '(' . $e->getFile() . ':' . $e->getLine() .')');
        }
        unset($logData);
    }
}