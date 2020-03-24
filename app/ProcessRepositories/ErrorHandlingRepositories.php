<?php declare(strict_types=1);
namespace App\ProcessRepositories;
use Swoft\Log\Helper\CLog;
use Swoft\Redis\Redis;
// use App\Common\DataBaseHandleFunc;

class ErrorHandlingRepositories
{
    private static $_instance;
    private static $projectID;
    private static $projectName;
    private static $queueName;
    private static $queueNameBy5min;
    private static $commonFailQueueName;
    
    private static $dbHandlerFuncInstance;
    private static $failQueueName;
    private static $failQueueTimer = 0;
    private static $failQueueTimerKeyBy5min;
    private static $timeStampBy5min = 300;
    public static $maxTimeout;
    private static $topicsCommonRepositories;
    private static $kafkaTopicFailJobTemp;

   

    public function __construct()
    {
        self::$topicsCommonRepositories = TopicsCommonRepositories::getInstance(config('fail_logjob.fail_queue_name'), config('fail_logjob.commo_queue_name'));
        
        self::$queueNameBy5min = config('fail_logjob.5min_fail_queue_name');
        self::$failQueueTimerKeyBy5min = config('queue_max_timeout.5min_fail_queue_timer');
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

    private function setTimeStampBy5min(int $now): void
    {
        Redis::SETNX(self::$failQueueTimerKeyBy5min, $now + self::$timeStampBy5min);
        self::$failQueueTimer = $now + self::$timeStampBy5min;
    }

    public function handleFailMessage(): void
    {
        try {
            $keyArr = self::$topicsCommonRepositories->getTopicsKey();
            $topicName = $keyArr[TopicsCommonRepositories::TOPIC_NAME];
            $logData = Redis::BRPOPLPUSH($keyArr[TopicsCommonRepositories::KAFKA_TOPIC_JOB_KEY], $keyArr[TopicsCommonRepositories::KAFKA_TOPIC_FAILE_JOB_KEY], self::$maxTimeout);
            if (empty($logData)) return;

            $appLog = self::$topicsCommonRepositories;
            if (!isset($appLog[$topicName])) {
                throw new \Exception("APP LOG 配置中不存在对应的Record: ". $topicName);
            }

            $logDataDecrypt = unserialize(gzuncompress(unserialize($logData)));

            if (!$self::$topicsCommonRepositories->insertData($logDataDecrypt, $topicName, $appLog[$topicName])) {
                throw new \Exception("数据插入失败，对应的Record: ". $keyArr[$topicName]);
            }
            
            Redis::lrem($keyArr[TopicsCommonRepositories::KAFKA_TOPIC_FAILE_JOB_KEY], $logData);
            $topicfailJob = sprintf(self::$kafkaTopicFailJobTemp, self::$topicsCommonRepositories::$runProject, $topicName);
            Redis::lrem($topicfailJob, $logData);
        } catch (\Exception $e) {
            CLog::error($e->getMessage() . '(' . $e->getLine() .')');
        }   
    }

    public function handle5minFailMessage()
    {
        $len = Redis::LLEN(self::$queueNameBy5min);
        for ($i = 0; $i < $len; $i++) {
            $logData = Redis::BRPOPLPUSH(self::$queueNameBy5min, self::$commonFailQueueName, self::$maxTimeout);
            if ($logData) {
                $this->commonHandleMessage($logData);
                unset($logData);
            }
        }
    }

    private function commonFail(string $queueName1, strig $queueName2, string $data): void
    {
        Redis::LPUSH($queueName1, $data);
        Redis::LREM($queueName2, $data);
    }

    public function setEorrorMessage($topicName, $logData)
    {
        $key = sprintf(self::$failQueueName, self::$projectID, $topicName);
        Redis::LPUSH($key, $logData);
    }
}