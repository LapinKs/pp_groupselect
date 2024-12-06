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
        // Add field for group count.
        $mform->addElement('text', 'groupcount', get_string('groupcount', 'qtype_ddingroups'), ['size' => 2]);
        $mform->setType('groupcount', PARAM_INT);
        $mform->addHelpButton('groupcount', 'groupcount', 'qtype_ddingroups');
        $mform->setDefault('groupcount', 2);

        // Add field for sequence check.
        $sequenceoptions = [
            0 => get_string('strictsequence', 'qtype_ddingroups'),
            1 => get_string('loosequence', 'qtype_ddingroups'),
        ];
        $mform->addElement('select', 'sequencecheck', get_string('sequencecheck', 'qtype_ddingroups'), $sequenceoptions);
        $mform->setDefault('sequencecheck', 0);
        $mform->addHelpButton('sequencecheck', 'sequencecheck', 'qtype_ddingroups');

        // Add fields for feedback.
        $this->add_combined_feedback_fields(true);

        // Add interactive settings.
        $this->add_interactive_settings(false, true);

        // Define draggable items and groups.
        $this->add_draggable_items_section($mform);
    }

    /**
     * Add draggable items and groups.
     *
     * @param MoodleQuickForm $mform
     */
    protected function add_draggable_items_section($mform): void {
        $mform->addElement('header', 'draggableitemsheader', get_string('draggableitems', 'qtype_ddingroups'));
        $mform->setExpanded('draggableitemsheader', true);

        $this->add_repeat_elements($mform, 'draggableitem', [
            $mform->createElement('editor', 'text', get_string('draggableitem', 'qtype_ddingroups')),
            $mform->createElement('select', 'group', get_string('group', 'qtype_ddingroups'), ['Group 1', 'Group 2']),
        ], [
            'text' => ['type' => PARAM_RAW],
            'group' => ['type' => PARAM_INT],
        ]);
    }

    /**
     * Data preprocessing for form display.
     *
     * @param stdClass $question
     * @return stdClass
     */
    public function data_preprocessing($question): stdClass {
        $question = parent::data_preprocessing($question);
        $question->groupcount = $question->options->groupcount ?? 2;
        $question->sequencecheck = $question->options->sequencecheck ?? 0;

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

        if ($data['groupcount'] < 2) {
            $errors['groupcount'] = get_string('groupcounttooshort', 'qtype_ddingroups');
        }

        return $errors;
    }
}
