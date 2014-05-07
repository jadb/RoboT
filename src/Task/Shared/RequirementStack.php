<?php

namespace Robot\Task\Shared;

use Robo\Result;
use Robo\Task\Shared\DynamicConfig;
use Robo\Task\Shared\TaskInterface;

class RequirementStack implements TaskInterface, RequirementInterface
{
    use DynamicConfig;

    protected $requirements = [];
    protected $stopOnFail = false;
    protected $results = [];

    public function results()
    {
        return $this->results;
    }

    public function check()
    {
        foreach ($this->requirements as $requirement => $command) {
            exec($command, $output, $this->results[$requirement]);
            if ($this->stopOnFail && $this->results[$requirement]) {
                return false;
            }
        }

        return !(bool)array_filter($this->results);
    }

    public function run() {
        if ($this->check()) {
            return Result::success($this);
        }

        $message = 'One or more requirements failed';
        if ($this->stopOnFail) {
            $requirements = array_keys($this->results);
            $message = array_pop($requirements);
        }
        return Result::error($this, $message);
    }
}
