<?php declare(strict_types=1);
namespace App\ProcessRepositories;
use Swoft\Log\Helper\CLog;
use Swoft\Redis\Redis;
// use App\Common\DataBaseHandleFunc;

class ErrorHandlingRepositories
{
    private static $_instance;
    private static $projectID;
    private static $queueName;
    private static $queueNameBy5min;
    
    private static $dbHandlerFuncInstance;
    private static $failQueueName;
    private static $failQueueTimer = 0;
    private static $failQueueTimerKeyBy5min;
    private static $timeStampBy5min = 300;
    public static $maxTimeout;
    private static $topicsCommonRepositories;
    private static $kafkaTopicFailJobTemp;
    private static $fiveMinTopicCommonRepositories;
   

    public function __construct()
    {
        self::$queueNameBy5min = config('fail_logjob.5min_fail_queue_name');
        $commoQueueName = config('fail_logjob.commo_queue_name');
        self::$topicsCommonRepositories = TopicsCommonRepositories::getInstance(config('fail_logjob.fail_queue_name'), $commoQueueName);
        self::$fiveMinTopicCommonRepositories = TopicsCommonRepositories::getInstance(self::$queueNameBy5min, $commoQueueName);
        
        self::$failQueueTimerKeyBy5min = config('fail_logjob.5min_fail_queue_timer');
        self::$maxTimeout = config('fail_logjob.queue_max_timeout');
        self::$failQueueName = config('fail_logjob.fail_queue_name');
        self::$kafkaTopicFailJobTemp = config('kafka_config.kafka_topic_fail_job');
    }

    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function check5minFailQueue(): bool
    {
        $now = time();
        if (!Redis::EXISTS(self::$failQueueTimerKeyBy5min)){
            $this->setTimeStampBy5min($now);
            return false;
        }

        if (!self::$failQueueTimer) {
            self::$failQueueTimer = Redis::GET(self::$failQueueTimerKeyBy5min);
            if (!self::$failQueueTimer) {
                $this->setTimeStampBy5min($now);
                return false;
            }
        }
        
        if ($now > self::$failQueueTimer) {
            $this->setTimeStampBy5min($now);
            return true;
        } else {
            return false;
        }
    }

    public function setTimeStampBy5min(int $now): void
    {
        Redis::SETNX(self::$failQueueTimerKeyBy5min, $now + self::$timeStampBy5min);
        self::$failQueueTimer = $now + self::$timeStampBy5min;
    }

    public function handleFailMessage(): void
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
            $topicfailJob = sprintf(self::$kafkaTopicFailJobTemp, self::$topicsCommonRepositories::$runProject, $topicName);
            Redis::lrem($topicfailJob, $logData);
        } catch (\Exception $e) {
            $this->setEorrorMessage($topicName, $logData, self::$queueNameBy5min);
            CLog::error($e->getMessage() . '(' . $e->getLine() .')');
        }
        unset($logData);
    }

    public function handle5minFailMessage()
    {
        try {
            for ($i = 0; $i < self::$fiveMinTopicCommonRepositories::$topicNums; $i++) {

                $keyArr = self::$fiveMinTopicCommonRepositories->getTopicsKey();
                $topicName = $keyArr[TopicsCommonRepositories::TOPIC_NAME];
                $falg = true;

                $len = Redis::LLEN($keyArr[TopicsCommonRepositories::KAFKA_TOPIC_JOB_KEY]);
                for ($i = 0; $i < $len; $i++) {
                    $logData = Redis::BRPOPLPUSH($keyArr[TopicsCommonRepositories::KAFKA_TOPIC_JOB_KEY], $keyArr[TopicsCommonRepositories::KAFKA_TOPIC_FAILE_JOB_KEY], self::$maxTimeout);
                    
                    if (empty($logData)) continue;
    
                    $appLog = self::$fiveMinTopicCommonRepositories::$appLog;
                    if (!isset($appLog[$topicName])) {
                        continue;
                        // throw new \Exception("APP LOG 配置中不存在对应的Record: ". $topicName);
                    }
    
                    $logDataDecrypt = unserialize(gzuncompress(unserialize($logData)));
                    if (!self::$fiveMinTopicCommonRepositories->insertData($logDataDecrypt, $topicName, $appLog[$topicName])) {
                        continue;
                        // throw new \Exception("数据插入失败，对应的Record: ". $keyArr[$topicName]);
                    }
                    unset($logDataDecrypt);
                    Redis::lrem($keyArr[TopicsCommonRepositories::KAFKA_TOPIC_FAILE_JOB_KEY], $logData);
                    unset($logData);
                }
            } 
        } catch (\Exception $e) {
            CLog::error($e->getMessage() . '(' . $e->getLine() .')');
        }
    }
    
    public function setEorrorMessage($topicName, $logData, $queueName = '')
    {
        if (empty($queueName)) {
            $queueName = self::$failQueueName;
        }
        $key = sprintf($queueName, self::$topicsCommonRepositories::$runProject, $topicName);
        Redis::LPUSH($key, $logData);
    }
}