<?php
/**
 * @category  Apptrian
 * @package   Apptrian_Minify
 * @author    Apptrian
 * @copyright Copyright (c) 2017 Apptrian (http://www.apptrian.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License
 */

/**
 * This class is taken from mrclay/minify library and modified into Magento
 * compatible class. Some additional features are added to it for seamless
 * integration with Magento.
 */

/**
 * Class Minify_HTML
 * @package Minify
 */

/**
 * Compress HTML
 *
 * This is a heavy regex-based removal of whitespace, unnecessary comments and
 * tokens. IE conditional comments are preserved. There are also options to have
 * STYLE and SCRIPT blocks compressed by callback functions.
 *
 * A test suite is available.
 *
 * @package Minify
 * @author Stephen Clay <steve@mrclay.org>
 */

class Apptrian_Minify_Html
{
    /**
     * @var null|bool
     */
    public $isXhtml = null;

    /**
     * @var null|string
     */
    public $replacementHash = null;

    /**
     * @var array
     */
    public $placeholders = array();

    /**
     * @var null|string
     */
    public $cssMinifier = null;

    /**
     * @var null|string
     */
    public $jsMinifier = null;

    /**
     * @var boolean
     */
    public $jsCleanComments = true;

    /**
     * Remove Comments.
     *
     * @var bool
     */
    public $removeComments = true;

    /**
     * Cache Compatibility.
     *
     * @var bool
     */
    public $cacheCompatibility = false;

    /**
     * Maximum Minification (entire code on one line).
     *
     * @var bool
     */
    public $maxMinification = false;

    /**
     * Options
     *
     * @var array
     */
    public $options = array();

    /**
     * "Minify" an HTML page
     *
     * @param string $html
     *
     * @param array $options
     *
     * 'cssMinifier' : (optional) callback function to process content of STYLE
     * elements.
     *
     * 'jsMinifier' : (optional) callback function to process content of SCRIPT
     * elements. Note: the type attribute is ignored.
     *
     * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
     * unset, minify will sniff for an XHTML doctype.
     *
     * @return string
     */
    public static function minify($html, $options = array())
    {
        $min = new self($html, $options);

        return $min->process();
    }

    /**
     * Create a minifier object
     *
     * @param string $html
     *
     * @param array $options
     *
     * 'cssMinifier' : (optional) callback function to process content of STYLE
     * elements.
     *
     * 'jsMinifier' : (optional) callback function to process content of SCRIPT
     * elements. Note: the type attribute is ignored.
     *
     * 'jsCleanComments' : (optional) whether to remove HTML comments beginning
     * and end of script block
     *
     * 'xhtml' : (optional boolean) should content be treated as XHTML1.0? If
     * unset, minify will sniff for an XHTML doctype.
     */
    public function __construct($html, $options = array())
    {
        $this->_html = str_replace("\r\n", "\n", trim($html));
        if (isset($options['xhtml'])) {
            $this->isXhtml = (bool)$options['xhtml'];
        }
        
        if (isset($options['cssMinifier'])) {
            $this->cssMinifier = $options['cssMinifier'];
        }
        
        if (isset($options['jsMinifier'])) {
            $this->jsMinifier = $options['jsMinifier'];
        }
        
        if (isset($options['jsCleanComments'])) {
            $this->jsCleanComments = (bool)$options['jsCleanComments'];
        }
        
        if (isset($options['removeComments'])) {
            $this->removeComments = (bool)$options['removeComments'];
        }
        
        if (isset($options['cacheCompatibility'])) {
            $this->cacheCompatibility = (bool)$options['cacheCompatibility'];
        }
        
        if (isset($options['maxMinification'])) {
            $this->maxMinification = (bool)$options['maxMinification'];
        }
        
        // Preserve options if needed for script type="text/template"
        $this->options = $options;
    }

    /**
     * Minify the markeup given in the constructor
     *
     * @return string
     */
    public function process()
    {
        if ($this->isXhtml === null) {
            $this->isXhtml = (false !== strpos(
                $this->_html,
                '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML'
            ));
        }

        $this->replacementHash = 'MINIFYHTML' . md5($_SERVER['REQUEST_TIME']);
        $this->placeholders = array();

        // replace SCRIPTs (and minify) with placeholders
        $this->_html = preg_replace_callback(
            '/(\\s*)<script(\\b[^>]*?>)([\\s\\S]*?)<\\/script>(\\s*)/i',
            array($this, 'removeScriptCB'),
            $this->_html
        );

        // replace STYLEs (and minify) with placeholders
        $this->_html = preg_replace_callback(
            '/\\s*<style(\\b[^>]*>)([\\s\\S]*?)<\\/style>\\s*/i',
            array($this, 'removeStyleCB'),
            $this->_html
        );

        // remove HTML comments (not containing IE conditional comments).
        $this->_html = preg_replace_callback(
            '/<!--([\\s\\S]*?)-->/',
            array($this, 'commentCB'),
            $this->_html
        );

        // replace PREs with placeholders
        $this->_html = preg_replace_callback(
            '/\\s*<pre(\\b[^>]*?>[\\s\\S]*?<\\/pre>)\\s*/i',
            array($this, 'removePreCB'),
            $this->_html
        );

        // replace TEXTAREAs with placeholders
        $this->_html = preg_replace_callback(
            '/\\s*<textarea(\\b[^>]*?>[\\s\\S]*?<\\/textarea>)\\s*/i',
            array($this, 'removeTextareaCB'),
            $this->_html
        );

        // trim each line.
        // To be done > take into account attribute values that span multiple
        // lines.
        $this->_html = preg_replace('/^\\s+|\\s+$/m', '', $this->_html);

        // remove ws around block/undisplayed elements
        $this->_html = preg_replace(
            '/\\s+(<\\/?(?:area|article|aside|base(?:font)?|blockquote|body'
            .'|canvas|caption|center|col(?:group)?|dd|dir|div|dl|dt|fieldset'
            .'|figcaption|figure|footer|form|frame(?:set)?|h[1-6]|head|header'
            .'|hgroup|hr|html|legend|li|link|main|map|menu|meta|nav|ol'
            .'|opt(?:group|ion)|output|p|param|section'
            .'|t(?:able|body|head|d|h||r|foot|itle)|ul|video)\\b[^>]*>)/i',
            '$1',
            $this->_html
        );

        // remove ws outside of all elements
        $this->_html = preg_replace(
            '/>(\\s(?:\\s*))?([^<]+)(\\s(?:\s*))?</',
            '>$1$2$3<',
            $this->_html
        );

        if ($this->maxMinification) {
            // Strip all multiple spaces to one space
            $this->_html = preg_replace('/\s+/ui', ' ', $this->_html);
        } else {
            // use newlines before 1st attribute in open tags
            // (to limit line lengths)
            $this->_html = preg_replace(
                '/(<[a-z\\-]+)\\s+([^>]+>)/i',
                "$1\n$2",
                $this->_html
            );
        }
        
        // fill placeholders
        $this->_html = str_replace(
            array_keys($this->placeholders),
            array_values($this->placeholders),
            $this->_html
        );
        // issue 229: multi-pass to catch scripts that didn't get replaced
        // in textareas
        $this->_html = str_replace(
            array_keys($this->placeholders),
            array_values($this->placeholders),
            $this->_html
        );

        return $this->_html;
    }

    public function commentCB($m)
    {
        if ($this->cacheCompatibility) {
            return (0 === strpos($m[1], '[')
                || false !== strpos($m[1], '<![')
                || false !== stripos($m[1], ' ko ')
                || false !== stripos($m[1], ' /ko ')
                || false !== stripos($m[1], 'esi <')
                || false !== stripos($m[1], ' fpc')
            )
                ? $m[0]
                : '';
        } else {
            return (0 === strpos($m[1], '[')
                || false !== strpos($m[1], '<![')
                || false !== stripos($m[1], ' ko ')
                || false !== stripos($m[1], ' /ko ')
            )
                ? $m[0]
                : '';
        }
    }

    public function reservePlace($content)
    {
        $placeholder = '%' . $this->replacementHash
            . count($this->placeholders) . '%';
        $this->placeholders[$placeholder] = $content;

        return $placeholder;
    }

    public function removePreCB($m)
    {
        return $this->reservePlace("<pre{$m[1]}");
    }

    public function removeTextareaCB($m)
    {
        return $this->reservePlace("<textarea{$m[1]}");
    }

    public function removeStyleCB($m)
    {
        $openStyle = "<style{$m[1]}";
        $css = $m[2];
        // remove HTML comments
        $css = preg_replace('/(?:^\\s*<!--|-->\\s*$)/', '', $css);

        // remove CDATA section markers
        $css = $this->removeCdata($css);

        // minify
        $css = Mage::helper('apptrian_minify')
            ->minifyCss($css, $this->removeComments);

        return $this->reservePlace(
            $this->needsCdata($css)
                ? "{$openStyle}/*<![CDATA[*/{$css}/*]]>*/</style>"
                : "{$openStyle}{$css}</style>"
        );
    }

    public function removeScriptCB($m)
    {
        $openScript = "<script{$m[2]}";
        $js = $m[3];

        // whitespace surrounding? preserve at least one space
        $wsA = ($m[1] === '') ? '' : ' ';
        $wsB = ($m[4] === '') ? '' : ' ';

        // remove HTML comments (and ending "//" if present)
        if ($this->jsCleanComments) {
            $js = preg_replace(
                '/(?:^\\s*<!--\\s*|\\s*(?:\\/\\/)?\\s*-->\\s*$)/',
                '',
                $js
            );
        }

        // remove CDATA section markers
        $js = $this->removeCdata($js);

        if (false !== stripos($openScript, 'type="text/template"')
            || false !== stripos($openScript, 'type="text/x-magento-template"')
        ) {
            $js = Apptrian_Minify_Html::minify($js, $this->options);
        } else {
            // minify
            $js = Mage::helper('apptrian_minify')
                ->minifyJs($js, $this->removeComments);
        }

        return $this->reservePlace(
            $this->needsCdata($js)
                ? "{$wsA}{$openScript}/*<![CDATA[*/{$js}/*]]>*/</script>{$wsB}"
                : "{$wsA}{$openScript}{$js}</script>{$wsB}"
        );
    }

    public function removeCdata($str)
    {
        return (false !== strpos($str, '<![CDATA['))
            ? str_replace(array('<![CDATA[', ']]>'), '', $str)
            : $str;
    }

    public function needsCdata($str)
    {
        return ($this->isXhtml && preg_match(
            '/(?:[<&]|\\-\\-|\\]\\]>)/',
            $str
        ));
    }
}
