<?php
/**
 *
 * @package   qtype_ddingroups
 * @copyright -
 * @author    Konstantin Lapin <kostyalapin777@mail.ru>
 */


defined('MOODLE_INTERNAL') || die();


function qtype_ddingroups_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_ddingroups', $filearea, $args, $forcedownload, $options);
}
