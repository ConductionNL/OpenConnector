<?php

namespace OCA\OpenConnector\Action;

use OCA\OpenConnector\Service\CallService;
use OCA\OpenConnector\Db\SourceMapper;

/**
 * This class is used to run the action tasks for the OpenConnector app. It hooks into the cron job list and runs the classes that are set as the job class in the job.
 *
 * @package OCA\OpenConnector\Cron
 */
class EventAction
{
    private CallService $callService;
    private SourceMapper $sourceMapper;
    public function __construct(
        CallService $callService,
        SourceMapper $sourceMapper,
    ) {
        $this->callService = $callService;
    }

    //@todo: make this a bit more generic :')
    public function run(array $argument = []): array
    {
        // @todo: implement this

        // Let's report back about what we have just done
        return [];
    }

}
