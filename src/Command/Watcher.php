<?php

namespace Robot\Command;

use Robot\Command\Exception\RequiredTaskException;

trait Watcher
{
/**
 * @desc Auto-updates by watching `composer.json`.
 */
    public function watchComposer()
    {
        if (!$this->watcherReady()) {
            return Result::error($this);
        }

        $this->taskWatch()->monitor('composer.json', function() {
            $this->taskComposerUpdate()->run();
        })->run();
    }

    protected function watcherComposerReady()
    {
        $task = 'Robo\Task\Composer';
        if (!in_array($task, class_uses($this))) {
            throw new RequiredTaskException('Bumper', $task);
        }

        return true;
    }

    protected function watcherWatchReady()
    {
        $task = 'Robo\Task\Watch';
        if (!in_array($task, class_uses($this))) {
            throw new RequiredTaskException('Bumper', $task);
        }

        return true;
    }

    protected function watcherReady()
    {
        return $this->watcherWatchReady()
            && $this->watcherComposerReady();
    }
}
