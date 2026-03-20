<?php

declare (strict_types=1);
/**
 *
 *
 *
 *
 */
namespace Magento_Hackathon\Composer\Magento;

use Composer\Autoload\Autoload_Generator;
use Composer\Autoload\Class_Map_Generator;
use Composer\Composer;
use Composer\Event_Dispatcher\Event_Dispatcher;
use Composer\Event_Dispatcher\Event_Subscriber_Interface;
use Composer\Installer\Package_Events;
use Composer\IO\Io_Interface;
use Composer\Package\Package_Interface;
use Composer\Plugin\Plugin_Events;
use Composer\Plugin\Plugin_Interface;
use Composer\Script\Script_Events;
use Composer\Util\Filesystem;
use Recursive_Directory_Iterator;
use Recursive_Iterator_Iterator;
use Symfony\Component\Process\Process;
class Plugin implements Plugin_Interface, Event_Subscriber_Interface
{
    /**
     * @var IOInterface
     */
    protected $io;
    /**
     * @var ProjectConfig
     */
    protected $config;
    /**
     * @var DeployManager
     */
    protected $deploy_manager;
    /**
     * @var Composer
     */
    protected $composer;
    private ?\Magento_Hackathon\Composer\Magento\Installer $installer = null;
    /**
     * @var Filesystem
     */
    protected $filesystem;
    private string $regenerate = '/.regenerate';
    private string $var_folder = '/var';
    protected function init_deploy_manager(Composer $composer, Io_Interface $io)
    {
        $this->deploy_manager = new Deploy_Manager($io);
        $extra = $composer->get_package()->get_extra();
        $sort_priority = $extra['magento-deploy-sort-priority'] ?? [];
        $this->deploy_manager->set_sort_priority($sort_priority);
    }
    public function activate(Composer $composer, Io_Interface $io): void
    {
        $this->io = $io;
        $this->composer = $composer;
        $this->filesystem = new Filesystem();
        $this->config = new Project_Config($composer->get_package()->get_extra());
        $this->installer = new Installer($io, $composer);
        $this->init_deploy_manager($composer, $io);
        $this->installer->set_deploy_manager($this->deploy_manager);
        $this->installer->set_config($this->config);
        if ($this->io->is_debug()) {
            $this->io->write('activate magento plugin');
        }
        $composer->get_installation_manager()->add_installer($this->installer);
    }
    public static function get_subscribed_events(): array
    {
        return [Plugin_Events::COMMAND => [['onCommandEvent', 1]], Script_Events::POST_INSTALL_CMD => [['onNewCodeEvent', 1]], Script_Events::POST_UPDATE_CMD => [['onNewCodeEvent', 1]], Package_Events::POST_PACKAGE_UNINSTALL => [['onPackageUnistall', 0]]];
    }
    public function on_package_unistall(\Composer\Installer\Package_Event $event): void
    {
        $ds = DIRECTORY_SEPARATOR;
        $package = $event->get_operation()->get_package();
        [$vendor, $package_name] = explode('/', (string) $package->get_pretty_name());
        $package_name = trim(str_replace('module-', '', $package_name));
        $package_installation_path = $package_installation_path = $this->installer->get_target_dir();
        $package_path = ucfirst($vendor) . $ds . str_replace(' ', '', ucwords(str_replace('-', ' ', $package_name)));
        $this->io->write("Removing {$package_path}");
        $lib_path = 'lib' . $ds . 'internal' . $ds . $package_path;
        $magento_package_path = 'app' . $ds . 'code' . $ds . $package_path;
        $deploy_strategy = $this->installer->get_deploy_strategy($package);
        $deploy_strategy->rmdir_recursive($package_installation_path . $ds . $lib_path);
        $deploy_strategy->rmdir_recursive($package_installation_path . $ds . $magento_package_path);
        $this->request_regeneration();
    }
    /**
     * actually is triggered before anything got executed
     *
     * @param \Composer\Plugin\CommandEvent $event
     */
    public function on_command_event(\Composer\Plugin\Command_Event $event): void
    {
        $event->get_command_name();
    }
    /**
     * event listener is named this way, as it listens for events leading to changed code files
     *
     * @param \Composer\Script\Event $event
     */
    public function on_new_code_event(\Composer\Script\Event $event): void
    {
        if ($this->io->is_debug()) {
            $this->io->write('start magento deploy via deployManager');
        }
        $this->deploy_manager->do_deploy();
        $this->deploy_libraries();
        $this->save_vendor_dir_path($event->get_composer());
        $this->request_regeneration();
        $this->set_file_permissions();
    }
    /**
     * Set permissions for files using extra->chmod from composer.json
     */
    private function set_file_permissions(): void
    {
        $packages = $this->composer->get_repository_manager()->get_local_repository()->get_packages();
        $message = 'Check "chmod" section in composer.json of %s package.';
        foreach ($packages as $package) {
            $extra = $package->get_extra();
            if (!isset($extra['chmod'])) {
                continue;
            }
            if (!is_array($extra['chmod'])) {
                continue;
            }
            $error = false;
            foreach ($extra['chmod'] as $chmod) {
                if (!isset($chmod['mask']) || !isset($chmod['path']) || str_contains((string) $chmod['path'], '..')) {
                    $error = true;
                    continue;
                }
                $file = $this->installer->get_target_dir() . '/' . $chmod['path'];
                if (file_exists($file)) {
                    chmod($file, octdec((string) $chmod['mask']));
                } else {
                    $this->io->write_error(['File doesn\'t exist: ' . $chmod['path'], sprintf($message, $package->get_name())]);
                }
            }
            if ($error) {
                $this->io->write_error(['Incorrect mask or file path.', sprintf($message, $package->get_name())]);
            }
        }
    }
    protected function deploy_libraries()
    {
        $packages = $this->composer->get_repository_manager()->get_local_repository()->get_packages();
        $autoload_directories = [];
        $library_path = $this->config->get_library_path();
        if ($library_path === null) {
            if ($this->io->is_debug()) {
                $this->io->write('jump over deployLibraries as no Magento libraryPath is set');
            }
            return;
        }
        $vendor_dir = rtrim((string) $this->composer->get_config()->get('vendor-dir'), '/');
        $filesystem = $this->filesystem;
        $filesystem->remove_directory($library_path);
        $filesystem->ensure_directory_exists($library_path);
        foreach ($packages as $package) {
            /** @var PackageInterface $package */
            $package_config = $this->config->get_library_config_by_packagename($package->get_name());
            if ($package_config === null) {
                continue;
            }
            if (!isset($package_config['autoload'])) {
                $package_config['autoload'] = ['/'];
            }
            foreach ($package_config['autoload'] as $path) {
                $autoload_directories[] = $library_path . '/' . $package->get_name() . '/' . $path;
            }
            if ($this->io->is_debug()) {
                $this->io->write('Magento deployLibraries executed for ' . $package->get_name());
            }
            $library_target_path = $library_path . '/' . $package->get_name();
            $filesystem->remove_directory($library_target_path);
            $filesystem->ensure_directory_exists($library_target_path);
            $this->copy_recursive($vendor_dir . '/' . $package->get_pretty_name(), $library_target_path);
        }
        new Autoload_Generator(new Event_Dispatcher($this->composer, $this->io));
        Class_Map_Generator::create_map($library_path);
        $executable = $this->composer->get_config()->get('bin-dir') . '/phpab';
        if (!file_exists($executable)) {
            $executable = $this->composer->get_config()->get('vendor-dir') . '/theseer/autoload/composer/bin/phpab';
        }
        if (file_exists($executable)) {
            if ($this->io->is_debug()) {
                $this->io->write('Magento deployLibraries executes autoload generator');
            }
            $process = new Process($executable . " -o {$library_path}/autoload.php  " . implode(' ', $autoload_directories));
            $process->run();
        } else if ($this->io->is_debug()) {
            $this->io->write('Magento deployLibraries autoload generator not availabel, you should require "theseer/autoload"');
            var_dump($executable, getcwd());
        }
    }
    /**
     * Copy then delete is a non-atomic version of {@link rename}.
     *
     * Some systems can't rename and also don't have proc_open,
     * which requires this solution.
     *
     * copied from \Composer\Util\Filesystem::copyThenRemove and removed the remove part
     *
     * @param string $source
     */
    protected function copy_recursive($source, string $target)
    {
        $it = new Recursive_Directory_Iterator($source, Recursive_Directory_Iterator::SKIP_DOTS);
        $ri = new Recursive_Iterator_Iterator($it, Recursive_Iterator_Iterator::SELF_FIRST);
        $this->filesystem->ensure_directory_exists($target);
        foreach ($ri as $file) {
            $target_path = $target . DIRECTORY_SEPARATOR . $ri->get_sub_path_name();
            if ($file->is_dir()) {
                $this->filesystem->ensure_directory_exists($target_path);
            } else {
                copy($file->get_pathname(), $target_path);
            }
        }
    }
    /**
     * Generate file with path to Composer 'vendor' dir to be used by the application
     *
     * @param \Composer\Composer $composer
     * @throws \UnexpectedValueException
     */
    private function save_vendor_dir_path(Composer $composer): void
    {
        $magento_dir = $this->installer->get_target_dir();
        $vendor_dir_path = $this->filesystem->find_shortest_path($magento_dir, realpath($composer->get_config()->get('vendor-dir')), true);
        $vendor_path_file = $magento_dir . '/app/etc/vendor_path.php';
        $content = <<<AUTOLOAD
        <?php
        /**
         * Path to Composer vendor directory
         */
        return '{$vendor_dir_path}';
        
        AUTOLOAD;
        file_put_contents($vendor_path_file, $content);
    }
    /**
     * Force regeneration of var/di, var/cache, var/generation on next object manager invocation
     */
    private function request_regeneration(): void
    {
        if (is_writable($this->installer->get_target_dir() . $this->var_folder)) {
            $filename = $this->installer->get_target_dir() . $this->var_folder . $this->regenerate;
            touch($filename);
        }
    }
    /**
     * @inheritdoc
     */
    public function deactivate(Composer $composer, Io_Interface $io)
    {
    }
    /**
     * @inheritdoc
     */
    public function uninstall(Composer $composer, Io_Interface $io)
    {
    }
}