<?php

defined('MOODLE_INTERNAL') || die();

class filter_mathjax extends moodle_text_filter {
    public function __construct($context, array $localconfig) {
        global $PAGE, $CFG;
        
        if (isset($PAGE)) {
            if (file_exists($CFG->dirroot . '/filter/mathjax/js/MathJax.js')) {
                $mathjaxpath = '/filter/mathjax/js/MathJax.js';
            } else if (substr($PAGE->url, 0, 6) === 'https:') {
                $mathjaxpath = 'https://d3eoax9i5htok0.cloudfront.net/mathjax/latest/MathJax.js';
            } else {
                $mathjaxpath = 'http://cdn.mathjax.org/mathjax/latest/MathJax.js';
            }
            
            $moodleconfig = new moodle_url('/filter/mathjax/moodle-config.js.php');
            $url = new moodle_url($mathjaxpath, array('config' =>
                    'TeX-AMS-MML_HTMLorMML,' . $moodleconfig));
            
            $PAGE->requires->js($url);
        }
        
        parent::__construct($context, $localconfig);
    }
    
    public function filter($text, array $options = array()) {
        return $text;
    }
}
