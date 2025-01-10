<?php

namespace qtype_ddingroups\output;

use qtype_ddingroups_question;

class specific_grade_detail_feedback extends renderable_base {
    public function export_for_template(\renderer_base $output): array {
        $data = [];
        $question = $this->qa->get_question();

        // Определяем, показывать ли детали оценки для состояний "partial" или "wrong".
        $showpartialwrong = false;
        if ($step = $this->qa->get_last_step()) {
            $showpartialwrong = preg_match('/(partial|wrong)$/', $step->get_state());
        }
        $data['showpartialwrong'] = $showpartialwrong;

        // Если не нужно показывать детали, возвращаем пустой массив.
        if (!$showpartialwrong) {
            return $data;
        }

        $plugin = 'qtype_ddingroups';

        // Показываем детали оценки, если это включено.
        if ($question->showgrading) {
            // Получаем тип оценки.
            $gradingtype = $question->gradingtype;
            $gradingtype = qtype_ddingroups_question::get_grading_types($gradingtype);

            if ($gradingtype) {
                $data['gradingtype'] = get_string('gradingtype', $plugin) . ': ' . $gradingtype;
            }

            // Получаем детали текущего ответа.
            if ($currentresponse = $question->currentresponse) {
                $totalscore = 0;
                $totalmaxscore = 0;

                $data['ddingroupslayoutclass'] = $question->get_ddingroups_layoutclass();

                // Формируем детали оценки для каждого элемента.
                foreach ($currentresponse as $answerid => $groupid) {
                    if (array_key_exists($answerid, $question->answers)) {
                        $correctgroup = $question->correctresponse[$answerid] ?? null;

                        // Вычисляем баллы.
                        $isCorrect = $correctgroup === $groupid;
                        $score = $isCorrect ? 1 : 0; // Если ответ правильный, то 1, иначе 0.
                        $maxscore = 1; // Максимальный балл за элемент.

                        $totalscore += $score;
                        $totalmaxscore += $maxscore;

                        $data['scoredetails'][] = [
                            'answerid' => $answerid,
                            'groupid' => $groupid,
                            'iscorrect' => $isCorrect,
                            'score' => $score,
                            'maxscore' => $maxscore,
                            'percent' => round(100 * $score / $maxscore, 0),
                        ];
                    }
                }

                // Если используется схема "всё или ничего", убираем детали оценки.
                if ($question->gradingtype === qtype_ddingroups_question::GRADING_ALL_OR_NOTHING || $totalmaxscore == 0) {
                    unset($data['scoredetails']);
                } else {
                    // Формируем общие детали оценки.
                    $data['totalscore'] = $totalscore;
                    $data['totalmaxscore'] = $totalmaxscore;
                    $data['gradedetails'] = $totalmaxscore > 0 ? round(100 * $totalscore / $totalmaxscore, 0) : 0;
                }
            }
        }

        return $data;
    }
}
