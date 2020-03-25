<?php declare(strict_types=1);
namespace App\Repositories;
use Swoft\Redis\Redis;

class ShowTopicsCommandRepositories
{
    private static $_instance;
    private $projectID;
    private $topicList;
    private $queueList;

    const TOPICS_LIST = 'kafka_topic_job';
    const TOPICS_FAIL_LIST = 'kafka_topic_fail_job';
    const FAIL_TOPICS_LIST = 'fail_queue_name';
    const FIVEMIN_FAIL_TOPICS_LIST = '5min_fail_queue_name';
    const COMMON_FAIL_TOPICS_LIST = 'commo_queue_name';

    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        $this->projectID = config('project_config.project_id');
        $this->getQueueList();
        $this->getTopicsList();
    }

    private function getQueueList()
    {
        $this->queueList = [
            self::TOPICS_LIST => config('kafka_config.kafka_topic_job'),
            self::TOPICS_FAIL_LIST => config('kafka_config.kafka_topic_fail_job'),
            self::FAIL_TOPICS_LIST => config('kafka_config.fail_queue_name'),
            self::FIVEMIN_FAIL_TOPICS_LIST => config('fail_logjob.5min_fail_queue_name'),
            self::COMMON_FAIL_TOPICS_LIST => config('fail_logjob.commo_queue_name')
        ];
    }

    private function getTopicsList()
    {
        $appLog = config('app_log_' . $this->projectID);
        $this->topicList = array_keys($appLog);
    }

    public function showNums(string $operType): array
    {
        if (isset($this->queueList[$operType]) && !empty($this->topicList)) {
            $topicTemp = $this->queueList[$operType];
            $ret = [];
            foreach($this->topicList as $topic) {
                $topicName = sprintf($topicTemp, $this->projectID, $topic);
                $num = Redis::llen($topicName);
                $ret[$topic] = $num;
            }
            return $ret;
        }
        return [];
    }

    public function searchData(string $operType, string $topic, int $start, int $end): string
    {
        if (isset($this->queueList[$operType]) && !empty($this->topicList)) {
            $topicTemp = $this->queueList[$operType];
            $topicName = sprintf($topicTemp, $this->projectID, $topic);
            $ret = Redis::lrange($topicName, $start, $end);
            if (!$ret) return '';
                
            $mergeData = [];
            $columnSet = [];
            $columnIndex = 0;
            foreach ($ret as $key => $data) {
                $data = unserialize(gzuncompress(unserialize($data)));
                if ($key == $columnIndex) {
                    $columnSet = array_keys($data[$columnIndex]);
                }
                $mergeData = array_merge($mergeData, $data);                
            }
            return $this->echoSearchData($columnSet, $mergeData);
        }
        return '';
    }

    private function echoSearchData(array $columnSet, array $mergeData): string
    {
        $echoStr = '';

        foreach ($columnSet as $column) {
            $echoStr .= $column . "\t";
        }
        $echoStr .= "\n";

        foreach ($mergeData as $data) {
            foreach ($data as $k => $v) {
                $echoStr .= $v . "\t";
            }
            $echoStr .= "\n";
        }
        return $echoStr;
    }


}