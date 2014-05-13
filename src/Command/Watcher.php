<?php

namespace Robot\Command;

use Robot\Command\Exception\RequiredTaskException;

trait Watcher
{
    protected $taskWatch;

/**
 * @desc Runs all the `watch:*` commands.
 */
    public function watch() {
        if (!$this->watcherReady()) {
            return Result::error($this);
        }

        $this->watcherCodeceptionMonitor();
        $this->watcherComposerMonitor();
        $this->taskWatch->run();
    }

/**
 * @desc Runs `codecept build` by monitoring codeception paths.
 */
    public function watchCodeception() {
        if (!$this->watcherReady()) {
            return Result::error($this);
        }

        $this->watcherCodeceptionMonitor();
        $this->taskWatch->run();
    }

/**
 * @desc Runs `composer update` by monitoring `composer.json`.
 */
    public function watchComposer()
    {
        if (!$this->watcherReady()) {
            return Result::error($this);
        }

        $this->watcherComposerMonitor();
        $this->taskWatch->run();
    }

    protected function watcherCodeceptionMonitor() {
        $callback = function() {
            $this->taskExec('vendor/bin/codecept build')->run();
        };

        $this->taskWatch = $this->taskWatch->monitor('tests/_helpers', $callback);

        foreach (['acceptance', 'functional', 'unit'] as $name) {
            $file = "tests/$name.suite.yml";
            if (!file_exists($file)) {
                $file = "tests/$name.suite.dist.yml";
                if (!file_exists($file)) {
                    continue;
                }
            }
            $this->taskWatch = $this->taskWatch->monitor($file, $callback);
        }
    }

    protected function watcherComposerMonitor() {
        $this->taskWatch = $this->taskWatch->monitor('composer.json', function() {
            $this->taskComposerUpdate()->run();
        });
    }

    protected function watcherComposerReady()
    {
        $task = 'Robo\Task\Composer';
        if (!in_array($task, class_uses($this))) {
            throw new RequiredTaskException('Composer', $task);
        }

        return true;
    }

    protected function watcherExecReady()
    {
        $task = 'Robo\Task\Exec';
        if (!in_array($task, class_uses($this))) {
            throw new RequiredTaskException('Exec', $task);
        }

        return true;
    }

    protected function watcherWatchReady()
    {
        $task = 'Robo\Task\Watch';
        if (!in_array($task, class_uses($this))) {
            throw new RequiredTaskException('Watch', $task);
        }

        return true;
    }

    protected function watcherReady()
    {
        if (
            empty($this->taskWatch)
            && $this->watcherComposerReady()
            && $this->watcherExecReady()
            && $this->watcherWatchReady()
        ) {
            $this->taskWatch = $this->taskWatch();
        }

        return !empty($this->taskWatch);
    }
}
