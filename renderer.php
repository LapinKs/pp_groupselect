<?php

use qtype_ddingroups\output\correct_response;
use qtype_ddingroups\output\feedback;
use qtype_ddingroups\output\formulation_and_controls;
use qtype_ddingroups\output\num_parts_correct;
use qtype_ddingroups\output\specific_grade_detail_feedback;


class qtype_ddingroups_renderer extends qtype_with_combined_feedback_renderer {

    public function formulation_and_controls(question_attempt $qa, question_display_options $options): string {
        $formulationandcontrols = new formulation_and_controls($qa, $options);
        return $this->output->render_from_template('qtype_ddingroups/formulation_and_controls',
            $formulationandcontrols->export_for_template($this->output));
    }
    

    public function feedback(question_attempt $qa, question_display_options $options): string {
        $feedback = new feedback($qa, $options);
        return $this->output->render_from_template('qtype_ddingroups/feedback',
            $feedback->export_for_template($this->output));
    }

    /**
     * Display the grade detail of the response.
     *
     * @param question_attempt $qa The question attempt to display.
     * @return string Output grade detail of the response.
     * @throws moodle_exception
     */
    public function specific_grade_detail_feedback(question_attempt $qa): string {
        $specificgradedetailfeedback = new specific_grade_detail_feedback($qa);
        return $this->output->render_from_template('qtype_ddingroups/specific_grade_detail_feedback',
            $specificgradedetailfeedback->export_for_template($this->output));
    }

    public function specific_feedback(question_attempt $qa): string {
        return $this->combined_feedback($qa);
    }

    public function correct_response(question_attempt $qa): string {
        $correctresponse = new correct_response($qa);

        return $this->output->render_from_template('qtype_ddingroups/correct_response',
            $correctresponse->export_for_template($this->output));
    }

    protected function num_parts_correct(question_attempt $qa): string {
        $numpartscorrect = new num_parts_correct($qa);
        return $this->output->render_from_template('qtype_ddingroups/num_parts_correct',
            $numpartscorrect->export_for_template($this->output));
    }

    public function feedback_image($fraction, $selected = true): string {
        return parent::feedback_image($fraction);
    }
}
