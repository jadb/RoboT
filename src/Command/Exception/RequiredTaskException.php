<?php
namespace Robot\Command\Exception;

use Exception;

class RequiredTaskException extends \Exception  {

    public function __construct($command, $requiredTask)
    {
        parent::__construct("$command command requires the $requiredTask task.");
    }

}

