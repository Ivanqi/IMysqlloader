<?php declare(strict_types=1);

namespace App\Console\Command;

use Swoft\Console\Annotation\Mapping\Command;
use Swoft\Console\Annotation\Mapping\CommandMapping;
use Swoft\Console\Annotation\Mapping\CommandArgument;
use Swoft\Console\Annotation\Mapping\CommandOption;

use Swoft\Console\Exception\ConsoleErrorException;
use Swoft\Console\Helper\Show;
use Swoft\Http\Server\Router\Route;
use Swoft\Console\Input\Input;
use Swoft\Console\Output\Output;
use App\Repositories\ShowTopicsCommandRepositories;
use function input;
use function output;
use function sprintf;

/**
 *  Topics操作
 * @Command(name="topics",coroutine=true)
 */
class ShowTopicsCommand 
{
    private $showTopicsCommandRepositories;
    public function __construct()
    {
        $this->showTopicsCommandRepositories = ShowTopicsCommandRepositories::getInstance();
    }

    /**
     * @CommandMapping(name="show_topics_num", desc="展示还未消费topics数量")
     */
    public function showTopicsNum(Input $input, Output $output): void
    {
        $ret = $this->showTopicsCommandRepositories->showNums(ShowTopicsCommandRepositories::TOPICS_LIST);
        if ($ret) {
            Show::aList($ret);
        } else {
            $output->error("没有数据");
        }
    }

    /**
     * @CommandMapping(name="show_fail_topics_num", desc="展示失败topics数量")
     */
    public function showFailTopicsNum(Input $input, Output $output): void
    {
        $ret = $this->showTopicsCommandRepositories->showNums(ShowTopicsCommandRepositories::TOPICS_FAIL_LIST);
        if ($ret) {
            Show::aList($ret);
        } else {
            $output->error("没有数据");
        }
    }

    /**
     * @CommandMapping(name="show_1s_fail_topics_num", desc="展示每秒内失败topics数量")
     */
    public function show1SFailTopicsNum(Input $input, Output $output)
    {
        $ret = $this->showTopicsCommandRepositories->showNums(ShowTopicsCommandRepositories::FAIL_TOPICS_LIST);
        if ($ret) {
            Show::aList($ret);
        } else {
            $output->error("没有数据");
        }
    }

    /**
     * @CommandMapping(name="show_5min_fail_topics_num", desc="展示5min失败topics数量")
     */
    public function show5MinFailTopicsNum(Input $input, Output $output): void
    {
        $ret = $this->showTopicsCommandRepositories->showNums(ShowTopicsCommandRepositories::FIVEMIN_FAIL_TOPICS_LIST);
        if ($ret) {
            Show::aList($ret);
        } else {
            $output->error("没有数据");
        }
    }
    
    /**
     * @CommandMapping(name="show_common_fail_topics_num", desc="展示最终失败topics数量")
     */
    public function showCommonFailTopicsNum(Input $input, Output $output): void
    {
        $ret = $this->showTopicsCommandRepositories->showNums(ShowTopicsCommandRepositories::COMMON_FAIL_TOPICS_LIST);
        if ($ret) {
            Show::aList($ret);
        } else {
            $output->error("没有数据");
        }
    }

    /**
     * @CommandMapping(name="search_topics", desc="查询未消费topics信息")
     * @CommandOption("topicName", short="t", type="string", default="test",desc="Topics的名称")
     * @CommandOption("start", short="s", type="int", default="0",desc="范围查找的开始")
     * @CommandOption("end", short="e", type="int", default="0",desc="范围查找的结束")
     * @example
     *     {searchTopics} searchTopics -t test -s 0 -e 0
     * @param Input $input
     * @param Output $output
     */
    public function searchTopics(Input $input, Output $output): void
    {
        $opts = $input->getOpts();
        $topicName = $opts['topicName'];
        $start = (int) $opts['start'];
        $end = (int) $opts['end'];
        $ret = $this->showTopicsCommandRepositories->searchData(ShowTopicsCommandRepositories::TOPICS_LIST, $topicName, $start, $end);
        
        if (empty($ret)) {
            $output->error("没有数据");
        } else {
            echo $ret;
        }
    }

    /**
     * @CommandMapping(name="search_fail_topics", desc="查询失败topics信息")
     * @CommandOption("topicName", short="t", type="string", default="test",desc="Topics的名称")
     * @CommandOption("start", short="s", type="int", default="0",desc="范围查找的开始")
     * @CommandOption("end", short="e", type="int", default="0",desc="范围查找的结束")
     * @example
     *     {searchFailTopics} searchFailTopics -t test -s 0 -e 0
     * @param Input $input
     * @param Output $output
     */
    public function searchFailTopics(Input $input, Output $output): void
    {
        $opts = $input->getOpts();
        $topicName = $opts['topicName'];
        $start = (int) $opts['start'];
        $end = (int) $opts['end'];
        $ret = $this->showTopicsCommandRepositories->searchData(ShowTopicsCommandRepositories::TOPICS_FAIL_LIST, $topicName, $start, $end);
        
        if (empty($ret)) {
            $output->error("没有数据");
        } else {
            echo $ret;
        }
    }

    /**
     * @CommandMapping(name="search_1s_fail_topics", desc="查询1s失败topics信息")
     * @CommandOption("topicName", short="t", type="string", default="test",desc="Topics的名称")
     * @CommandOption("start", short="s", type="int", default="0",desc="范围查找的开始")
     * @CommandOption("end", short="e", type="int", default="0",desc="范围查找的结束")
     * @example
     *     {search1sFailTopics} search1sFailTopics -t test -s 0 -e 9
     * @param Input $input
     * @param Output $output
     */
    public function search1sFailTopics(Input $input, Output $output): void
    {
        $opts = $input->getOpts();
        $topicName = $opts['topicName'];
        $start = (int) $opts['start'];
        $end = (int) $opts['end'];
        $ret = $this->showTopicsCommandRepositories->searchData(ShowTopicsCommandRepositories::FAIL_TOPICS_LIST, $topicName, $start, $end);
        
        if (empty($ret)) {
            $output->error("没有数据");
        } else {
            echo $ret;
        }
    }


    /**
     * @CommandMapping(name="search_5min_fail_topics", desc="查询5min失败topics信息")
     * @CommandOption("topicName", short="t", type="string", default="test",desc="Topics的名称")
     * @CommandOption("start", short="s", type="int", default="0",desc="范围查找的开始")
     * @CommandOption("end", short="e", type="int", default="0",desc="范围查找的结束")
     * @example
     *     {search5minFailTopics} search5minFailTopics -t test -s 0 -e 0
     * @param Input $input
     * @param Output $output
     */
    public function search5minFailTopics(Input $input, Output $output): void
    {
        $opts = $input->getOpts();
        $topicName = $opts['topicName'];
        $start = (int) $opts['start'];
        $end = (int) $opts['end'];
        $ret = $this->showTopicsCommandRepositories->searchData(ShowTopicsCommandRepositories::FIVEMIN_FAIL_TOPICS_LIST, $topicName, $start, $end);
        
        if (empty($ret)) {
            $output->error("没有数据");
        } else {
            echo $ret;
        }
    }

    /**
     * @CommandMapping(name="search_common_fail_topics", desc="查询最终失败topics信息")
     * @CommandOption("topicName", short="t", type="string", default="test",desc="Topics的名称")
     * @CommandOption("start", short="s", type="int", default="0",desc="范围查找的开始")
     * @CommandOption("end", short="e", type="int", default="0",desc="范围查找的结束")
     * @example
     *     {searchCommonFailTopics} searchCommonFailTopics -t test -s 0 -e 0
     * @param Input $input
     * @param Output $output
     */
    public function searchCommonFailTopics(Input $input, Output $output): void
    {
        $opts = $input->getOpts();
        $topicName = $opts['topicName'];
        $start = (int) $opts['start'];
        $end = (int) $opts['end'];
        $ret = $this->showTopicsCommandRepositories->searchData(ShowTopicsCommandRepositories::COMMON_FAIL_TOPICS_LIST, $topicName, $start, $end);
        
        if (empty($ret)) {
            $output->error("没有数据");
        } else {
            echo $ret;
        }
    }
}