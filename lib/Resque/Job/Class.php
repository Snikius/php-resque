<?php

class Resque_Job_Class extends Resque_Job {
    
    private $instance;
    private $method;
    
    const INSTANCE_DEFAULT_METHOD = "fire";
        
    const INSTANCE_METHOD_NAME = "_instance_method";
    
    public function getInstance() {     
        if (!is_null($this->instance)) {
                return $this->instance;
        }

        $arguments=$this->getArguments();
        $this->method = Resque_Job_Class::INSTANCE_DEFAULT_METHOD;
        $class = ucfirst($this->payload['class']);
        if(isset($arguments[Resque_Job_Class::INSTANCE_METHOD_NAME])) {
            $this->method = $arguments[Resque_Job_Class::INSTANCE_METHOD_NAME];
        }

        if(!class_exists($class)) {
                throw new Resque_Exception(
                        'Could not find job class ' . $class . '.'
                );
        }

        if(!method_exists($class, $this->method)) {
                throw new Resque_Exception(
                        'Job class ' . $class . ' does not contain a '.$this->method.' method.'
                );
        }

        $this->instance = new $class;
        $this->instance->job = $this;
        $this->instance->args = $this->getArguments();
        $this->instance->queue = $this->queue;
        return $this->instance;
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
                    'queue_time' => microtime(true),
            ));

            if($monitor) {
                    Resque_Job_Status::create($id);
            }

            return $id;
    }
    
    public function getClosure() {     
        if (!is_null($this->_closure)) {
                return $this->_closure;
        }
        $this->_closure=unserialize($this->payload['class']);
        return $this->_closure;
    }
    
    public function perform()
    {
        try {
                Resque_Event::trigger('beforePerform', $this);

                $instance = $this->getInstance();
                if(method_exists($instance, 'setUp')) {
                        $instance->setUp();
                }
                
                $method=$this->method;
                $instance->$method($this,$this->getArguments());

                if(method_exists($instance, 'tearDown')) {
                        $instance->tearDown();
                }

                Resque_Event::trigger('afterPerform', $this);
        }
        // beforePerform/setUp have said don't perform this job. Return.
        catch(Resque_Job_DontPerform $e) {
                return false;
        }

        return true;
    }
}