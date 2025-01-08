<?php
namespace qtype_ddingroups\output;

use renderer_base;
use question_attempt;
use question_display_options;


class feedback extends renderable_base {

    /**
     * Define the feedback with options for display.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options Controls what should and should not be displayed
     * via question_display_options but unit tests are fickle.
     */
    public function __construct(
        question_attempt $qa,
        /** @var question_display_options The question display options. */
        protected question_display_options $options
    ) {
        parent::__construct($qa);
    }

    public function export_for_template(renderer_base $output): array {
        global $PAGE;

        $data = [];
        $question = $this->qa->get_question();
        $qtyperenderer = $PAGE->get_renderer('qtype_ddingroups');

        if ($this->options->feedback) {
            // Literal render out but we trust the teacher.
            $data['specificfeedback'] = $qtyperenderer->specific_feedback($this->qa);

            $specificgradedetailfeedback = new specific_grade_detail_feedback($this->qa);
            $data['specificgradedetailfeedback'] = $specificgradedetailfeedback->export_for_template($output);

            if ($hint = $this->qa->get_applicable_hint()) {
                $data['hint'] = $question->format_hint($hint, $this->qa);
            }
        }

        if ($this->options->numpartscorrect) {
            $numpartscorrect = new num_parts_correct($this->qa);
            $data['numpartscorrect'] = $numpartscorrect->export_for_template($output);
        }

        if ($this->options->generalfeedback) {
            // Literal render out but we trust the teacher.
            $data['generalfeedback'] = $question->format_generalfeedback($this->qa);
        }

        if ($this->options->rightanswer) {
            $correctresponse = new correct_response($this->qa);
            $data['rightanswer'] = $correctresponse->export_for_template($output);
        }

        return $data;
    }
}
