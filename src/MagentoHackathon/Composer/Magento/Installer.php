<?php

declare (strict_types=1);
/**
 * Composer Magento Installer
 */
namespace Magento_Hackathon\Composer\Magento;

use Composer\Composer;
use Composer\Installer\Installer_Interface;
use Composer\Installer\Library_Installer;
use Composer\IO\Io_Interface;
use Composer\Package\Package_Interface;
use Composer\Repository\Installed_Repository_Interface;
use Magento_Hackathon\Composer\Magento\Deploy\Manager\Entry;
use React\Promise\Promise_Interface;
/**
 * Composer Magento Installer
 */
class Installer extends Library_Installer implements Installer_Interface
{
    /**
     * The base directory of the magento installation
     *
     * @var \SplFileInfo
     */
    protected $magento_root_dir;
    /**
     * The default base directory of the magento installation
     *
     * @var \SplFileInfo
     */
    protected $default_magento_root_dir = './';
    /**
     * The base directory of the modman packages
     *
     * @var \SplFileInfo
     */
    protected $modman_root_dir;
    /**
     * If set overrides existing files
     *
     * @var bool
     */
    protected $is_forced = false;
    /**
     * The module's base directory
     *
     * @var string
     */
    protected $_source_dir;
    protected string $_deploy_strategy = 'copy';
    public const MAGENTO_REMOVE_DEV_FLAG = 'magento-remove-dev';
    public const MAGENTO_MAINTANANCE_FLAG = 'maintenance.flag';
    public const MAGENTO_CACHE_PATH = 'var/cache';
    public const MAGENTO_ROOT_DIR_TMP_SUFFIX = '_tmp';
    public const MAGENTO_ROOT_DIR_BACKUP_SUFFIX = '_bkup';
    protected $no_maintenance_mode = false;
    protected $original_magento_root_dir;
    protected $backup_magento_root_dir;
    protected $remove_magento_dev = false;
    protected $keep_magento_cache = false;
    protected $_magento_local_xml_path = 'app/etc/local.xml';
    protected $_default_env_file_paths = ['app/etc/local.xml'];
    protected $_magento_dev_dir = 'dev';
    protected $_magento_writable_dirs = ['app/etc', 'media', 'var'];
    /**
     * @var DeployManager
     */
    protected $deploy_manager;
    /**
     * @var ProjectConfig
     */
    protected $config;
    /**
     * If set the deployed files will be added to the projects .gitignore file
     *
     * @var bool
     */
    protected $append_git_ignore = false;
    /**
     * @var array Path mapping prefixes that need to be translated (i.e. to
     * use a public directory as the web server root).
     */
    protected array $_path_mapping_translations = [];
    /**
     * Initializes Magento Module installer
     *
     * @param \Composer\IO\IOInterface $io
     * @param \Composer\Composer $composer
     * @param string $type
     * @throws \ErrorException
     */
    public function __construct(Io_Interface $io, Composer $composer, $type = 'magento-module')
    {
        parent::__construct($io, $composer, $type);
        $this->initialize_vendor_dir();
        $this->annoy($io);
        $extra = $composer->get_package()->get_extra();
        if (isset($extra['magento-root-dir']) || $root_dir_input = $this->default_magento_root_dir) {
            if (isset($root_dir_input)) {
                $extra['magento-root-dir'] = $root_dir_input;
            }
            $dir = rtrim(trim((string) $extra['magento-root-dir']), '/\\');
            $this->magento_root_dir = new \Spl_File_Info($dir);
            if (!is_dir($dir) && $io->ask_confirmation('magento root dir "' . $dir . '" missing! create now? [Y,n] ')) {
                $this->initialize_magento_root_dir();
                $io->write('magento root dir "' . $dir . '" created');
            }
            if (!is_dir($dir)) {
                $dir = $this->vendor_dir . "/{$dir}";
                $this->magento_root_dir = new \Spl_File_Info($dir);
            }
        }
        if (isset($extra['modman-root-dir'])) {
            $dir = rtrim(trim($extra['modman-root-dir']), '/\\');
            if (!is_dir($dir)) {
                $dir = $this->vendor_dir . "/{$dir}";
            }
            if (!is_dir($dir)) {
                throw new \ErrorException("modman root dir \"{$dir}\" is not valid");
            }
            $this->modman_root_dir = new \Spl_File_Info($dir);
        }
        if (isset($extra['magento-deploystrategy'])) {
            $this->_deploy_strategy = (string) $extra['magento-deploystrategy'];
            if ($this->_deploy_strategy !== 'copy') {
                $io->write("<warning>Warning: Magento 2 is not tested with \"{$this->_deploy_strategy}\" deployment strategy. It may not function properly.</warning>");
            }
        }
        if (($this->magento_root_dir === null || false === $this->magento_root_dir->is_dir()) && $this->_deploy_strategy != 'none') {
            $dir = $this->magento_root_dir instanceof \Spl_File_Info ? $this->magento_root_dir->get_pathname() : '';
            $io->write("<error>magento root dir \"{$dir}\" is not valid</error>", true);
            $io->write('<comment>You need to set an existing path for "magento-root-dir" in your composer.json</comment>', true);
            $io->write('<comment>For more information please read about the "Usage" in the README of the installer Package</comment>', true);
            throw new \ErrorException("magento root dir \"{$dir}\" is not valid");
        }
        if (isset($extra['magento-force'])) {
            $this->is_forced = (bool) $extra['magento-force'];
        }
        if (false !== getenv('MAGENTO_CLOUD_PROJECT')) {
            $this->set_deploy_strategy('none');
        }
        if (isset($extra['magento-deploystrategy'])) {
            $this->set_deploy_strategy((string) $extra['magento-deploystrategy']);
        }
        if (!empty($extra['auto-append-gitignore'])) {
            $this->append_git_ignore = true;
        }
        if (!empty($extra['path-mapping-translations'])) {
            $this->_path_mapping_translations = (array) $extra['path-mapping-translations'];
        }
    }
    public function set_deploy_manager(Deploy_Manager $deploy_manager): void
    {
        $this->deploy_manager = $deploy_manager;
    }
    public function set_config(Project_Config $config): void
    {
        $this->config = $config;
    }
    /**
     * @return DeployManager
     */
    public function get_deploy_manager()
    {
        return $this->deploy_manager;
    }
    /**
     * Create base requrements for project installation
     */
    protected function initialize_magento_root_dir()
    {
        if (!$this->magento_root_dir->is_dir()) {
            $magento_root_path = $this->magento_root_dir->get_pathname();
            $path_parts = explode(DIRECTORY_SEPARATOR, $magento_root_path);
            $base_dir = explode(DIRECTORY_SEPARATOR, $this->vendor_dir);
            array_pop($base_dir);
            $path_parts = array_merge($base_dir, $path_parts);
            $directory_path = '';
            foreach ($path_parts as $path_part) {
                $directory_path .= $path_part . DIRECTORY_SEPARATOR;
                $this->filesystem->ensure_directory_exists($directory_path);
            }
        }
        // $this->getSourceDir($package);
    }
    /**
     * @param string $strategy
     */
    public function set_deploy_strategy($strategy): void
    {
        $this->_deploy_strategy = $strategy;
    }
    /**
     * Returns the strategy class used for deployment
     *
     * @param \Composer\Package\PackageInterface $package
     * @param string $strategy
     * @return \MagentoHackathon\Composer\Magento\Deploystrategy\DeploystrategyAbstract
     */
    public function get_deploy_strategy(Package_Interface $package, $strategy = null)
    {
        if (null === $strategy) {
            $strategy = $this->_deploy_strategy;
        }
        $extra = $this->composer->get_package()->get_extra();
        if (isset($extra['magento-deploystrategy-overwrite'])) {
            $module_specific_deploy_strategys = $this->transform_array_keys_to_lower_case($extra['magento-deploystrategy-overwrite']);
            if (isset($module_specific_deploy_strategys[$package->get_name()])) {
                $strategy = $module_specific_deploy_strategys[$package->get_name()];
            }
        }
        $module_specific_deploy_ignores = [];
        if (isset($extra['magento-deploy-ignore'])) {
            $extra['magento-deploy-ignore'] = $this->transform_array_keys_to_lower_case($extra['magento-deploy-ignore']);
            if (isset($extra['magento-deploy-ignore']['*'])) {
                $module_specific_deploy_ignores = $extra['magento-deploy-ignore']['*'];
            }
            if (isset($extra['magento-deploy-ignore'][$package->get_name()])) {
                $module_specific_deploy_ignores = array_merge($module_specific_deploy_ignores, $extra['magento-deploy-ignore'][$package->get_name()]);
            }
        }
        if ($package->get_type() === 'magento-core') {
            $strategy = 'copy';
        }
        $target_dir = $this->get_target_dir();
        $source_dir = $this->get_source_dir($package);
        $impl = match ($strategy) {
            'symlink' => new \Magento_Hackathon\Composer\Magento\Deploystrategy\Symlink($source_dir, $target_dir),
            'link' => new \Magento_Hackathon\Composer\Magento\Deploystrategy\Link($source_dir, $target_dir),
            'none' => new \Magento_Hackathon\Composer\Magento\Deploystrategy\None($source_dir, $target_dir),
            default => new \Magento_Hackathon\Composer\Magento\Deploystrategy\Copy($source_dir, $target_dir),
        };
        // Inject isForced setting from extra config
        $impl->set_is_forced($this->is_forced);
        $impl->set_ignored_mappings($module_specific_deploy_ignores);
        return $impl;
    }
    /**
     * Decides if the installer supports the given type
     *
     * @param  string $packageType
     * @return bool
     */
    public function supports($package_type)
    {
        return array_key_exists($package_type, Package_Types::$package_types);
    }
    /**
     * Return Source dir of package
     *
     * @param \Composer\Package\PackageInterface $package
     * @return string
     */
    protected function get_source_dir(Package_Interface $package)
    {
        $this->filesystem->ensure_directory_exists($this->vendor_dir);
        return $this->get_install_path($package);
    }
    /**
     * Return the absolute target directory path for package installation
     *
     * @return string
     */
    public function get_target_dir()
    {
        return realpath($this->magento_root_dir->get_pathname());
    }
    /**
     * @inheritdoc
     */
    public function install(Installed_Repository_Interface $repo, Package_Interface $package)
    {
        if ($package->get_type() === 'magento-core' && !$this->pre_install_magento_core()) {
            return;
        }
        $after_install = function () use ($package): void {
            // skip marshal and apply default behavior if extra->map does not exist
            if ($this->has_extra_map($package)) {
                $strategy = $this->get_deploy_strategy($package);
                $strategy->set_mappings($this->get_parser($package)->get_mappings());
                $deploy_manager_entry = new Entry();
                $deploy_manager_entry->set_package_name($package->get_name());
                $deploy_manager_entry->set_deploy_strategy($strategy);
                $this->deploy_manager->add_package($deploy_manager_entry);
                if ($this->append_git_ignore) {
                    $this->append_git_ignore($package, $this->get_git_ignore_file_location());
                }
            }
        };
        $promise = parent::install($repo, $package);
        // Composer v2 might return a promise here
        if ($promise instanceof Promise_Interface) {
            return $promise->then($after_install);
        }
        // If not, execute the code right away as parent::install executed synchronously (composer v1, or v2 without async)
        $after_install();
    }
    /**
     * Get .gitignore file location
     *
     * @return string
     */
    public function get_git_ignore_file_location()
    {
        return $this->magento_root_dir->get_pathname() . '/.gitignore';
    }
    /**
     * Add all the files which are to be deployed
     * to the .gitignore file, if it doesn't
     * exist then create a new one
     *
     * @param string $ignoreFile
     */
    public function append_git_ignore(Package_Interface $package, $ignore_file): void
    {
        $contents = [];
        if (file_exists($ignore_file)) {
            $contents = file($ignore_file, FILE_IGNORE_NEW_LINES);
        }
        $additions = [];
        foreach ($this->get_parser($package)->get_mappings() as $map) {
            $dest = $map[1];
            $ignore = sprintf('/%s', $dest);
            $ignore = str_replace('/./', '/', $ignore);
            $ignore = str_replace('//', '/', $ignore);
            $ignore = rtrim($ignore, '/');
            if (!in_array($ignore, $contents)) {
                $ignored_mappings = $this->get_deploy_strategy($package)->get_ignored_mappings();
                if (in_array($ignore, $ignored_mappings)) {
                    continue;
                }
                $additions[] = $ignore;
            }
        }
        if (!empty($additions)) {
            array_unshift($additions, '#' . $package->get_name());
            $contents = array_merge($contents, $additions);
            file_put_contents($ignore_file, implode("\n", $contents));
        }
        if ($package->get_type() === 'magento-core') {
            $this->prepare_magento_core();
        }
    }
    /**
     * Install Magento core
     *
     * @param InstalledRepositoryInterface $repo repository in which to check
     * @param PackageInterface $package package instance
     */
    protected function pre_install_magento_core()
    {
        if (!$this->io->ask_confirmation('<info>Are you sure you want to install the Magento core?</info><error>Attention: Your Magento root dir will be cleared in the process!</error> [<comment>Y,n</comment>] ', true)) {
            $this->io->write('Skipping core installation...');
            return false;
        }
        $this->clear_root_dir();
        return true;
    }
    protected function clear_root_dir()
    {
        $this->filesystem->remove_directory($this->magento_root_dir->get_pathname());
        $this->filesystem->ensure_directory_exists($this->magento_root_dir->get_pathname());
    }
    public function prepare_magento_core(): void
    {
        $this->set_magento_permissions();
        $this->redeploy_project();
    }
    /**
     * some directories have to be writable for the server
     */
    protected function set_magento_permissions()
    {
        foreach ($this->_magento_writable_dirs as $dir) {
            if (!file_exists($this->get_target_dir() . DIRECTORY_SEPARATOR . $dir)) {
                mkdir($this->get_target_dir() . DIRECTORY_SEPARATOR . $dir, 0777, true);
            }
            $this->set_permissions($this->get_target_dir() . DIRECTORY_SEPARATOR . $dir, 0777, 0666);
        }
    }
    /**
     * set permissions recursively
     *
     * @param string $path Path to set permissions for
     * @param int $dirmode Permissions to be set for directories
     * @param int $filemode Permissions to be set for files
     */
    protected function set_permissions($path, $dirmode, $filemode)
    {
        if (is_dir($path)) {
            if (!@chmod($path, $dirmode)) {
                $this->io->write('Failed to set permissions "%s" for directory "%s"', decoct($dirmode), $path);
            }
            $dh = opendir($path);
            while (($file = readdir($dh)) !== false) {
                if ($file != '.' && $file != '..') {
                    // skip self and parent pointing directories
                    $fullpath = $path . '/' . $file;
                    $this->set_permissions($fullpath, $dirmode, $filemode);
                }
            }
            closedir($dh);
        } elseif (is_file($path)) {
            if (false == !@chmod($path, $filemode)) {
                $this->io->write('Failed to set permissions "%s" for file "%s"', decoct($filemode), $path);
            }
        }
    }
    protected function redeploy_project()
    {
        $io_interface = $this->io;
        // init repos
        $composer = $this->composer;
        $installed_repo = $composer->get_repository_manager()->get_local_repository();
        $composer->get_download_manager();
        $im = $composer->get_installation_manager();
        /*
         * @var $moduleInstaller MagentoHackathon\Composer\Magento\Installer
         */
        $module_installer = $im->get_installer('magento-module');
        foreach ($installed_repo->get_packages() as $package) {
            if ($io_interface->is_verbose()) {
                $io_interface->write($package->get_name());
                $io_interface->write($package->get_type());
            }
            if ($package->get_type() != 'magento-module') {
                continue;
            }
            if ($io_interface->is_verbose()) {
                $io_interface->write("package {$package->get_name()} recognized");
            }
            $strategy = $module_installer->get_deploy_strategy($package);
            if ($io_interface->get_option('verbose')) {
                $io_interface->write('used ' . $strategy::class . ' as deploy strategy');
            }
            $strategy->set_mappings($module_installer->get_parser($package)->get_mappings());
            $strategy->deploy();
        }
    }
    /**
     * @inheritdoc
     */
    public function update(Installed_Repository_Interface $repo, Package_Interface $initial, Package_Interface $target)
    {
        if ($target->get_type() === 'magento-core' && !$this->pre_update_magento_core()) {
            return;
        }
        // cleanup marshaled files if extra->map exist
        if ($this->has_extra_map($initial)) {
            $initial_strategy = $this->get_deploy_strategy($initial);
            $initial_strategy->set_mappings($this->get_parser($initial)->get_mappings());
            try {
                $initial_strategy->clean();
            } catch (\ErrorException $e) {
                if ($this->io->is_debug()) {
                    $this->io->write($e->get_message());
                }
            }
        }
        $after_update = function () use ($target): void {
            // marshal files for new package version if extra->map exist
            if ($this->has_extra_map($target)) {
                $target_strategy = $this->get_deploy_strategy($target);
                $target_strategy->set_mappings($this->get_parser($target)->get_mappings());
                $deploy_manager_entry = new Entry();
                $deploy_manager_entry->set_package_name($target->get_name());
                $deploy_manager_entry->set_deploy_strategy($target_strategy);
                $this->deploy_manager->add_package($deploy_manager_entry);
            }
            if ($this->append_git_ignore) {
                $this->append_git_ignore($target, $this->get_git_ignore_file_location());
            }
            if ($target->get_type() === 'magento-core') {
                $this->post_update_magento_core();
            }
        };
        $promise = parent::update($repo, $initial, $target);
        // Composer v2 might return a promise here
        if ($promise instanceof Promise_Interface) {
            return $promise->then($after_update);
        }
        // If not, execute the code right away as parent::update executed synchronously (composer v1, or v2 without async)
        $after_update();
    }
    protected function pre_update_magento_core()
    {
        if (!$this->io->ask_confirmation('<info>Are you sure you want to manipulate the Magento core installation</info> [<comment>Y,n</comment>]? ', true)) {
            $this->io->write('Skipping core update...');
            return false;
        }
        $tmp_dir = $this->magento_root_dir->get_pathname() . self::MAGENTO_ROOT_DIR_TMP_SUFFIX;
        $this->filesystem->ensure_directory_exists($tmp_dir);
        $this->original_magento_root_dir = clone $this->magento_root_dir;
        $this->magento_root_dir = new \Spl_File_Info($tmp_dir);
        return true;
    }
    protected function post_update_magento_core()
    {
        $tmp_dir = $this->magento_root_dir->get_pathname();
        $backup_dir = $this->original_magento_root_dir->get_pathname() . self::MAGENTO_ROOT_DIR_BACKUP_SUFFIX;
        $this->backup_magento_root_dir = new \Spl_File_Info($backup_dir);
        $orig_root_dir = $this->original_magento_root_dir->get_path_name();
        $this->filesystem->rename($orig_root_dir, $backup_dir);
        $this->filesystem->rename($tmp_dir, $orig_root_dir);
        $this->magento_root_dir = clone $this->original_magento_root_dir;
        $this->prepare_magento_core();
        $this->cleanup_post_update_magento_core();
    }
    protected function cleanup_post_update_magento_core()
    {
        $root_dir = $this->magento_root_dir->get_pathname();
        $backup_dir = $this->backup_magento_root_dir->get_pathname();
        $persistent_folders = ['media', 'var'];
        copy($backup_dir . DIRECTORY_SEPARATOR . $this->_magento_local_xml_path, $root_dir . DIRECTORY_SEPARATOR . $this->_magento_local_xml_path);
        foreach ($persistent_folders as $folder) {
            $this->filesystem->remove_directory($root_dir . DIRECTORY_SEPARATOR . $folder);
            $this->filesystem->rename($backup_dir . DIRECTORY_SEPARATOR . $folder, $root_dir . DIRECTORY_SEPARATOR . $folder);
        }
        if ($this->io->ask('Remove root backup? [Y,n] ', true)) {
            $this->filesystem->remove_directory($backup_dir);
            $this->io->write('Removed root backup!', true);
        } else {
            $this->io->write('Skipping backup removal...', true);
        }
        $this->clear_magento_cache();
    }
    public function toggle_magento_maintenance_mode($active = false): void
    {
        if (($target_dir = $this->get_target_dir()) && !$this->no_maintenance_mode) {
            $flag_path = $target_dir . DIRECTORY_SEPARATOR . self::MAGENTO_MAINTANANCE_FLAG;
            if ($active) {
                $this->io->write('Adding magento maintenance flag...');
                file_put_contents($flag_path, '*');
            } elseif (file_exists($flag_path)) {
                $this->io->write('Removing magento maintenance flag...');
                unlink($flag_path);
            }
        }
    }
    public function clear_magento_cache(): void
    {
        if (($target_dir = $this->get_target_dir()) && !$this->keep_magento_cache) {
            $magento_cache_path = $target_dir . DIRECTORY_SEPARATOR . self::MAGENTO_CACHE_PATH;
            if ($this->filesystem->remove_directory($magento_cache_path)) {
                $this->io->write('Magento cache cleared');
            }
        }
    }
    /**
     * @inheritdoc
     */
    public function uninstall(Installed_Repository_Interface $repo, Package_Interface $package)
    {
        // skip marshal and apply default behavior if extra->map does not exist
        if ($this->has_extra_map($package)) {
            $strategy = $this->get_deploy_strategy($package);
            $strategy->set_mappings($this->get_parser($package)->get_mappings());
            try {
                $strategy->clean();
            } catch (\ErrorException $e) {
                if ($this->io->is_debug()) {
                    $this->io->write($e->get_message());
                }
            }
        }
        return parent::uninstall($repo, $package);
    }
    /**
     * Returns the modman parser for the vendor dir
     *
     * @return Parser
     * @throws \ErrorException
     */
    public function get_parser(Package_Interface $package)
    {
        $extra = $package->get_extra();
        $module_specific_map = $this->composer->get_package()->get_extra();
        if (isset($module_specific_map['magento-map-overwrite'])) {
            $module_specific_map = $this->transform_array_keys_to_lower_case($module_specific_map['magento-map-overwrite']);
            if (isset($module_specific_map[$package->get_name()])) {
                $map = $module_specific_map[$package->get_name()];
            }
        }
        $suffix = $package->get_type() ? Package_Types::$package_types[$package->get_type()] : '';
        if (isset($map)) {
            return new Map_Parser($map, $this->_path_mapping_translations, $suffix);
        }
        if (isset($extra['map'])) {
            return new Map_Parser($extra['map'], $this->_path_mapping_translations, $suffix);
        }
        if (isset($extra['package-xml'])) {
            return new Package_Xml_Parser($this->get_source_dir($package), $extra['package-xml'], $this->_path_mapping_translations, $suffix);
        }
        if (file_exists($this->get_source_dir($package) . '/modman')) {
            return new Modman_Parser($this->get_source_dir($package), $this->_path_mapping_translations, $suffix);
        }
        throw new \ErrorException('Unable to find deploy strategy for module: no known mapping');
    }
    /**
     * {@inheritDoc}
     */
    public function get_install_path(Package_Interface $package)
    {
        if ($this->modman_root_dir !== null && true === $this->modman_root_dir->is_dir()) {
            $target_dir = $package->get_target_dir();
            if (!$target_dir) {
                [$vendor, $target_dir] = explode('/', $package->get_pretty_name());
            }
            $install_path = $this->modman_root_dir . '/' . $target_dir;
        } else {
            $install_path = parent::get_install_path($package);
        }
        // Make install path absolute. This is needed in the symlink deploy strategies.
        if (DIRECTORY_SEPARATOR !== $install_path[0] && $install_path[1] !== ':') {
            return getcwd() . "/{$install_path}";
        }
        return $install_path;
    }
    public function transform_array_keys_to_lower_case($array)
    {
        $array_new = [];
        foreach ($array as $key => $value) {
            $array_new[strtolower((string) $key)] = $value;
        }
        return $array_new;
    }
    /**
     * this function is for annoying people with messages.
     *
     * First usage: get people to vote about the future release of composer so later I can say "you wanted it this way"
     */
    public function annoy(Io_Interface $io): void
    {
        /**
         * No <error> in future, as some people look for error lines inside of CI Applications, which annoys them
         */
        /*
                $io->write('<comment> time for voting about the future of the #magento #composer installer. </comment>', true);
                $io->write('<comment> https://github.com/magento-hackathon/magento-composer-installer/blob/discussion-master/Milestone/2/index.md </comment>', true);
                $io->write('<error> For the case you don\'t vote, I will ignore your problems till iam finished with the resulting release. </error>', true);
                 *
                 **/
    }
    /**
     * Checks if package has extra map value set
     */
    private function has_extra_map(Package_Interface $package): bool
    {
        $package_extra = $package->get_extra();
        if (isset($package_extra['map'])) {
            return true;
        }
        return false;
    }
}