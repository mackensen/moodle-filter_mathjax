<?php
define('NO_DEBUG_DISPLAY', true);
require_once('../../config.php');
header("Content-Type: application/javascript");
?>
/**
 * MathJax configuration for Moodle
 */

MathJax.Hub.Config({
  // only process the contents of the #page-content element
  elements: [ 'page-content' ],
});

MathJax.Ajax.loadComplete("<?php echo $PAGE->url ?>");
