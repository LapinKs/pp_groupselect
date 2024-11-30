<?php
/**
 * Version information for the mulridrop question type.
 *
 * @package    qtype
 * @subpackage groupselect
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');

class qtype_groupselect_question extends question_graded_automatically {
        public $answers; // Список ответов.
        public $groups;  // Список групп.
    
        public function grade_response(array $response) {
            // Логика проверки ответа.
            return [1.0, question_state::$gradedright];
        }
}
    

    

    

    




 


    

