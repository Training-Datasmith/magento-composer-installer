# Architecture: magento-composer-installer

## Purpose

A Composer plugin for Magento 1 (and early Magento 2) that deploys extension files from `vendor/` into the correct Magento directory structure. Supports `modman`, `package.xml`, and `map` file deployment descriptors, with three strategies: copy, symlink, and hardlink.

## Directory Structure

```
src/MagentoHackathon/Composer/Magento/
  Plugin.php                      — Composer plugin entry point: registers the installer
  Installer.php                   — Composer installer: handles install/update/uninstall events
  Deploy_Manager.php              — Orchestrates deployment: collects entries, calls strategy
  Project_Config.php              — Reads Magento root path and deployment preferences from composer.json
  Package_Types.php               — Enum-like constants for Magento package types
  Parser.php                      — Base parser interface
  Modman_Parser.php               — Parses `modman` deployment map files
  Map_Parser.php                  — Parses explicit `map` deployment descriptors
  Package_Xml_Parser.php          — Parses Magento `package.xml` (Connect format)
  Path_Translation_Parser.php     — Translates relative paths in deployment maps
  Deploystrategy/
    Deploystrategy_Abstract.php   — Base deployment strategy: resolves glob patterns, validates paths
    Copy.php                      — Copies files from vendor to Magento root
    Symlink.php                   — Creates symlinks (preferred for development)
    Link.php                      — Creates hardlinks
    None.php                      — No-op strategy (skip deployment)
  Deploy/Manager/Entry.php        — Value object representing one source→destination mapping
  Command/Deploy_Command.php      — Magerun/CLI command for manual re-deployment
```

## Key Design Decisions

- **Strategy pattern for deployment**: `DeployStrategyAbstract` defines the algorithm; `Copy`, `Symlink`, and `Link` differ only in how they create the destination file
- **Multiple deployment descriptor formats**: Modman files, `package.xml`, and inline `map` entries in `composer.json` are all parsed to the same `Entry[]` structure, then processed uniformly
- **Glob expansion**: Source paths in map files may include glob patterns; the strategy expands them before deploying

## Extension Points

- Add a new deployment strategy by extending `Deploystrategy_Abstract` and registering it in `Installer`
- Add a new deployment descriptor format by implementing `Parser`

## Dependency Flow

```
Composer event (install/update)
  → Plugin → Installer
  → Project_Config (reads composer.json)
  → Parser (Modman | Map | PackageXml) → Entry[]
  → Deploy_Manager
  → DeployStrategy (Copy | Symlink | Link) → filesystem
```
