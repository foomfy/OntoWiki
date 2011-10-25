<?php

/**
 * will be used by OntoWiki to scan the extension folder and load the needed extension
 *
 * @author Jonas Brekle <jonas.brekle@gmail.com>
 */
class Ontowiki_Extension_Manager
{
    const DEFAULT_CONFIG_FILE = 'default.ini';
    const EXTENSION_DEFAULT_DOAP_FILE = 'doap.n3';
    
    const COMPONENT_HELPER_SUFFIX = 'Helper';
    const COMPONENT_HELPER_FILE_SUFFIX = 'Helper.php';
    
    const COMPONENT_CLASS_POSTFIX = 'Controller';
    const COMPONENT_FILE_POSTFIX = 'Controller.php';

    const PLUGIN_CLASS_POSTFIX = 'Plugin';
    const PLUGIN_FILE_POSTFIX = 'Plugin.php';

    const WRAPPER_CLASS_POSTFIX = 'Wrapper';
    const WRAPPER_FILE_POSTFIX = 'Wrapper.php';


    /**
     * Array where component information is kept
     * @var array
     */
    protected $_extensionRegistry = array();

    /**
     * The path scanned for components
     * @var string
     */
    protected $_extensionPath = null;

    /**
     * The translation object.
     * @var Zend_Translate
     */
    protected $_translate = null;

    /**
     * Base URL for hyperlinks.
     * @var string
     */
    protected $_componentUrlBase = '';

    /**
     * Component helpers to be initialized
     * @var array
     */
    protected $_helpers = array();

    protected $_componentRegistry = array();

    protected $_moduleRegistry = null;

    /**
     * Denotes whether component helpers have been called.
     * @var boolean
     */
    protected $_helpersCalled = false;

    /**
     * Prefix to distinguish component controller directories
     * from other controller directories.
     * @var string
     */
    private $_componentPrefix = '_component_';

    /**
     * Keys in the component configuration file storing path names that
     * should be normalized.
     * @var array
     */
    private $_pathKeys = array(
        'templates',
        'languages',
        'helpers'
    );

    /**
     * Name of the private section in the component config file
     * @var string
     */
    private $_privateSection = 'private';

    protected $_eventDispatcher = null;


    /**
     * Constructor
     */
    public function __construct($extensionPath)
    {
        if (!(substr($extensionPath, -1) == DIRECTORY_SEPARATOR)) {
            $extensionPath .= DIRECTORY_SEPARATOR;
        }
        $this->_extensionPath = $extensionPath;
        
        OntoWiki_Module_Registry::reset();
        //OntoWiki_Module_Registry::getInstance()->resetInstance();
        $this->_moduleRegistry = OntoWiki_Module_Registry::getInstance();
        $this->_moduleRegistry->setExtensionPath($extensionPath);

        //TODO nessesary?
        Erfurt_Wrapper_Registry::reset();


        $this->_eventDispatcher = Erfurt_Event_Dispatcher::getInstance();

        // scan for extensions
        $this->_scanExtensionPath();

        // scan for translations
        $this->_scanTranslations();

        // register for event
        $dispatcher = Erfurt_Event_Dispatcher::getInstance();
        $dispatcher->register('onRouteShutdown', $this);
    }

    // ------------------------------------------------------------------------
    // --- Public Methods -----------------------------------------------------
    // ------------------------------------------------------------------------

    /**
     * Returns component.
     *
     * @return array
     */
    public function getExtensionConfig($name)
    {
        if (isset($this->_extensionRegistry[$name])) {
            return $this->_extensionRegistry[$name];
        }
    }
    /**
     * Returns registered extensions.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->_extensionRegistry;
    }

     /**
     * Returns registered components.
     *
     * @return array
     */
    public function getComponents()
    {
        return $this->_componentRegistry;
    }


    /**
     * Returns the helper associated with the component specified.
     *
     * @throws OntoWiki_Component_Exception if no component with the specified name has been registered or
     *         OntoWiki_Component_Exception if the specified component has no helper defined.
     * @return OntoWiki_Component_Helper
     */
    public function getComponentHelper($componentName)
    {
        if (!$this->isExtensionRegistered($componentName)) {
            throw new OntoWiki_Component_Exception('Component with key "'.$componentName.'" not registered');
        }

        if (!isset($this->_helpers[$componentName]['instance'])) {
            throw new OntoWiki_Component_Exception('no helper loaded for component "'.$componentName.'"');
        }

        return $this->_helpers[$componentName]['instance'];
    }

    /**
     * Returns the path the component manager used to search for components
     * because there is one component per extension, this path is equal to the extension path.
     *
     * @return string
     */
    public function getComponentPath()
    {
        return $this->getExtensionPath();
    }


    /**
     * Returns the path the extension manager used to search for extensions.
     *
     * @return string
     */
    public function getExtensionPath($name = null)
    {   
        if ($name == null) {
            return $this->_extensionPath;
        } else {
            return $this->_extensionPath . $name;
        }
    }

    /**
     * Returns the specified component's URL.
     *
     * @throws OntoWiki_Component_Exception if no component with the specified name has been registered
     * @return string
     */
    public function getComponentUrl($componentName)
    {
        if (!$this->isExtensionRegistered($componentName)) {
            throw new OntoWiki_Component_Exception("Component with key '$componentName' not registered");
        }

        return $this->_componentUrlBase . $componentName . '/';
    }

    /**
     * Checks whether a specific extension is registered.
     *
     *
     * @param  string $componentName
     * @return boolean
     */
    public function isExtensionRegistered($componentName)
    {
        return array_key_exists($componentName, $this->_extensionRegistry);
    }


    /**
     * Checks whether a specific component is registered.
     * 
     * @deprecated
     *
     * @param  string $componentName
     * @return boolean
     */
    public function isComponentRegistered($componentName)
    {
        return array_key_exists($componentName, $this->_componentRegistry);
    }

    /**
     * Checks whether a specific component is activated
     * in its configuration file.
     *
     * @param  string $componentName
     * @return boolean
     */
    public function isExtensionActive($componentName)
    {
        return array_key_exists($componentName, $this->_extensionRegistry) && 
            $this->_extensionRegistry[$componentName]->enabled;
    }

    /**
     * Returns a prefix that can be used to distinguish components from
     * other extensions, i.e. modules or plugins.
     * 
     * @deprecated
     *
     * @return string
     */
    public function getComponentPrefix()
    {
        return $this->_componentPrefix;
    }

    /**
     * Returns the helper path for a given component.
     *
     * @param  string $componentName
     * @return string
     */
    public function getComponentHelperPath($componentName)
    {
        if (!$this->isExtensionRegistered($componentName)) {
            throw new OntoWiki_Component_Exception("Component with key '$componentName' not registered");
        }

        if (isset($this->_extensionRegistry[$componentName]->helpers)) {
            $path = $this->_extensionPath
                  . $componentName
                  . DIRECTORY_SEPARATOR
                  . $this->_extensionRegistry[$componentName]->helpers;

            return $path;
        }
    }

    /**
     * Returns the template path for a given component.
     *
     * @param  string $componentName
     * @return string
     */
    public function getComponentTemplatePath($componentName)
    {
        if (!$this->isExtensionRegistered($componentName)) {
            throw new OntoWiki_Component_Exception("Component with key '$componentName' not registered");
        }

        if (isset($this->_extensionRegistry[$componentName]->templates)) {
            $path = $this->_extensionPath
                  . $componentName
                  . DIRECTORY_SEPARATOR
                  . $this->_extensionRegistry[$componentName]->templates;

            return $path;
        }

        return $this->_extensionPath
                  . $componentName
                  . DIRECTORY_SEPARATOR;
    }

    /**
     * Returns the component's private configuration section
     *
     * @param  string $extensionName
     * @return array|null
     */
    public function getPrivateConfig($extensionName)
    {
        if (!$this->isExtensionRegistered($extensionName)) {
            throw new OntoWiki_Component_Exception("Component with key '$extensionName' not registered");
        }
        
        return $this->_extensionRegistry[$extensionName]->{$this->_privateSection};
    }

    /**
     * Sets the base URL for hyperlinks.
     *
     * @param string $urlBase
     */
    public function setComponentUrlBase($componentUrlBase)
    {
        $componentUrlBase = (string)$componentUrlBase;

        $this->_componentUrlBase = trim($componentUrlBase, '/\\') . '/';

        return $this;
    }

    /**
     * Sets the translation object to be used for string translation.
     *
     * @param Zend_Translate $translate
     */
    public function setTranslate(Zend_Translate $translate)
    {
        $this->_translate = $translate;

        // (re)scan for translations
        $this->_scanTranslations();

        return $this;
    }

    // ------------------------------------------------------------------------
    // --- Event Handlers -----------------------------------------------------
    // ------------------------------------------------------------------------

    public function onRouteShutdown(Erfurt_Event $event)
    {
        // init component helpers
        if (!$this->_helpersCalled) {
            foreach ($this->_helpers as $componentName => &$helper) {
                // only if helper has not been previously loaded
                if (!isset($helper['instance'])) {
                    $helperInstance = $this->_loadHelper($componentName);
                } else {
                    $helperInstance = $this->_helpers[$componentName]['instance'];
                }

                $helperInstance->init();
            }

            $this->_helpersCalled = true;
        }
    }

    // ------------------------------------------------------------------------
    // --- Private Methods ----------------------------------------------------
    // ------------------------------------------------------------------------

    protected function _loadHelper($componentName)
    {
        if (!isset($this->_helpers[$componentName])) {
            throw new OntoWiki_Component_Exception("No helper defined for component '$componentName'.");
        }

        $helperSpec = $this->_helpers[$componentName];

        // load helper class
        require_once $helperSpec['path'];
        // instantiate helper object
        $helperInstance = new $helperSpec['class']($this);

        // register helper events
        if (isset($helperSpec['events'])) {
            $dispatcher = Erfurt_Event_Dispatcher::getInstance();
            foreach ($helperSpec['events'] as $currentEvent) {
                $dispatcher->register($currentEvent, $helperInstance);
            }
        }

        $this->_helpers[$componentName]['instance'] = $helperInstance;

        return $helperInstance;
    }

    private function _getLastConfigEditTime()
    {
        if (stristr(PHP_OS, 'win')) {
            return time()+1; //always triggers a full directory scan
        } else {
            return filemtime($this->_extensionPath); //false positives (any file edit) 
        }
    }
    
    private function _getModifiedConfigsSince($time)
    {
        $dir = new DirectoryIterator($this->_extensionPath);
        $reservedNames = array('themes','translations');
        $mod = array();
        foreach ($dir as $file) {
            if (!$file->isDot() && $file->isDir() && !in_array($file->getFileName(), $reservedNames)) {
                //for all folders in <ow>/extensions/
                $extensionName = $file->getFileName();
                $modifiedLocalConfig = @filemtime($this->_extensionPath . $extensionName.'.ini');
                if ($modifiedLocalConfig && $modifiedLocalConfig > $time) { //check for modification on the local config
                    $mod[$extensionName] = 0;
                }
                $modifiedDefaultConfig = @filemtime(
                    $file->getRealPath() . DIRECTORY_SEPARATOR . self::EXTENSION_DEFAULT_DOAP_FILE
                );
                if ($modifiedDefaultConfig && $modifiedDefaultConfig > $time) { //and the default config
                    if (isset($mod[$extensionName])) {
                        $mod[$extensionName] = 2;
                    } else {
                        $mod[$extensionName] = 1;
                    }
                }
            }
        }
        return $mod;
    }
    
    /**
     * Scans the component path for conforming components and
     * announces their paths to appropriate components.
     */
    private function _scanExtensionPath()
    {
        clearstatcache();
        $cachedConfigPath = 'cache/extensions.json';
        $cacheCreation = @filemtime($cachedConfigPath);
        if (file_exists($cachedConfigPath)) {
            //load from cache
            $config = json_decode(file_get_contents($cachedConfigPath), true); 
            foreach ($config as $extensionName => $extensionConfig) {
                $config[$extensionName] = new Zend_Config($extensionConfig, true); //cast
            }
        } else {
            $config = array();
            $dir = new DirectoryIterator($this->_extensionPath);
            $reservedNames = array('themes','translations');
            foreach ($dir as $file) {
                if (!$file->isDot() && $file->isDir() && !in_array($file->getFileName(), $reservedNames)) {
                    $extensionName = $file->getFileName();

                    $currentExtensionPath = $file->getPathname() . DIRECTORY_SEPARATOR;

                    // parse all extensions on the filesystem
                    if (is_readable($currentExtensionPath . self::EXTENSION_DEFAULT_DOAP_FILE)) {
                        $config[$extensionName] = $this->_loadConfigs($extensionName);
                    } 
                }
            }
        }
        
        $reloadConfigs = array();
        //parse all extensions whose configs have been modified
        if ($cacheCreation < $this->_getLastConfigEditTime()) { //this check is speeds up the scan on linux
            $reloadConfigs = $this->_getModifiedConfigsSince($cacheCreation);
            foreach ($reloadConfigs as $extensionName => $code) {
                //code: 0=>local-config, 1=>default-config, 2=>both (has been modified)
                $config[$extensionName] = $this->_loadConfigs($extensionName); //reload always both
            }
        }
        

        $view = OntoWiki::getInstance()->view;
        //register the discovered extensions within ontowiki
        foreach ($config as $extensionName => $extensionConfig) {
             $currentExtensionPath = $this->_extensionPath . DIRECTORY_SEPARATOR .$extensionName. DIRECTORY_SEPARATOR;

            if (!$extensionConfig->enabled) {
                continue;
            } 

            //templates can be in the main extension folder
            $view->addScriptPath($currentExtensionPath);
            if (isset($extensionConfig->templates)) {
                //or in a folder specified in the config
                $view->addScriptPath($currentExtensionPath.$extensionConfig->templates);
            }

            //check for component class (only one per extension for now)
            if (file_exists($currentExtensionPath.ucfirst($extensionName).self::COMPONENT_FILE_POSTFIX)) {
                $this->_addComponent($extensionName, $currentExtensionPath, $extensionConfig);
            }

            //check for modules and plugins (multiple possible)
            //TODO declare them in the config?
            $extensionDir = new DirectoryIterator($currentExtensionPath);
            foreach ($extensionDir as $extensionDirFile) {
                $filename = $extensionDirFile->getFilename();

                if ( //ends with Module postfix 
                    substr(
                        $filename, 
                        -strlen(OntoWiki_Module_Registry::MODULE_FILE_POSTFIX)
                    ) === OntoWiki_Module_Registry::MODULE_FILE_POSTFIX
                ) {
                    $this->_addModule($extensionName, $filename, $currentExtensionPath, $extensionConfig);
                } elseif ( 
                    substr(
                        $filename, 
                        -strlen(self::PLUGIN_FILE_POSTFIX)
                    ) === self::PLUGIN_FILE_POSTFIX
                ) {
                    $this->_addPlugin($filename, $currentExtensionPath, $extensionConfig);
                } elseif (
                    substr(
                        $filename, 
                        -strlen(self::WRAPPER_FILE_POSTFIX)
                    ) === self::WRAPPER_FILE_POSTFIX
                ) {
                    $this->_addWrapper($filename, $currentExtensionPath, $extensionConfig);
                }
            }
        }
        
        //save to instance
        $this->_extensionRegistry = $config;
        
        //save to cache 
        if (!empty($reloadConfigs)) {
            $configArrays = array();
            foreach ($config as $extensionName => $extensionConfig) {
                $configArrays[$extensionName] = $extensionConfig->toArray();
            }
            file_put_contents($cachedConfigPath, json_encode($configArrays));
        }
    }

    /**
     * adds a component to the internal registry.
     *
     * @param string $componentName the component's (folder) name
     * @param string $componentPath the path to the component folder
     * @param array $config the config of the components extension
     */
    private function _addComponent($componentName, $componentPath, $config)
    {
        // load helper
        $helperClassName = ucfirst($componentName) . self::COMPONENT_HELPER_SUFFIX;
        $helperPathName  = $componentPath . ucfirst($componentName) . self::COMPONENT_HELPER_FILE_SUFFIX;
        if (is_readable($helperPathName)) {
            $helperSpec = array(
                'path'   => $helperPathName,
                'class'  => $helperClassName
            );
            
            // store events
            if (isset($config->helperEvents)) {
                $helperSpec['events'] = $config->helperEvents->toArray();
            } else {
                $helperSpec['events'] = array();
            }
           
            if ($config->enabled) {
                $this->_helpers[$componentName] = $helperSpec;
                
                // event helpers need to be called early
                if (!empty($helperSpec['events'])) {
                    $this->_loadHelper($componentName);
                }

                //helpers without events will be instantiated onRouteShutdown
            } 
        } 

        $action = isset($config->action) ? $config->action : null;

        $position = isset($config->position) ? $config->position : null;

        if (isset($config->navigation) && (boolean)$config->navigation && $config->enabled) {
            // register with navigation
            OntoWiki_Navigation::register(
                $componentName, 
                array(
                    'controller' => $componentName,
                    'action'     => $action,
                    'name'       => $config->name,
                    'priority'   => $position,
                    'active'     => false
                )
            );
        }

        $this->_componentRegistry[$componentName] = $config;
    }

    protected function _addModule($extensionName, $moduleFilename, $modulePath, $config = null)
    {
        //one extension can contain many modules - so they share a config file
        //but each module needs different settings
        //so we got this hack to change the config like it was when every module had its own config
        if (isset($config->modules)) {
            $moduleName = strtolower(
                substr(
                    $moduleFilename, 
                    0, 
                    strlen($moduleFilename)-strlen(OntoWiki_Module_Registry::MODULE_FILE_POSTFIX)
                )
            );
            if (isset($config->modules->{$moduleName})) {
                //dont touch the original config (seen also by components etc)
                $config = unserialize(serialize($config)); 
                $config->merge($config->modules->{$moduleName}); //pull this config up!
            }
        } 

        if (isset($config->context) && is_string($config->context)) {
            $contexts = array($config->context);
        } else if (isset($config->context) && is_object($config->context)) {
            $contexts = $config->context->toArray();
        } else if (isset($config->contexts) && is_object($config->contexts)) {
            $contexts = $config->contexts->toArray();
        } else {
            $contexts = array(OntoWiki_Module_Registry::DEFAULT_CONTEXT);
        }
        
        // register for context(s)
        foreach ($contexts as $context) {
            //echo "register ".$moduleName." for ".$context.PHP_EOL;
            $this->_moduleRegistry->register($extensionName, $moduleFilename, $context, $config);
        }
    }


    /**
     * adds a wrapper
     *
     * @param string $filename
     * @param string $wrapperPath
     */
    protected function _addWrapper($filename, $wrapperPath, $config)
    {
        $owApp = OntoWiki::getInstance();
    	$wrapperManager = $owApp->erfurt->getWrapperManager(false);
        $wrapperManager->addWrapperExternally(
            strtolower(substr($filename, 0, strlen($filename) - strlen(self::WRAPPER_FILE_POSTFIX))), 
            $wrapperPath,
            isset($config->private) ? $config->private : new Zend_Config(array(), true)
        );
    }


    /**
     * Adds a plugin and registers it with the dispatcher.
     *
     * @param string $filename
     * @param string $pluginPath
     */
    private function _addPlugin($filename, $pluginPath, $config)
    {
        $owApp = OntoWiki::getInstance();
    	$pluginManager = $owApp->erfurt->getPluginManager(false);
        $pluginManager->addPluginExternally(
            strtolower(
                substr(
                    $filename, 
                    0,
                    strlen($filename) - strlen(self::PLUGIN_FILE_POSTFIX)
                )
            ), 
            $filename, 
            $pluginPath, 
            $config
        );
    }
    
    private static $_owconfigNS = 'http://ns.ontowiki.net/SysOnt/ExtensionConfig/';
    
    public static function loadDoapN3($path, $name) 
    {
        $parser =  Erfurt_Syntax_RdfParser::rdfParserWithFormat('n3');
        $triples = $parser->parse($path, Erfurt_Syntax_RdfParser::LOCATOR_FILE);
        $memModel = new Erfurt_Rdf_MemoryModel($triples);
        
        $owconfigNS = self::$_owconfigNS;
        $doapNS = 'http://usefulinc.com/ns/doap#';
        $mapping = array(
            $owconfigNS.'enabled' => 'enabled',
            $owconfigNS.'helperEvent' => 'helperEvents',
            $owconfigNS.'templates' => 'templates',
            $owconfigNS.'languages' => 'languages',
            $owconfigNS.'defaultAction' => 'action',
            $owconfigNS.'class' => 'classes',
            $doapNS.'name' => 'name',
            $doapNS.'description' => 'description',
            $doapNS.'maintainer' => 'authorUrl',
            $owconfigNS.'authorLabel' => 'author'
            
        );
        $scp = $owconfigNS.'config'; //sub config property
        $mp = $owconfigNS.'hasModule'; //module property
        $base = dirname($path).DIRECTORY_SEPARATOR;
        $extensionUri = $memModel->getValue($base, 'http://xmlns.com/foaf/0.1/primaryTopic');
        $privateNS = $memModel->getValue($extensionUri, $owconfigNS.'privateNamespace');
        
        $modules = array();
        $config = array('default'=>array(), 'private'=>array(), 'events'=>array(), 'modules'=>array());
        $subconfigs = array();
        foreach ($memModel->getPO($extensionUri) as $key => $values) {
            //echo $key.PHP_EOL;
            if ($key == $scp) {
                foreach ($values as $val) {
                    $subconfigs[] = $val['value'];
                }
                continue;
            } else if ($key == $mp) {
                foreach ($values as $val) {
                    $modules[] = $val['value'];
                }
                continue;
            }
            if (isset($mapping[$key])) {
                $mappedKey = $mapping[$key];
                $section = 'default'; 
            } else {
                $mappedKey = self::getPrivateKey($key, $privateNS);
                if ($mappedKey == null) {
                    //echo 'skipped '.$key.' -> '.$mappedKey.' '.PHP_EOL;
                    continue; //skip irregular keys
                }
                $section = 'private';
            }
               
            foreach ($values as $value) {
                $value = self::getValue($value);
                self::addValue($mappedKey, $value, $config[$section]);
            }
        }
                
        foreach ($subconfigs as $bnUri) {
            $config['private'] = array_merge(
                $config['private'], 
                self::getSubConfig($memModel, $bnUri, $privateNS, $mapping)
            );
        }
        
        foreach ($modules as $moduleUri) {
            $name = strtolower(self::getPrivateKey($moduleUri, $privateNS));
            $config['modules'][$name] = array();
            foreach ($memModel->getPO($moduleUri) as $key => $values) {
                $mappedKey = self::getPrivateKey($key, $owconfigNS);
                if ($mappedKey == null) {
                    continue; //modules can only have specific properties
                }

                foreach ($values as $value) {
                    $value = self::getValue($value);
                    self::addValue($mappedKey, $value, $config['modules'][$name]);
                }
            }
        }
        
        if (empty($config['events'])) {
            unset($config['events']);
        }
        
        if (isset($config['modules']['default'])) {
            $config = array_merge($config, $config['modules']['default']);
            unset($config['modules']['default']);
        }
        $config = array_merge($config, $config['default']);
        unset($config['default']);
        
        return $config;
    }
    
    private static function getValue($value) 
    {
        if ($value['type'] == 'literal' && 
            isset($value['datatype']) && 
            $value['datatype'] == 'http://www.w3.org/2001/XMLSchema#boolean'
        ) {
            $value = $value['value'] == 'true';
        } else {
            $value = $value['value'];
        }
        return $value;
    }
    
    private static function addValue($key, $value, &$to) 
    {
        if (!isset($to[$key])) { //first entry for that key
            $to[$key] = $value;
        } else if (is_array($to[$key])) { //there are already multiple values for that key
            $to[$key][] = $value;
        } else {        //it the second entry for that key, turn to array
            $to[$key] = array($to[$key], $value);
        } 
    }
    
    private static function getPrivateKey($key, $privateNS, $mapping = array()) 
    {
        if (isset($mapping[$key])) {
            return $mapping[$key];
        }
        $newKey = str_replace($privateNS, '', $key);
        if (preg_match('/^[a-zA-Z0-9]*$/', $newKey) != 1) {
            //echo 'illegal '.$key.PHP_EOL;
            return null;
        } 
        return $newKey;
    }
    
    private static function getSubConfig($memModel, $bnUri, $privateNS, $mapping) 
    {
        $kv = array();
        $name = null;
        foreach ($memModel->getPO($bnUri) as $key => $values) {
            if ($key == EF_RDF_TYPE) {
                continue;
            }
            if ($key == self::$_owconfigNS.'id') {
                $name = $values[0]['value'];
            } else if ($key == self::$_owconfigNS.'config') {
                foreach ($values as $value) {
                    $kv = array_merge($kv, self::getSubConfig($memModel, $value['value'], $privateNS, $mapping));
                }
            } else {
                $mappedKey = self::getPrivateKey($key, $privateNS, $mapping);
                foreach ($values as $value) {
                    $value = self::getValue($value);
                    self::addValue($mappedKey, $value, $kv);
                }
            }
        }
        if ($name != null) {
            return array($name=>$kv);
        } else echo 'no name for '.$bnUri;
    }

    private function _loadConfigs($name) 
    {
        $path = $this->_extensionPath . $name . DIRECTORY_SEPARATOR;
        $config = new Zend_Config(self::loadDoapN3($path . self::EXTENSION_DEFAULT_DOAP_FILE, $name), true);
           
        // overwrites default config with local config
        $localConfigPath = $this->_extensionPath . $name . '.ini';
        if (is_readable($localConfigPath)) {
            //the local config is still in ini syntax
            $localConfig = new Zend_Config_Ini($localConfigPath, null, true);
            $config->merge($localConfig);
        }

        //convert to object
        
        //fix missing names
        if (!isset ($config->name)) {
           $config->name = $name;
        }

        //fix deprecated/invalid values for "enabled"
        if (is_string($config->enabled)) {
            switch($config->enabled) {
                case '1':
                case 'enabled':
                case 'true':
                case 'on':
                    $config->enabled = true;
                    break;
                default:
                    $config->enabled = false;
            }
        }

        // normalize paths
        foreach ($this->_pathKeys as $pathKey) {
            if (isset($config->{$pathKey})) {
                $config->{$pathKey} = rtrim($config->{$pathKey}, '/\\') . '/';
            }
        }

        // save component's path
        $config->path = $path;

        return $config;
    }

    /**
      * Reads all available component translations and adds them to the translation object
      */
    private function _scanTranslations()
    {

        // check for valid translation object
        if (is_object($this->_translate) ) {
            foreach ($this->_extensionRegistry as $component => $settings) {
                // check if component owns translation
                if (
                    isset($settings->languages) &&
                    is_readable($settings->path . $settings->languages)
                ) {
                    // keep current locale
                    $locale = $this->_translate->getAdapter()->getLocale();

                    $this->_translate->addTranslation(
                        $settings->path . $settings->languages,
                        null,
                        array('scan' => Zend_Translate::LOCALE_FILENAME)
                    );

                    // reset current locale
                    $this->_translate->setLocale($locale);
                }
            }
        }
    }
}
