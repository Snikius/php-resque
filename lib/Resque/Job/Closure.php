<?php
use Illuminate\Support\SerializableClosure;

class Resque_Job_Closure extends Resque_Job {
    
    private $_closure;
    
    public function getClosure() {    
        if (!is_null($this->_closure)) {
                return $this->_closure;
        }
        $this->_closure=unserialize($this->payload['class']);
        return $this->_closure;
    }
    
    public static function invokeSerializableClosure(SerializableClosure $serializable, Resque_Job $job ,array $data = []) {
        $varibles=$serializable->getVariables();
        foreach($varibles as $k=>$varib) {
            $$k=$varib;
        }
        eval('$callback = '.$serializable->getCode());
        $callback($job, $data);
    }
 
    public function perform() {
        try {
            $closure=$this->getClosure();
            Resque_Event::trigger('beforePerform', $this);
            Resque_Job_Closure::invokeSerializableClosure($closure, $this, $this->getArguments());
            Resque_Event::trigger('afterPerform', $this);
        }
        catch(Resque_Job_DontPerform $e) {
                return false;
        }

        return true;
    }
    
    
    public static function create($queue, $class, $args = null, $monitor = false) {
            if($args !== null && !is_array($args)) {
                    throw new InvalidArgumentException(
                            'Supplied $args must be an array.'
                    );
            }
            $id = md5(uniqid('', true));
            $data=array(
                    'class'	=> $class,
                    'args'	=> array($args),
                    'id'	=> $id,
                    'closure'   => true,
                    'queue_time' => microtime(true),
            );
            Log::info('Push closure:'.  json_encode($data));
            Resque::push($queue,$data);

            if($monitor) {
                    Resque_Job_Status::create($id);
            }

            return $id;
    }
}