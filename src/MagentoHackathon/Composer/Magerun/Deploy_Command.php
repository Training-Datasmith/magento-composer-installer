<?php

declare (strict_types=1);
namespace Magento_Hackathon\Composer\Magerun;

use N98\Magento\Command\Abstract_Magento_Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
class Deploy_Command extends Abstract_Magento_Command
{
    protected function configure()
    {
        $this->set_name('composer:magento:deploy')->set_description('Test command registered in a module');
    }
    /**
     * @return int|void
     */
    protected function execute(Input_Interface $input, Output_Interface $output)
    {
        $output->writeln('it works, maybe');
    }
}