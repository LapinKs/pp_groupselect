<?php
namespace qtype_ddingroups\output;

class correct_response extends renderable_base {
    public function export_for_template(\renderer_base $output): array {
        $data = [];
        $question = $this->qa->get_question();
        $correctresponse = $question->correctresponse;
        $data['hascorrectresponse'] = !empty($correctresponse);

        // Если правильного ответа нет, ранний возврат.
        if (!$data['hascorrectresponse']) {
            return $data;
        }

        $step = $this->qa->get_last_step();
        // Отображаем правильный ответ только при частично правильных или неверных ответах.
        $data['showcorrect'] = in_array($step->get_state(), ['gradedpartial', 'gradedwrong', 'notanswered']);
        if (!$data['showcorrect']) {
            return $data;
        }

        $data['ddingroupslayoutclass'] = $question->get_ddingroups_layoutclass();
        $data['correctanswers'] = [];

        foreach ($correctresponse as $groupid => $answerids) {
            $groupname = $question->groups[$groupid]->name; // Название группы.

            $answers = [];
            foreach ($answerids as $answerid) {
                $answer = $question->answers[$answerid];
                $answertext = $question->format_text($answer->answer, $answer->answerformat,
                    $this->qa, 'question', 'answer', $answerid);
                $answers[] = [
                    'answertext' => $answertext,
                ];
            }

            $data['correctanswers'][] = [
                'groupid' => $groupid,
                'groupname' => $groupname,
                'answers' => $answers,
            ];
        }

        return $data;
    }
}
