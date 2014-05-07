<?php

namespace Robot\Task;

use Robo\Output;
use Robo\Result;
use Robo\Task\Exec;
use Robo\Task\Shared\CommandInterface;
use Robo\Task\Shared\DynamicConfig;
use Robo\Task\Shared\TaskInterface;
use Robot\Task\Shared\RequirementStack;

trait Git
{
    protected function taskGit($pathToGit = 'git')
    {
        return new GitStackTask($pathToGit);
    }

    protected function taskGitRequirements($pathToGit = 'git')
    {
        return new GitRequirementStack($pathToGit);
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

class GitRequirementStack extends RequirementStack
{
    use Output;

    protected $requirements = [
        'Git repository missing' => '[ -d .git ] || %executable% rev-parse --git-dir 2> /dev/null',
    ];
    protected $stopOnFail = true;

    public function __construct($pathToGit = 'git')
    {
        $this->executable = 'git';
    }

    public function check()
    {
        $closure = function($command) {
            return str_replace('%executable%', $this->executable, $command);
        };
        $this->requirements = array_map($closure, $this->requirements);
        return parent::check();
    }

    public function clean($cache = true)
    {
        $command = '[[ $(echo "`%s`" 2> /dev/null | tail -n1) == "" ]]';
        $this->requirements['Dirty repository index'] = sprintf($command, '%executable% diff --shortstat');
        if ($cache) {
            $this->requirements['Dirty repository cache'] = sprintf($command, '%executable% diff --shortstat --cached');
        }
        return $this;
    }

    public function requirements()
    {
        return $this->requirements;
    }

    public function run()
    {
        $this->printTaskInfo('checking requirements...');
        return parent::run();
    }

}
