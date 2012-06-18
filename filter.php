<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This filter invokes MathJax for mathematics typesetting.
 *
 * @package    filter
 * @subpackage mathjax
 * @copyright  2012 Jonathon Fowler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class filter_mathjax extends moodle_text_filter {
    private $mathjaxpath;
    private $paranoidmode = true;
    private $setupdone = false;
    
    /**
     * Detect the environment we're working within and set up
     * as appropriate
     */
    public function __construct($context, array $localconfig) {
        global $CFG;
        
        parent::__construct($context, $localconfig);
        
        if ($CFG->version < 2012061500) {
            // call setup() here for <2.3 releases
            global $PAGE;
            $this->setup($PAGE, $context);
        }
    }
    
    /**
     * Setup a page for filtering
     * @param moodle_page $page
     * @param context $context
     * @since 2.3
     */
    public function setup($page, $context) {
        global $CFG;
        
        if ($this->setupdone) {
            return;
        }
        $this->setupdone = true;
        
        if (file_exists($CFG->dirroot . '/filter/mathjax/js/MathJax.js') &&
            file_exists($CFG->dirroot . '/filter/mathjax/js/config') &&
            file_exists($CFG->dirroot . '/filter/mathjax/js/jax') &&
            file_exists($CFG->dirroot . '/filter/mathjax/js/fonts') &&
            file_exists($CFG->dirroot . '/filter/mathjax/js/images') &&
            file_exists($CFG->dirroot . '/filter/mathjax/js/extensions')) {
            // the bundled kit at least seems complete, so use it
            $path = '/filter/mathjax/js';
            $pathssl = '/filter/mathjax/js';
        } else {
            if (!empty($CFG->filter_mathjax_distroothttp)) {
                $path = $CFG->filter_mathjax_distroothttp;
            } else {
                $path = 'http://cdn.mathjax.org/mathjax/latest';
            }
            if (!empty($CFG->filter_mathjax_distroothttps)) {
                $pathssl = $CFG->filter_mathjax_distroothttps;
            } else {
                $pathssl = 'https://c328740.ssl.cf1.rackcdn.com/mathjax/latest';
            }
        }
        
        if (substr($page->url, 0, 6) === 'https:') {
            $this->mathjaxpath = $pathssl;
        } else {
            $this->mathjaxpath = $path;
        }
        
        if ($page->state > moodle_page::STATE_BEFORE_HEADER) {
            // It seems we can use the efficient initialisation path
            $this->paranoidmode = false;
            
            $jsurl = $this->get_mathjax_url(true);
            $mathjaxroot = (string)(new moodle_url($this->mathjaxpath));
            
            $page->requires->js($jsurl);
            $page->requires->js_init_call('M.filter_mathjax.init',
                array('mathjaxroot' => $mathjaxroot));
        } else {
            // We need to be paranoid.
            
            // Initialisation code will be emitted for each identified block
            // but will only be called once. It's enormously inefficient but
            // it's the only way we can be sure MathJax gets triggered.
            
            // The init code needs to be generated per detected block of markup
            // because, for example if a HTML file is filtered by Moodle,
            // the whole HTML page is passed through us, not just the body.
            
            debugging('filter_mathjax: page is pre-STATE_BEFORE_HEADER. '.
                    'using paranoid mode.', DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Constructs a URL to the main MathJax script
     * @param boolean $delaystartup if true, MathJax startup will be delayed
     * @return moodle_url
     */
    public function get_mathjax_url($delaystartup) {
        $params = array();
        $params['config'] = 'TeX-AMS-MML_HTMLorMML';
        if ($delaystartup) {
            $params['delayStartupUntil'] = 'configured';
        }
        return new moodle_url($this->mathjaxpath . '/MathJax.js', $params);
    }
    
    /**
     * Constructs the initialisation JavaScript code to be emitted
     * when the page is not being built through Moodle's rendering
     * framework
     * @return string
     */
    public function get_mathjax_init() {
        $escmathjaxroot = addcslashes((string)(new moodle_url($this->mathjaxpath)), "'");
        $escmathjaxurl = addcslashes((string)$this->get_mathjax_url(false), "'");
        
        $code = <<<EOT
<script type="text/javascript">
var filter_mathjax = filter_mathjax || (function(){
    var c, h = document.getElementsByTagName('head')[0];
    
    c = document.createElement('script');
    c.type = 'text/x-mathjax-config';
    c[window.opera ? 'innerHTML' : 'text'] = "MathJax.Hub.Config({ root:'{$escmathjaxroot}' });";
    h.appendChild(c);
    
    c = document.createElement('script');
    c.type = 'text/javascript';
    c.src = '{$escmathjaxurl}';
    h.appendChild(c);
    
    return function () {};
})();
filter_mathjax();
</script>
EOT;

        return $code;
    }
    
    public function filter($text, array $options = array()) {
        // The presence of these indicates MathJax ought to process the block:
        //   inline: \( ... \)
        //   block: $$ ... $$, \[ ... \]
        //   mathml: <math> ... </math>

        if ($this->paranoidmode) {
            $precodeblock = $precodeinline = $this->get_mathjax_init();
            $postcodeblock = $postcodeinline = '';
        } else {
            $precodeblock = '<div class="filter-mathjax">';
            $postcodeblock = '</div>';
            $precodeinline = '<span class="filter-mathjax">';
            $postcodeinline = '</span>';
        }
        
        // we wrap the MathJax markup we find in inline or block
        // elements as appropriate for the Javascript module to collect
        // and pass to MathJax for processing
        $patterns = array(
            '/\\\\\\(.+?\\\\\\)/s',
            '/\$\$.+?\$\$/s',
            '/\\\\\\[.+?\\\\\\]/s',
            '/<math\s[^>]+.+?<\/math>/s',
        );
        
        $replacements = array(
            $precodeinline.'$0'.$postcodeinline,
            $precodeblock.'$0'.$postcodeblock,
            $precodeblock.'$0'.$postcodeblock,
            $precodeblock.'$0'.$postcodeblock,
        );
        
        return preg_replace($patterns, $replacements, $text);
    }
}
