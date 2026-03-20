<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento\Command;

use Magento_Hackathon\Composer\Magento\Deploy\Manager\Entry;
use Magento_Hackathon\Composer\Magento\Deploy_Manager;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @author Tiago Ribeiro <tiago.ribeiro@seegno.com>
 * @author Rui Marinho <rui.marinho@seegno.com>
 */
class Deploy_Command extends \Composer\Command\Base_Command
{
    protected function configure()
    {
        $this->set_name('magento-module-deploy')->set_description('Deploy all Magento modules loaded via composer.json')->set_definition([])->set_help(<<<EOT
        This command deploys all magento Modules
        
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output)
    {
        // init repos
        $composer = $this->get_composer();
        $installed_repo = $composer->get_repository_manager()->get_local_repository();
        $composer->get_download_manager();
        $im = $composer->get_installation_manager();
        /**
         * @var $moduleInstaller \MagentoHackathon\Composer\Magento\Installer
         */
        $module_installer = $im->get_installer('magento-module');
        $deploy_manager = new Deploy_Manager($this->get_io());
        $extra = $composer->get_package()->get_extra();
        $sort_priority = $extra['magento-deploy-sort-priority'] ?? [];
        $deploy_manager->set_sort_priority($sort_priority);
        $module_installer->set_deploy_manager($deploy_manager);
        foreach ($installed_repo->get_packages() as $package) {
            if ($input->get_option('verbose')) {
                $output->writeln($package->get_name());
                $output->writeln($package->get_type());
            }
            if ($package->get_type() != 'magento-module') {
                continue;
            }
            if ($input->get_option('verbose')) {
                $output->writeln("package {$package->get_name()} recognized");
            }
            $strategy = $module_installer->get_deploy_strategy($package);
            if ($input->get_option('verbose')) {
                $output->writeln('used ' . $strategy::class . ' as deploy strategy');
            }
            $strategy->set_mappings($module_installer->get_parser($package)->get_mappings());
            $deploy_manager_entry = new Entry();
            $deploy_manager_entry->set_package_name($package->get_name());
            $deploy_manager_entry->set_deploy_strategy($strategy);
            $deploy_manager->add_package($deploy_manager_entry);
        }
        $deploy_manager->do_deploy();
    }
}