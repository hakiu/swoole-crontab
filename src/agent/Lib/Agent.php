<?php
/**
 * Created by PhpStorm.
 * User: liuzhiming
 * Date: 2017/7/11
 * Time: 14:31
 */

namespace Lib;

use Lib;

class Agent
{

    /**
     * @var \Lib\Server
     */
    public static $client;

    /**
     * 重连定时器id
     * @var
     */
    public $reConnectTimerId;
    /**
     * 重连时间
     */
    const RE_CONNECT_TIME = 10*1000;

    static $is_close = false;


    public function onWorkStart()
    {
        self::$client->connect();
        Lib\Process::signal();//注册信号
        //5秒同步一次日志
        swoole_timer_tick(5000,function (){
            Lib\Process::notify();
        });
        //1秒执行一次超时判断
        swoole_timer_tick(1000,function (){
            Lib\Process::timeout();
        });
    }

    public function onConnect($client)
    {
        echo "连接成功\n";
        //清除重连定时器
        $this->clearTimer();
        //连接上了注册服务
        $this->register();
    }

    /**
     * 报错
     * @param $client
     */
    public function onError($client)
    {
        if ($client->errCode == 61 || $client->errCode == 111){
            echo date("Y-m-d H:i:s")." 连接中心服失败\n";
            $this->reConnect();
        }else{
            echo "Error=>code:".$client->errCode."msg:".socket_strerror($client->errCode)."\n";
        }

    }

    /**
     * 收到消息
     * @param $client
     * @param $data
     */
    public function onReceive($client,$data)
    {
        if (is_bool($data = $this->call($data))){
            return ;
        }
    }

    /**
     * 连接关闭
     * @param $client
     */
    public function onClose($client)
    {
        echo "连接关闭\n";
        $this->reConnect();
    }

    /**
     * 回调
     * @param $data
     * @return bool
     */
    protected function call($data)
    {
        if (!isset($data['call']) || !isset($data['params'])) {
            return $data;
        }

        if ($data['call'] == "App\\close"){
            echo date("Y-m-d H:i:s")." 服务端已发送强制关闭命令,等待正在运行任务结束\n";
            $this->clearTimer();
            self::$is_close = true;
        }
        //函数不存在
        if (!is_callable($data['call'])) {
            return false;
        }
        //调用接口方法
        call_user_func_array($data['call'], $data['params']);
        return true;
    }

    /**
     * 发送注册消息
     */
    protected function register()
    {
        self::$client->call("register");
    }

    /**
     * 清除重连定时器
     */
    protected function clearTimer()
    {
        if ($this->reConnectTimerId){
            swoole_timer_clear($this->reConnectTimerId);
        }
        $this->reConnectTimerId = 0;
    }

    /**
     * 定时重连服务器
     */
    protected function reConnect()
    {
        $this->clearTimer();
        $this->reConnectTimerId = swoole_timer_tick(self::RE_CONNECT_TIME,function (){
            self::$client->connect();
        });
    }
}