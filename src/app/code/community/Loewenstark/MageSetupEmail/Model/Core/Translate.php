<?php

class Loewenstark_MageSetupEmail_Model_Core_Translate
extends Mage_Core_Model_Translate
{
    // ttl for apc/apcu/xcache
    const UC_TTL = 604800; //7 days
    const DEBUG_LOG_FILE = 'translate.log';
    const LOCALE_FOLDER = 'magesetupemail';
    
    protected $_debug = false;
    protected $_force_revalidate = false;
    protected $_cache = null;
    
    public function __construct()
    {
        parent::__construct();
        if(extension_loaded('apc'))
        {
            $this->_cache = 'apc';
        } elseif(extension_loaded('apcu')) {
            $this->_cache = 'apcu';
        } elseif(extension_loaded('xCache')) {
            $this->_cache = 'xcache';
        }
    }

    /**
     * Retrive translated template file
     * LWS: replace the hole method and added cache mechanism, Files: magesetup or templates based
     *
     * @param string $file
     * @param string $type
     * @param string $localeCode
     * @return string file content
     */
    public function getTemplateFile($file, $type, $localeCode=null)
    {
        $package = $this->getConfig(self::CONFIG_KEY_DESIGN_PACKAGE);
        $theme = $this->getConfig(self::CONFIG_KEY_DESIGN_THEME);
        $store_id = $this->getConfig(self::CONFIG_KEY_STORE);
        
        if (is_null($localeCode) || preg_match('/[^a-zA-Z_]/', $localeCode)) {
            $localeCode = $this->getLocale();
        }
        
        $filePath = Mage::getBaseDir('design')  . DS . 'frontend' . DS .
                $package . DS . $theme . DS. 'locale'. DS . $localeCode . DS . $type . DS . $file;
        if($this->_FileExists($filePath))
        {
            return $this->_readTemplateFile($filePath, $key);
        }
        
        $filePath = Mage::getBaseDir('design')  . DS . 'frontend' . DS .
                $package . DS . 'default' . DS. 'locale'. DS . $localeCode . DS . $type . DS . $file;
        
        if($this->_FileExists($filePath))
        {
            return $this->_readTemplateFile($filePath, $key);
        }
        
        $filePath = Mage::getBaseDir('locale') . DS
                . $localeCode
                . DS . 'template' . DS . self::LOCALE_FOLDER . DS . $type . DS . $file;
        
        if($this->_FileExists($filePath))
        {
            return $this->_readTemplateFile($filePath, $key);
        }
        
        $filePath = Mage::getBaseDir('locale') . DS
                . Mage::app()->getLocale()->getDefaultLocale()
                . DS . 'template' . DS . self::LOCALE_FOLDER . DS . $type . DS . $file;
        
        if(Mage::app()->getLocale()->getDefaultLocale() != $localeCode && $this->_FileExists($filePath))
        {
            return $this->_readTemplateFile($filePath, $key);
        }
        
        $filePath = Mage::getBaseDir('locale') . DS
                . Mage_Core_Model_Locale::DEFAULT_LOCALE
                . DS . 'template' . DS . self::LOCALE_FOLDER . DS . $type . DS . $file;
        
        if(Mage_Core_Model_Locale::DEFAULT_LOCALE != $localeCode && $this->_FileExists($filePath))
        {
            return $this->_readTemplateFile($filePath, $key);
        }
        
        // orig part
        $filePath = Mage::getBaseDir('locale')  . DS
                  . $localeCode . DS . 'template' . DS . $type . DS . $file;
        if($this->_FileExists($filePath))
        {
            return $this->_readTemplateFile($filePath, $key);
        }
        // If no template specified for this locale, use store default
        $filePath = Mage::getBaseDir('locale') . DS
                  . Mage::app()->getLocale()->getDefaultLocale()
                  . DS . 'template' . DS . $type . DS . $file;
        if(Mage::app()->getLocale()->getDefaultLocale() != $localeCode && $this->_FileExists($filePath))
        {
            return $this->_readTemplateFile($filePath, $key);
        }

        // If no template specified as  store default locale, use en_US/Mage_Core_Model_Locale::DEFAULT_LOCALE
        $filePath = Mage::getBaseDir('locale') . DS
                  . Mage_Core_Model_Locale::DEFAULT_LOCALE
                  . DS . 'template' . DS . $type . DS . $file;

        return $this->_readTemplateFile($filePath, $key);
    }
    
    /**
     * read file
     * 
     * @param string $file
     * @param bool $cachekey
     * @return string file content
     */
    protected function _readTemplateFile($file, $cachekey=false)
    {
        if($cachekey)
        {
            $this->_setTemplateCache($cachekey, $file);
        }
        return (string)file_get_contents($file);
    }
    
    /**
     * 
     * @param string $key
     * @return boolean|string
     */
    protected function _getTemplateCache($key)
    {
        if($this->_force_revalidate)
        {
            return false;
        }
        $value = null;
        switch($this->_cache)
        {
            case 'apc':
                $value = apc_fetch($key);
                break;
            case 'apcu':
                $value = apcu_fetch($key);
                break;
            case 'xcache':
                $value = xcache_isset($key) ? xcache_get($key) : null;
                break;
        }
        if(!empty($value))
        {
            if(file_exists($value))
            {
                if($this->_debug)
                {
                    Mage::log('loaded key from cache: '.$key, null, self::DEBUG_LOG_FILE);
                }
                return $value;
            }
            // clear cache if file does not exits
            $this->_setTemplateCache($key, null);
        }
        return false;
    }
    
    /**
     * 
     * @param type $key
     * @param type $value
     */
    protected function _setTemplateCache($key, $value)
    {
        $save = false;
        switch($this->_cache)
        {
            case 'apc':
                apc_store($key, $value, self::UC_TTL);
                break;
            case 'apcu':
                apcu_store($key, $value, self::UC_TTL);
                break;
            case 'xcache':
                xcache_set($key, $value, self::UC_TTL);
                break;
        }
        if($this->_cache)
        {
            $save = true;
        }
        if($this->_debug && $save)
        {
            Mage::log('save key to cache: '.$key, null, self::DEBUG_LOG_FILE);
        }
        return true;
    }
    
    /**
     * alias for file_exists
     * 
     * @param string $file
     * @return bool
     */
    protected function _FileExists($file)
    {
        if($this->_debug)
        {
            Mage::log('File: '.$file, null, self::DEBUG_LOG_FILE);
        }
        return @file_exists($file);
    }
}
