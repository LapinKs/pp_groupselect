<?php

class correct_response extends renderable_base {
    public function export_for_template(\renderer_base $output): array {

        $data = [];
        $question = $this->qa->get_question();
        $correctresponse = $question->correctresponse;
        $data['hascorrectresponse'] = !empty($correctresponse);
        // Early return if a correct response does not exist.
        if (!$data['hascorrectresponse']) {
            return $data;
        }

        $step = $this->qa->get_last_step();
        // The correct response should be displayed only for partially correct or incorrect answers.
        $data['showcorrect'] = $step->get_state() == 'gradedpartial' || $step->get_state() == 'gradedwrong';
        // Early return if the correct response should not be displayed.
        if (!$data['showcorrect']) {
            return $data;
        }

        $data['ddingroupslayoutclass'] = $question->get_ddingroups_layoutclass();
        $data['correctanswers'] = [];

        foreach ($correctresponse as $answerid) {
            $answer = $question->answers[$answerid];
            $answertext = $question->format_text($answer->answer, $answer->answerformat,
                $this->qa, 'question', 'answer', $answerid);

            $data['correctanswers'][] = [
                'answertext' => $answertext,
            ];
        }

        return $data;
    }
}
