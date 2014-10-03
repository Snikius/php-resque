<?php

class Resque_Job_Closure extends Resque_Job {
    
    private $_closure;
    
    public function getClosure() {    
        if (!is_null($this->_closure)) {
                return $this->_closure;
        }
        $this->_closure=unserialize($this->payload['class']);
        return $this->_closure;
    }
    
    public function perform() {
        try {
                Resque_Event::trigger('beforePerform', $this);
                
                $closure=$this->getClosure();
                $closure($this, $this->getArguments());
                
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
            Resque::push($queue, array(
                    'class'	=> $class,
                    'args'	=> array($args),
                    'id'	=> $id,
                    'closure'   => true,
                    'queue_time' => microtime(true),
            ));

            if($monitor) {
                    Resque_Job_Status::create($id);
            }

            return $id;
    }
}