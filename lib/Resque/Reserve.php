<?php

class Resque_Reserve {
    public static function reserve($queue) {
        $payload = Resque::pop($queue);
        if(!is_array($payload)) {
            return false;
        }      
        
        if(isset($payload['closure'])) {
            return new Resque_Job_Closure($queue, $payload);
        } else {
            return new Resque_Job_Class($queue, $payload);
        }
    }
    
    public static function reserveBlocking(array $queues, $timeout = null)
    {
        $item = Resque::blpop($queues, $timeout);

        if(!is_array($item)) {
            return false;
        }
        
        if(isset($item['payload']['closure'])) {
            return new Resque_Job_Closure($item['queue'], $item['payload']);
        } else {
            return new Resque_Job_Class($item['queue'], $item['payload']);
        }
    }
}