<?php
namespace framework\driver\queue;

/*
 * https://github.com/arnaud-lb/php-rdkafka
 */
class Kafka extends Queue
{
    protected function connect($role)
    {
        $class = "RdKafka\\$role";
        if (isset($this->config['options'])) {
            $conf = new \RdKafka\Conf();
            foreach ($config['options'] as $k => $v) {
                $conf->set($k, $v);
            }
        }
        $connection = new $class($conf ?? null);
        $connection->addBrokers($this->config['hosts']);
        return $connection;
    }
}
