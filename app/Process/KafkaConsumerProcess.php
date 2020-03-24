<?php declare(strict_types=1);

namespace App\Process;

use Swoft\Log\Helper\CLog;
use Swoft\Process\Annotation\Mapping\Process;
use Swoft\Process\Contract\ProcessInterface;
use Swoole\Coroutine;
use Swoole\Process\Pool;
use Swoft\Redis\Redis;
use App\ProcessRepositories\SystemMonitorProcessRepositories;
use App\ProcessRepositories\kafkaConsumerRepositories;

/**
 * Class KafkaConsumerProcess
 *
 * @since 2.0
 *
 * @Process(workerId={0})
 */
class KafkaConsumerProcess implements ProcessInterface
{
    private static $runProject;
    private static $consumerConf;
    private static $kafkakafkaProducer;
    private static $topicNames;
    private static $consumer;
    private static $systemMonitorCode;

    public function __construct()
    {
        self::$runProject = (int) config('project_config.project_id');
        self::$systemMonitorCode = SystemMonitorProcessRepositories::SUCCESS_CODE;
    }

    private function registerSignal()
    {
        \Swoole\Process::signal(SIGUSR1, function ($signo) {
            self::$systemMonitorCode = SystemMonitorProcessRepositories::ERROR_CODE;
        });

        \Swoole\Process::signal(SIGUSR2, function ($signo) {
            self::$systemMonitorCode = SystemMonitorProcessRepositories::SUCCESS_CODE;
        });
    }

    /**
     * @param Pool $pool
     * @param int  $workerId
     */
    public function run(Pool $pool, int $workerId): void
    { 
        // $start_time = microtime(true); 
        $this->registerSignal();

        $kafkakafkaProducer = kafkaConsumerRepositories::getInstance();
        $consumerConf = $kafkakafkaProducer->kafkaConsumerConf();
        $topicNames = $kafkakafkaProducer->getTopicName();
        $consumer = new \RdKafka\KafkaConsumer($consumerConf);
        $consumer->subscribe($topicNames);



        while (self::$runProject > 0) {
            if (self::$systemMonitorCode == SystemMonitorProcessRepositories::SUCCESS_CODE) {
                $message = $consumer->consume($kafkakafkaProducer->getConsumerTime());
                switch ($message->err) {
                    case RD_KAFKA_RESP_ERR_NO_ERROR:
                        $kafkakafkaProducer->handleConsumerMessage($message);
                        // $consumer->commit($message);
                        break;
                    case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        // CLog::error('No more message; will wait for more');
                        // $end_time = microtime(true); 
                        // $execution_time = ($end_time - $start_time); 
                        // CLog::error(" 脚本执行时间 = ".$execution_time." 秒");
                        // Coroutine::sleep(0.1);
                        break;
                    case RD_KAFKA_RESP_ERR__TIMED_OUT:
                        // CLog::error('Timed out:'. $workerId);
                        Coroutine::sleep(0.1);
                        break;
                    default:
                        throw new \Exception($message->errstr(), $message->err);
                        break;
                }
            } else {
                CLog::error('System Overload');
                Coroutine::sleep(0.1);
            }            
        }

        // $conf = new \RdKafka\Conf();

        // // Set a rebalance callback to log partition assignments (optional)
        // $conf->setRebalanceCb(function(\RdKafka\KafkaConsumer $kafka, $err, array $partitions = NULL){
        //     switch ($err) {
        //         case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
        //             echo "Assign: ";
        //             var_dump($partitions);
        //             $kafka->assign($partitions);
        //             break;
        //         case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
        //             echo "Revoke: ";
        //             var_dump($partitions);
        //             $kafka->assign(NULL);
        //             break;
        //         default:
        //             throw new \Exception($err);
        //     }
        // });
        
        // Configure the group.id. All consumer with the same group.id will come
        // different partitions
        // $conf->set('group.id', 'IMysqlLoaderGroup23');
        
        // Initial list of Kafka brokers
        // $conf->set('metadata.broker.list', '192.168.1.131:9092');
        
        
        // Set where to start consuming messages when there is no initial offset in offset store or the desired offest is out of range.
        // 'smallest': start from the beginning
        // $conf->set('auto.offset.reset', 'smallest');
        
        // $consumer = new \RdKafka\KafkaConsumer($consumerConf);
        
        // Subscribe to topic 'test'
        // $consumer->subscribe($topicNames);
        
        // echo "Waiting for partiton assignment ... (make take some time when \n";
        // echo "quickly re-joining the group after leaving it .)\n";
        
        // while (true) {
        //     $message = $consumer->consume(120 * 10000);
        //     switch ($message->err) {
        //         case RD_KAFKA_RESP_ERR_NO_ERROR:
        //             var_dump($message);
        //             break;
        //         case RD_KAFKA_RESP_ERR__PARTITION_EOF:
        //             echo "No more message; will wait for more\n";
        //             break;
        //         case RD_KAFKA_RESP_ERR__TIMED_OUT:
        //             echo "Timed out\n";
        //             break;
        //         default:
        //             throw new \Exception($message->errstr(), $message->err);
        //     }
        // }
    }
}