<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/edit_question_form.php');

/**
 * Editing form for the ddingroups question type.
 *
 * @package    qtype_ddingroups
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddingroups_edit_form extends question_edit_form {

    public function qtype(): string {
        return 'ddingroups';
    }

    /**
     * Define the specific form fields for the ddingroups question type.
     *
     * @param MoodleQuickForm $mform
     */
    public function definition_inner($mform): void {
      
        $this->add_group_with_checkboxes($mform, 1);

        
        $mform->addElement('button', 'addgroupbutton', get_string('addgroup', 'qtype_ddingroups'), [
            'onclick' => 'M.qtype_ddingroups.add_new_group();'
        ]);


        $this->add_combined_feedback_fields(true);
        $this->add_interactive_settings(false, true);


        $PAGE->requires->js_call_amd('qtype_ddingroups/edit_form', 'init');
    }

    /**
     * Add a group with a text field and a row of checkboxes.
     *
     * @param MoodleQuickForm $mform
     * @param int $groupnumber
     */
    protected function add_group_with_checkboxes($mform, int $groupnumber): void {
        $groupname = get_string('groupname', 'qtype_ddingroups', $groupnumber);

        
        $mform->addElement('header', "groupheader_$groupnumber", $groupname);

       
        $mform->addElement('text', "groupname_$groupnumber", get_string('groupname', 'qtype_ddingroups'));
        $mform->setType("groupname_$groupnumber", PARAM_TEXT);

       
        $mform->addElement('html', "<div id='checkboxes_container_$groupnumber' class='checkboxes-container'></div>");
    }

    /**
     * Data preprocessing for form display.
     *
     * @param stdClass $question
     * @return stdClass
     */
    public function data_preprocessing($question): stdClass {
        $question = parent::data_preprocessing($question);

        
        if (!empty($question->options->groups)) {
            foreach ($question->options->groups as $groupnumber => $group) {
                $question->{"groupname_$groupnumber"} = $group->name;
                $question->{"checkboxes_$groupnumber"} = $group->checkboxes; 
            }
        }

        return $question;
    }

    /**
     * Validate form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        
        if (empty($data['groupname_1'])) {
            $errors['groupname_1'] = get_string('groupnamerequired', 'qtype_ddingroups');
        }

        return $errors;
    }
}
