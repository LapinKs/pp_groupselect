<?php


defined('MOODLE_INTERNAL') || die();


/**
 * ddingroups question type editing form definition.
 *
 * @copyright 2007 Jamie Pratt me@jamiep.org
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ddingroups_edit_form extends question_edit_form {

    protected function get_per_answer_fields($mform, $label, $gradeoptions,
            &$repeatedoptions, &$answersoption) {
        $mform->addElement('static', 'answersinstruct',
                get_string('availablechoices', 'qtype_ddingroups'),
                get_string('filloutthreeqsandtwoas', 'qtype_ddingroups'));

        $repeated = array();
        $repeated[] = $mform->createElement('editor', 'subquestions',
                $label, array('rows'=>3), $this->editoroptions);
        $repeated[] = $mform->createElement('text', 'subanswers',
                get_string('answer', 'question'), array('size' => 50, 'maxlength' => 255));
        $repeatedoptions['subquestions']['type'] = PARAM_RAW;
        $repeatedoptions['subanswers']['type'] = PARAM_TEXT;
        $answersoption = 'subquestions';
        return $repeated;
    }

    /**
     * Add question-type specific form fields.
     *
     * @param object $mform the form being built.
     */
    protected function definition_inner($mform) {
        $mform->addElement('advcheckbox', 'shuffleanswers',
                get_string('shuffle', 'qtype_ddingroups'), null, null, array(0, 1));
        $mform->addHelpButton('shuffleanswers', 'shuffle', 'qtype_ddingroups');
        $mform->setDefault('shuffleanswers', $this->get_default_value('shuffleanswers', 1));

        $this->add_per_answer_fields($mform, get_string('questionno', 'question', '{no}'), 0);

        $this->add_combined_feedback_fields(true);
        $this->add_interactive_settings(true, true);
    }

    /**
     * Language string to use for 'Add {no} more {whatever we call answers}'.
     */
    protected function get_more_choices_string() {
        return get_string('blanksforxmorequestions', 'qtype_ddingroups');
    }

    protected function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_combined_feedback($question, true);
        $question = $this->data_preprocessing_hints($question, true, true);

        if (empty($question->options)) {
            return $question;
        }

        $question->shuffleanswers = $question->options->shuffleanswers;

        $key = 0;
        foreach ($question->options->subquestions as $subquestion) {
            $question->subanswers[$key] = $subquestion->answertext;

            $draftid = file_get_submitted_draft_itemid('subquestions[' . $key . ']');
            $question->subquestions[$key] = array();
            $question->subquestions[$key]['text'] = file_prepare_draft_area($draftid,
                    $this->context->id, 'qtype_ddingroups', 'subquestion',
                    !empty($subquestion->id) ? (int) $subquestion->id : null,
                    $this->fileoptions, $subquestion->questiontext);
            $question->subquestions[$key]['format'] = $subquestion->questiontextformat;
            $question->subquestions[$key]['itemid'] = $draftid;
            $key++;
        }

        return $question;
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $answers = $data['subanswers'];
        $questions = $data['subquestions'];
        $questioncount = 0;
        $answercount = 0;
        foreach ($questions as $key => $question) {
            $trimmedquestion = trim($question['text']);
            $trimmedanswer = trim($answers[$key]);
            if ($trimmedquestion != '') {
                $questioncount++;
            }
            if ($trimmedanswer != '' || $trimmedquestion != '') {
                $answercount++;
            }
            if ($trimmedquestion != '' && $trimmedanswer == '') {
                $errors['subanswers['.$key.']'] =
                        get_string('nomatchinganswerforq', 'qtype_ddingroups', $trimmedquestion);
            }
        }
        $numberqanda = new stdClass();
        $numberqanda->q = 2;
        $numberqanda->a = 3;
        if ($questioncount < 1) {
            $errors['subquestions[0]'] =
                    get_string('notenoughqsandas', 'qtype_ddingroups', $numberqanda);
        }
        if ($questioncount < 2) {
            $errors['subquestions[1]'] =
                    get_string('notenoughqsandas', 'qtype_ddingroups', $numberqanda);
        }
        if ($answercount < 3) {
            $errors['subanswers[2]'] =
                    get_string('notenoughqsandas', 'qtype_ddingroups', $numberqanda);
        }
        return $errors;
    }

    public function qtype() {
        return 'ddingroups';
    }
}

