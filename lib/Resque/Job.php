<?php
/**
 * Resque job.
 *
 * @package		Resque/Job
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
abstract class Resque_Job
{       
	/**
	 * @var string The name of the queue that this job belongs to.
	 */
	public $queue;

	/**
	 * @var Resque_Worker Instance of the Resque worker running this job.
	 */
	public $worker;

	/**
	 * @var object Object containing details of the job.
	 */
	public $payload;

	/**
	 * @var object Instance of the class performing work for this job.
	 */
	private $instance;

        
        abstract public function perform();
                
	/**
	 * Instantiate a new instance of a job.
	 *
	 * @param string $queue The queue that the job belongs to.
	 * @param array $payload array containing details of the job.
	 */
	public function __construct($queue, $payload)
	{
		$this->queue = $queue;
		$this->payload = $payload;
	}

	/**
	 * Update the status of the current job.
	 *
	 * @param int $status Status constant from Resque_Job_Status indicating the current status of a job.
	 */
	public function updateStatus($status)
	{
		if(empty($this->payload['id'])) {
			return;
		}

		$statusInstance = new Resque_Job_Status($this->payload['id']);
		$statusInstance->update($status);
	}

	/**
	 * Return the status of the current job.
	 *
	 * @return int The status of the job as one of the Resque_Job_Status constants.
	 */
	public function getStatus()
	{
		$status = new Resque_Job_Status($this->payload['id']);
		return $status->get();
	}

	/**
	 * Get the arguments supplied to this job.
	 *
	 * @return array Array of arguments.
	 */
	public function getArguments()
	{
		if (!isset($this->payload['args'])) {
			return array();
		}

		return $this->payload['args'][0];
	}
   

	/**
	 * Mark the current job as having failed.
	 *
	 * @param $exception
	 */
	public function fail($exception)
	{
		Resque_Event::trigger('onFailure', array(
			'exception' => $exception,
			'job' => $this,
		));

		$this->updateStatus(Resque_Job_Status::STATUS_FAILED);
		Resque_Failure::create(
			$this->payload,
			$exception,
			$this->worker,
			$this->queue
		);
		Resque_Stat::incr('failed');
		Resque_Stat::incr('failed:' . $this->worker);
	}

	/**
	 * Re-queue the current job.
	 * @return string
	 */
	public function recreate()
	{
		$status = new Resque_Job_Status($this->payload['id']);
		$monitor = false;
		if($status->isTracking()) {
			$monitor = true;
		}

		return static::create($this->queue, $this->payload['class'], $this->getArguments(), $monitor);
	}

	/**
	 * Generate a string representation used to describe the current job.
	 *
	 * @return string The string representation of the job.
	 */
	public function __toString()
	{
		$name = array(
			'Job{' . $this->queue .'}'
		);
		if(!empty($this->payload['id'])) {
			$name[] = 'ID: ' . $this->payload['id'];
		}
		$name[] = $this->payload['class'];
		if(!empty($this->payload['args'])) {
			$name[] = json_encode($this->payload['args']);
		}
		return '(' . implode(' | ', $name) . ')';
	}
}
?>
