<?php

/**
 * @file
 * Contains Drupal\tmgmt_contentapi\Format\FormatManager.
 */
namespace Drupal\tmgmt_contentapi\Format;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * A plugin manager for file format plugins.
 */
class FormatManager extends DefaultPluginManager {

    /**
     * Array of instantiated source UI instances.
     *
     * @var array
     */
    protected $ui = array();

    protected $defaults = array(
        'ui' => '\Drupal\tmgmt\SourcePluginUiBase',
    );

    /**
     * Constructs a ConditionManager object.
     *
     * @param \Traversable $namespaces
     *   An object that implements \Traversable which contains the root paths
     *   keyed by the corresponding namespace to look for plugin implementations.
     * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
     *   Cache backend instance to use.
     * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
     *   The module handler to invoke the alter hook with.
     */
    #[\ReturnTypeWillChange]
    public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
        parent::__construct('Plugin/tmgmt_contentapi/Format', $namespaces, $module_handler, 'Drupal\tmgmt_contentapi\Format\FormatInterface', 'Drupal\tmgmt_contentapi\Annotation\FormatPlugin');
        //Comment this, not sure what happens here, probably not required
        //$this->alterInfo('tmgmt_contentapi_format_plugin_info_alter');
        //$this->setCacheBackend($cache_backend, 'contentapi_format_plugin');
    }

    /**
     * Returns a source plugin UI instance.
     *
     * @param string $plugin
     *   Name of the source plugin.
     *
     * @return \Drupal\tmgmt\SourcePluginUiInterface
     *   Instance a source plugin UI instance.
     */
    #[\ReturnTypeWillChange]
    public function createUIInstance($plugin) {
        if (!isset($this->ui[$plugin])) {
            $definition = $this->getDefinition($plugin);
            $class = $definition['ui'];
            $this->ui[$plugin] = new $class(array(), $plugin, $definition);
        }
        return $this->ui[$plugin];
    }

    /**
     * Returns an array of file format plugin labels.
     */
    #[\ReturnTypeWillChange]
    public function getLabels() {
        $labels = array();
        foreach ($this->getDefinitions() as $id => $definition) {
            $labels[$id] = $definition['label'];
        }
        return $labels;
    }

}
