<?php declare(strict_types=1);
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://swoft.org/docs
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */

namespace App\Process;

use Swoft\Log\Helper\CLog;
use Swoft\Process\Annotation\Mapping\Process;
use Swoft\Process\Contract\ProcessInterface;
use Swoole\Coroutine;
use Swoole\Process\Pool;
use Swoft\Redis\Redis;
use App\Common\SystemUsage;
use App\Common\DataBaseHandleFunc;

/**
 * Class LogProcess
 *
 * @since 2.0
 *
 * @Process(workerId={0,1})
 */
class LogProcess implements ProcessInterface
{
    private static $projectID;
    private static $projectName;
    private static $kafkaConsumerAddr;
    private static $groupId;
    private static $consumerConf;
    private static $consumer;
    private static $topicRule = '';
    private static $topicNames = [];
    private static $consumerTime = 0;
    private static $kafkaConsumerFailJob = '';
    private static $kafkaConsumerPrefix = '';
    private static $appLog;
    private static $dbHandleFuncInstance;

    public function __construct()
    {
        self::$projectID = (int) config('project_config.project_id');
        self::$projectName = config('project_config.project_name');
        self::$kafkaConsumerAddr = config('kafka_config.kafka_consmer_addr');
        self::$groupId = config('kafka_config.kafka_consumer_group');
        self::$appLog = config('app_log_' . self::$projectID);
       

        self::$consumerTime = config('kafka_config.kafka_consumer_time');
        self::$kafkaConsumerFailJob = config('kafka_config.kafka_consumer_fail_job');
        self::$kafkaConsumerPrefix = config('kafka_config.kafka_consumer_topic_prefix');

        self::$consumerConf = self::kafkaConsumerConf();
        
        self::$topicNames = self::getTopicName(self::$projectID, array_keys(self::$appLog), self::$kafkaConsumerPrefix);
        self::$dbHandleFuncInstance = DataBaseHandleFunc::getInstance(self::$projectName, self::$projectID);
    }

    private static function kafkaConsumerConf(): \RdKafka\Conf
    {
        $conf = new \RdKafka\Conf();

        // Set a rebalance callback to log partition assignments (optional)
        $conf->setRebalanceCb(__CLASS__ . '::setRebalanceCb');

        // Configure the group.id. All consumer with the same group.id will come
        // different partitions
        $conf->set('group.id', self::$groupId);
         
        // Set where to start consuming messages when there is no initial offset in offset store or the desired offest is out of range.
        // 'smallest': start from the beginning
        $conf->set('auto.offset.reset', 'smallest');
 
        // Initial list of Kafka brokers
        $conf->set('metadata.broker.list', self::$kafkaConsumerAddr);
        return $conf;
    }


    private static function getTopicName(int $runProject, array $topicList, string $kafkaPrefix) : array
    {
        $topicNameList = [];
        foreach ($topicList as $topic) {
            $topicName = sprintf(self::$topicRule, $kafkaPrefix, $runProject, $topic);
            $topicNameList[] = $topicName;
        }
        return $topicNameList;
    }

    public static function setErrorCb($producer, $err, $reason)
    {
        CLog::error(rd_kafka_err2str($err) . ':' . $reason);
    }

    public static function setRebalanceCb(\RdKafka\KafkaConsumer $kafka, $err, array $partitions = NULL): void
    {
        switch ($err) {
            case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                CLog::info("Assign:" . json_encode($partitions));
                $kafka->assign($partitions);
                break;
            case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                CLog::info("Revoke:" . json_encode($partitions));
                $kafka->assign(NULL);
                break;
            default:
                throw new \Exception($err);
        }
    }

    /**
     * @param Pool $pool
     * @param int  $workerId
     */
    public function run(Pool $pool, int $workerId): void
    { 
        if (self::$consumer == NULL) {
            self::$consumer = new \RdKafka\KafkaConsumer(self::$consumerConf);
        }
        self::$consumer->subscribe(self::$topicNames);

        while (self::$runProject > 0) {
            $syData = SystemUsage::getCpuWithMem();
            if ($syData['cpu_idle_rate'] > SystemUsage::$defaultMinCpuIdleRate && $syData['mem_usage'] < SystemUsage::$defaultMaxMemUsage) {
                self::kafkaConsumer(self::$consumer);
            }
            Coroutine::sleep(1);
        }
    }

    private static function handleConsumerMessage(\RdKafka\Message $message): void
    {
        try {
            $topicName = $message->topic_name;
            $recordName = substr($topicName, strlen(self::$kafkaConsumerPrefix . self::$projectID . '_') + 1);
            if (!isset(self::$appLog[$recordName])) {
                // 放入REDIS中
                throw new \Exception("APP LOG 配置中不存在对应的Record: ". $recordName);
            }
            if ($message->payload) {
                $payload = json_decode($message->payload, true);

                self::insertData($payload, $recordName);
                unset($payload);
            }
        } catch (\Exception $e) {
            CLog::error($e->getMessage() . '(' . $e->getLine() .')');
        }
    }

    private static function insertData(array $payload, $recordConfig): void
    {
        $backgrouds = $recordConfig['backgrouds'];
        $tableName = $recordConfig['table'];
        $saveMode = $recordConfig['save_mode'];
        try {
            foreach($backgrouds as $bg) {
                self::$dbHandleFuncInstance($bg, $saveMode, $tableName, $payload);
            }
        } catch(\Exception $e) {
        }
    }


    private static function kafkaProducer($recordName, string $data): bool
    {
        if (self::$producer == NULL) {
            self::$producer = new \RdKafka\Producer(self::$producerConf);
        }

        if (empty($data)) return false;

        $topicName = sprintf(self::$topicRule, self::$kafkaProducerPrefix, self::$projectID, $recordName);

        if (!isset(self::$producerTopic[$topicName])) {
            self::$producerTopic[$topicName] = self::$producer->newTopic($topicName);
        } else {
            if (self::$producerTopic[$topicName] == NULL) {
                self::$producerTopic[$topicName] = self::$producer->newTopic($topicName);
            }
        }

        if (!self::$producer->getMetadata(false, self::$producerTopic[$topicName], 2 * 1000)) {
            CLog::error('Failed to get metadata, is broker down?');
        }

        self::$producerTopic[$topicName]->produce(RD_KAFKA_PARTITION_UA, 0, $data);
        self::$producer->poll(0);

        while ((self::$producer->getOutQLen())) {
            self::$producer->poll(20);
        }

       return self::$producer->getOutQLen() > 0 ? false : true;
    }

    private static function kafkaConsumer(\RdKafka\KafkaConsumer $consumer): void
    {
        $message = $consumer->consume(self::$consumerTime);
        switch ($message->err) {
            case RD_KAFKA_RESP_ERR_NO_ERROR:
                self::handleConsumerMessage($message);
                break;
            case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                CLog::error('No more message; will wait for more');
                break;
            case RD_KAFKA_RESP_ERR__TIMED_OUT:
                CLog::error('Timed out');
                break;
            default:
                throw new \Exception($message->errstr(), $message->err);
        }
    }
}