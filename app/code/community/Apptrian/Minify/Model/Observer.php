<?php
/**
 * @category  Apptrian
 * @package   Apptrian_Minify
 * @author    Apptrian
 * @copyright Copyright (c) 2017 Apptrian (http://www.apptrian.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License
 */
class Apptrian_Minify_Model_Observer
{
    /**
     * Flag used to determine if Minify extension is enabled in config.
     *
     * @var null|bool
     */
    protected $_minifyEnabled = null;
    
    /**
     * Flag used to determine if Cache Compatibility option is set in config.
     *
     * @var null|bool
     */
    protected $_cacheCompatibility = null;
    
    /**
     * Flag used to determine if Maximum HTML Minification is set in config.
     *
     * @var null|bool
     */
    protected $_maxMinification = null;
    
    /**
     * Flag used to determine if Remove Important Comments is set in config.
     *
     * @var null|bool
     */
    protected $_removeComments = null;
    
    /**
     * Flag used to determine if block level HTML minification is set in config.
     *
     * @var null|bool
     */
    protected $_blockMinify = null;
    
    /**
     * Method returns status of minify extension. Is it enabled or not?
     *
     * @return bool
     */
    public function getMinifyEnabledStatus()
    {
        if ($this->_minifyEnabled === null) {
            $this->_minifyEnabled = Mage::getStoreConfigFlag(
                'apptrian_minify/minify_html/enabled'
            );
        }
    
        return $this->_minifyEnabled;
    }
    
    /**
     * Method returns status of cache comatibility option.
     *
     * @return bool
     */
    public function getCacheCompatibilityStatus()
    {
        if ($this->_cacheCompatibility === null) {
            $this->_cacheCompatibility = Mage::getStoreConfigFlag(
                'apptrian_minify/minify_html/compatibility'
            );
        }
    
        return $this->_cacheCompatibility;
    }
    
    /**
     * Method returns status of maximum HTML minification option.
     *
     * @return bool
     */
    public function getMaxMinificationStatus()
    {
        if ($this->_maxMinification === null) {
            $this->_maxMinification = Mage::getStoreConfigFlag(
                'apptrian_minify/minify_html/max_minification'
            );
        }
    
        return $this->_maxMinification;
    }
    
    /**
     * Method returns status of Remove Important Comments option.
     *
     * @return bool
     */
    public function getRemoveCommentsStatus()
    {
        if ($this->_removeComments === null) {
            $this->_removeComments = Mage::getStoreConfigFlag(
                'apptrian_minify/minify_css_js/remove_comments'
            );
        }
    
        return $this->_removeComments;
    }
    
    /**
     * Method returns status of block minification.
     *
     * @return bool
     */
    public function getBlockMinifyStatus()
    {
        if ($this->_blockMinify === null) {
            if ($this->getMinifyEnabledStatus()
                && $this->getCacheCompatibilityStatus()
            ) {
                $this->_blockMinify = true;
            } else {
                $this->_blockMinify = false;
            }
        }
        
        return $this->_blockMinify;
    }
    
    /**
     * This method is minifying HTML of every block.
     * Multiple calls per page but they are cached.
     *
     * @param Varien_Event_Observer $observer
     */
    public function minifyBlockHtml(Varien_Event_Observer $observer)
    {
        if ($this->getBlockMinifyStatus()) {
            $block     = $observer->getBlock();
            $transport = $observer->getTransport();
            $html      = $transport->getHtml();
            
            $removeComments  = $this->getRemoveCommentsStatus();
            $maxMinification = $this->getMaxMinificationStatus();
            
            $transport->setHtml(
                Mage::helper('apptrian_minify')->minifyHtml(
                    $html,
                    $removeComments,
                    true,
                    $maxMinification
                )
            );
        }
    }
    
    /**
     * This method is minifying HTML of entire page.
     * One call per entire page.
     *
     * @param Varien_Event_Observer $observer
     */
    public function minifyPageHtml(Varien_Event_Observer $observer)
    {
        $minifyEnabled      = $this->getMinifyEnabledStatus();
        $cacheCompatibility = $this->getCacheCompatibilityStatus();
        $maxMinification    = $this->getMaxMinificationStatus();
        $removeComments     = $this->getRemoveCommentsStatus();
        
        // !$cacheCompatibility must be there because it will minify twice
        // once on block level and once on page level
        if ($minifyEnabled && !$cacheCompatibility) {
            $response = $observer->getEvent()->getControllerAction()
                ->getResponse();
            $html     = $response->getBody();
            
            if (stripos($html, '<!DOCTYPE html') !== false) {
                $type = false;
                
                foreach ($response->getHeaders() as $header) {
                    if (stripos($header['name'], 'Content-Type') !== false) {
                        if (stripos($header['value'], 'text/html') !== false) {
                            $type = true;
                            break;
                        }
                    }
                }
                
                if ($type) {
                    $response->setBody(
                        Mage::helper('apptrian_minify')->minifyHtml(
                            $html,
                            $removeComments,
                            $cacheCompatibility,
                            $maxMinification
                        )
                    );
                }
            }
        }
    }
}
