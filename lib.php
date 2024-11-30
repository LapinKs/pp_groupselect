<?php


defined('MOODLE_INTERNAL') || die();


/**
 * Checks file access for numerical questions.
 *
 * @package  qtype_multidrop
 * @category files

 */
function qtype_multiodrop_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_groupselect', $filearea, $args, $forcedownload, $options);
}
