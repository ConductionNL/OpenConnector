<?php

namespace OCA\OpenConnector\Cron;

use OCA\OpenConnector\Db\JobMapper;
use OCA\OpenConnector\Db\JobLog;
use OCA\OpenConnector\Db\JobLogMapper;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use Psr\Container\ContainerInterface;
use Symfony\Component\Uid\Uuid;
use DateInterval;
use DateTime;

/**
 * This class is used to run the action tasks for the OpenConnector app. It hooks into the cron job list and runs the classes that are set as the job class in the job.
 *
 * @package OCA\OpenConnector\Cron
 */
class ActionTask extends TimedJob
{
    private JobMapper $jobMapper;
    private JobLogMapper $jobLogMapper;
    private IJobList $jobList;
    private ContainerInterface $containerInterface;

    public function __construct(
        ITimeFactory $time,
        JobMapper $jobMapper,
        JobLogMapper $jobLogMapper,
        IJobList $jobList,
        ContainerInterface $containerInterface
    ) {
        parent::__construct($time);
        $this->jobMapper = $jobMapper;
        $this->jobLogMapper = $jobLogMapper;
        $this->jobList = $jobList;
        $this->containerInterface = $containerInterface;
        // Run every 5 minutes
        //$this->setInterval(300);

        // Delay until low-load time
        //$this->setTimeSensitivity(\OCP\BackgroundJob\IJob::TIME_SENSITIVE);
        // Or $this->setTimeSensitivity(\OCP\BackgroundJob\IJob::TIME_INSENSITIVE);

        // Only run one instance of this job at a time
        //$this->setAllowParallelRuns(false);
    }

	/**
	 * @todo
	 * @todo: make this a bit more generic :')
	 *
	 * @param $argument
	 *
	 * @return JobLog|void
	 */
    public function run($argument)
    {
        // if we do not have a job id then everything is wrong
        if (isset($arguments['jobId']) && is_int($argument['jobId'])) {
            return;
        }

        // Let's get the job, the user might have deleted it in the mean time
        try {
            $job = $this->jobMapper->find($argument['jobId']);
        } catch (Exception $e) {
            return;
        }

        // If the job is not enabled, we don't need to do anything
        if (!$job->getIsEnabled()) {
            return;
        }

        // if the next run is in the the future, we don't need to do anything
        if ($job->getNextRun() && $job->getNextRun() > new DateTime()) {
            return;
        }

		$time_start = microtime(true);

        $action =  $this->containerInterface->get($job->getJobClass());
        $arguments = $job->getArguments();
        if(!is_array($arguments)){
            $arguments = [];
        }
        $result = $action->run($arguments);

        $time_end = microtime(true);
        $executionTime = ( $time_end - $time_start ) * 1000;

        // deal with single run
        if ($job->isSingleRun()) {
            $job->setIsEnabled(false);
        }


        // Update the job
        $job->setLastRun(new DateTime());
        $job->setNextRun(new DateTime());
        $this->jobMapper->update($job);

        // Log the job
        $jobLog = new JobLog();
        $jobLog->setUuid(Uuid::v4());
        $jobLog->setJobId($job->getId());
        $jobLog->setJobClass($job->getJobClass());
        $jobLog->setJobListId($job->getJobListId());
        $jobLog->setArguments($job->getArguments());
        $jobLog->setLastRun($job->getLastRun());
        $jobLog->setNextRun($job->getNextRun());
        $jobLog->setExecutionTime($executionTime);

        // Get the result and set it to the job log
        if (is_array($result)) {
            if (isset($result['level'])) {
                $jobLog->setLevel($result['level']);
            }
            if (isset($result['message'])) {
                $jobLog->setMessage($result['message']);
            }
            if (isset($result['stackTrace'])) {
                $jobLog->setStackTrace($result['stackTrace']);
            }
        }

        $this->jobLogMapper->insert($jobLog);

        // Let's report back about what we have just done
        return $jobLog;
    }

}
