<?php

namespace Robot\Task;

use Robo\Output;
use Robo\Result;
use Robo\Task\Exec;
use Robo\Task\Shared\CommandInterface;
use Robo\Task\Shared\TaskInterface;

trait Git
{
    protected function taskGit($pathToGit = 'git')
    {
        return new GitStackTask($pathToGit);
    }

    protected function taskGitRequiredClean($pathToGit = 'git')
    {
        return new GitRequiredCleanTask($pathToGit);
    }
}

/**
 * Runs Git commands in stack. You can use `stopOnFail()` to point that stack should be terminated on first fail.
 *
 * ``` php
 * <?php
 * $this->taskGit()
 *  ->stopOnFail()
 *  ->add('-A')
 *  ->commit('adding everything')
 *  ->push('origin','master')
 *  ->run()
 *
 * $this->taskGit()
 *  ->stopOnFail()
 *  ->add('doc/*')
 *  ->commit('doc updated')
 *  ->push()
 *  ->run();
 * ?>
 * ```
 */
class GitStackTask implements TaskInterface, CommandInterface
{
    use Exec;
    use Output;

    protected $git;
    protected $stackCommands = [];
    protected $stopOnFail = false;
    protected $tagged = false;
    protected $result;

    public function __construct($pathToGit = 'git')
    {
        $this->git = $pathToGit;
        $this->result = Result::success($this);
    }

    public function cloneRepo($repo, $to = null)
    {
        $this->addCommand(['clone', $repo, $to]);
        return $this;
    }

    public function stopOnFail()
    {
        $this->stopOnFail = true;
        return $this;
    }

    public function add($pattern = null, $options = '-A')
    {
        $this->addCommand([__FUNCTION__, $this->normalize($options), $pattern]);
        return $this;
    }

    public function commit($message, $options = null)
    {
        if (!empty($message)) {
            $options .= "-m '$message'";
        }
        $this->addCommand([__FUNCTION__, $this->normalize($options)]);
        return $this;
    }

    public function tag($tag, $message = null, $options = null)
    {
        if (!empty($message)) {
            $options .= " -m '$message'";
        }
        $this->addCommand([__FUNCTION__, $this->normalize($options), $tag]);
        $this->tagged = true;
        return $this;
    }

    public function pull($origin = null, $branch = null, $options = null)
    {
        $this->addCommand([__FUNCTION__, $this->normalize($options), $origin, $branch]);
        return $this;
    }

    public function push($origin = null, $branch = null, $options = null)
    {
        $this->addCommand([__FUNCTION__, $this->normalize($options), $origin, $branch]);
        if ($this->tagged) {
            $this->addCommand([__FUNCTION__, '--tags']);
            $this->tagged = false;
        }
        return $this;
    }

    public function checkout($branch)
    {
        $this->addCommand([__FUNCTION__, $options, $branch]);
        return $this;
    }

    public function addCommand($command)
    {
        if (is_array($command)) {
            $command = implode(' ', array_filter($command));
        }

        $this->stackCommands[] = $command;

        return $this;
    }

    public function getCommand()
    {
        $commands = array_map(function($c) { return $this->git .' '. $c; }, $this->stackCommands);
        return implode(' && ', $commands);
    }

    public function run()
    {
        $this->printTaskInfo("Running git commands...");
        foreach ($this->stackCommands as $command) {
            $this->result = $this->taskExec($this->git .' '.$command)->run();
            if (!$this->result->wasSuccessful() and $this->stopOnFail) {
                return $this->result;
            }
        }
        return Result::success($this);
    }

    protected function normalize($options)
    {
        if (!is_array($options)) {
            return $options;
        }

        foreach ($options as $opt => $val) {
            unset($options[$opt]);

            if (is_numeric($opt)) {
                $opt = $val;
                $val = null;
            }

            $opt = ltrim($opt, '-');

            $options[] = '-' . (strlen($opt) < 2 ?: '-') . $opt . ' ' . $val;
        }

        return $options;
    }
}

class GitRequiredCleanTask implements TaskInterface, CommandInterface
{
    const DIFFINDEX = 'diff --shortstat';

    const DIFFCACHE = 'diff --shortstat --cached';

    const COMMAND = '[[ $(echo "`%s`" 2> /dev/null | tail -n1) == "" ]]';

    protected $git;

    protected $cache = true;

    protected $index = true;

    public function __construct($pathToGit = 'git')
    {
        $this->git = $pathToGit;
    }

    public function noCache()
    {
        if (!$this->index) {
            throw new TaskException();
        }
        $this->cache = false;
        return $this;
    }

    public function noIndex()
    {
        if (!$this->cache) {
            throw new TaskException();
        }
        $this->index = false;
        return $this;
    }

    public function getCommand()
    {
        $command = [];
        if ($this->index) {
            array_push($command, $this->git . ' ' . self::DIFFINDEX);
        }

        if ($this->cache) {
            array_push($command, $this->git . ' ' . self::DIFFCACHE);
        }


        return sprintf(self::COMMAND, implode('``', $command));
    }

    public function run()
    {
        exec($this->getCommand(), $output, $code);

        if ($code) {
            return Result::error($this, 'Dirty repo');
        }

        return Result::success($this);
    }
}
