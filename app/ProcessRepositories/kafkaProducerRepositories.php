<?php declare(strict_types=1);
namespace App\ProcessRepositories;

use Swoft\Log\Helper\CLog;

class kafkaProducerRepositories
{
    private static $kafkaProducerFailJob = '';
    private static $kafkaProducerPrefix = '';
    private static $kafkaProducerAddr;
    private static $producer;
    private static $producerConf;
    private static $_instance;
    private static $runProject;
    private static $topicRule = '';
    private static $producerTopic = [];

    public function __construct()
    {
        self::$kafkaProducerAddr = config('kafka_config.kafka_producer_addr');
        self::$producerConf = $this->kafkaProducerConf();
        self::$runProject = (int) config('project_config.project_id');
        self::$topicRule = config('kafka_config.kafka_topic_rule');
        self::$kafkaProducerPrefix = config('kafka_config.kafka_producer_topic_prefix');
    }

    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function kafkaProducerConf(): \RdKafka\Conf
    {
        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', self::$kafkaProducerAddr);
        $conf->set('socket.keepalive.enable', 'true');
        $conf->set('log.connection.close', 'true');
        return $conf;
    }

    public function kafkaProducer($recordName, string $data): bool
    {
        if (self::$producer == NULL) {
            self::$producer = new \RdKafka\Producer(self::$producerConf);
        }

        if (empty($data)) return false;

        $topicName = sprintf(self::$topicRule, self::$kafkaProducerPrefix, self::$runProject, $recordName);

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
}