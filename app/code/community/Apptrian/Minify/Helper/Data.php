<?php
/**
 * @category  Apptrian
 * @package   Apptrian_Minify
 * @author    Apptrian
 * @copyright Copyright (c) 2017 Apptrian (http://www.apptrian.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License
 */
class Apptrian_Minify_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Array of paths that will be scaned for css and js files.
     *
     * @var array
     */
    protected $_paths = null;
    
    /**
     * Returns extension version.
     *
     * @return string
     */
    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()
            ->getNode()->modules->Apptrian_Minify->version;
    }
    
    /**
     * Returns array of paths that will be scaned for css and js files.
     *
     * @return array
     */
    public function getPaths()
    {
        if ($this->_paths === null) {
            $list         = array();
            $baseDirMedia = Mage::getBaseDir('media');
            $css          = $baseDirMedia . DS . 'css';
            $cssSecure    = $baseDirMedia . DS . 'css_secure';
            $js           = $baseDirMedia . DS . 'js';
            
            if (file_exists($css)) {
                $list[] = $css;
            }
            
            if (file_exists($cssSecure)) {
                $list[] = $cssSecure;
            }
            
            if (file_exists($js)) {
                $list[] = $js;
            }
            
            $this->_paths = $list;
        }
        
        return $this->_paths;
    }
    
    /**
     * Minifies CSS and JS files.
     *
     */
    public function process()
    {
        // Get remove important comments option
        $removeComments = (bool) Mage::getConfig()->getNode(
            'apptrian_minify/minify_css_js/remove_comments',
            'default'
        );
        
        foreach ($this->getPaths() as $path) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $path,
                    RecursiveDirectoryIterator::FOLLOW_SYMLINKS
                )
            );
            
            foreach ($iterator as $filename => $file) {
                if ($file->isFile()
                    && preg_match('/^.+\.(css|js)$/i', $file->getFilename())
                ) {
                    $filePath = $file->getRealPath();
                    if (!is_writable($filePath)) {
                        Mage::log(
                            'Minification failed for '
                            . $filePath . ' File is not writable.'
                        );
                        continue;
                    }
                    
                    //This is available from php v5.3.6
                    //$ext = $file->getExtension();
                    // Using this for compatibility
                    $ext = strtolower(
                        pathinfo($filePath, PATHINFO_EXTENSION)
                    );
                    $optimized   = '';
                    $unoptimized = file_get_contents($filePath);
                    
                    // If it is 0 byte file or cannot be read
                    if (!$unoptimized) {
                        Mage::log('File ' . $filePath . ' cannot be read.');
                        continue;
                    }
                    
                    // CSS files
                    if ($ext == 'css') {
                        $optimized = $this->minifyCss(
                            $unoptimized,
                            $removeComments
                        );
                    // JS files
                    } else {
                        $optimized = $this->minifyJs(
                            $unoptimized,
                            $removeComments
                        );
                    }
                    
                    // If optimization failed
                    if (!$optimized) {
                        Mage::log('File ' . $filePath . ' was not minified.');
                        continue;
                    }
                    
                    if (file_put_contents(
                        $filePath,
                        $optimized,
                        LOCK_EX
                    ) === false) {
                        Mage::log('Minification failed for ' . $filePath);
                    }
                }
            }
        }
    }
    
    public function minifyHtml(
        $html,
        $removeComments = true,
        $cacheCompatibility = false,
        $maxMinification = false
    ) {
        $options = array(
            'removeComments'     => $removeComments,
            'cacheCompatibility' => $cacheCompatibility,
            'maxMinification'    => $maxMinification
        );
        
        try {
            return Apptrian_Minify_Html::minify($html, $options);
        } catch (Exception $e) {
            $url = Mage::helper('core/url')->getCurrentUrl();;
            Mage::log('You have HTML/CSS/JS error on your web page.');
            Mage::log('Page URL: ' . $url);
            Mage::log('Exception Message: ' . $e->getMessage());
            Mage::log('Exception Trace: ' . $e->getTraceAsString());
            return $html;
        }
    }
    
    public function minifyCss($css, $removeComments = true)
    {
        $minifier = new Apptrian_Minify_Css(true, $removeComments);
        
        return $minifier->run($css);
    }
    
    public function minifyJs($js, $removeComments = true)
    {
        $flaggedComments = !$removeComments;
        
        return Apptrian_Minify_Js::minify(
            $js,
            array('flaggedComments' => $flaggedComments)
        );
    }
}
