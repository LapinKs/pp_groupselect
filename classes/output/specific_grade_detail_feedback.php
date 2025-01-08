<?php

namespace qtype_ddingroups\output;

use qtype_ddingroups_question;


class specific_grade_detail_feedback extends renderable_base {
    public function export_for_template(\renderer_base $output): array {

        $data = [];
        $question = $this->qa->get_question();

        // Decide if we should show grade explanation for "partial" or "wrong" states.
        // This should detect "^graded(partial|wrong)$" and possibly others.
        $showpartialwrong = false;
        if ($step = $this->qa->get_last_step()) {
            $showpartialwrong = preg_match('/(partial|wrong)$/', $step->get_state());
        }
        $data['showpartialwrong'] = $showpartialwrong;
        if (!$showpartialwrong) {
            return $data;
        }

        $plugin = 'qtype_ddingroups';

        // Show grading details if they are required.
        if ($question->showgrading) {
            // Fetch grading type.
            $gradingtype = $question->gradingtype;
            $gradingtype = qtype_ddingroups_question::get_grading_types($gradingtype);

            // Format grading type, e.g. Grading type: Relative to next item, excluding last item.
            if ($gradingtype) {
                $data['gradingtype'] = get_string('gradingtype', $plugin) . ': ' . $gradingtype;
            }

            // Fetch grade details and score details.
            if ($currentresponse = $question->currentresponse) {

                $totalscore = 0;
                $totalmaxscore = 0;

                $data['ddingroupslayoutclass'] = $question->get_ddingroups_layoutclass();

                // Format scoredetails, e.g. 1 /2 = 50%, for each item.
                foreach ($currentresponse as $position => $answerid) {
                    if (array_key_exists($answerid, $question->answers)) {
                        $score = $question->get_ddingroups_item_score($question, $position, $answerid);
                        if (!isset($score['maxscore'])) {
                            $score['score'] = get_string('noscore', $plugin);
                        } else {
                            $totalscore += $score['score'];
                            $totalmaxscore += $score['maxscore'];
                        }
                        $data['scoredetails'][] = [
                            'score' => $score['score'],
                            'maxscore' => $score['maxscore'],
                            'percent' => $score['percent'],
                        ];
                    }
                }

                if ($question->gradingtype === qtype_ddingroups_question::GRADING_ALL_OR_NOTHING || $totalmaxscore == 0) {
                    unset($data['scoredetails']); // All or nothing.
                } else {
                    // Format gradedetails, e.g. 4/6 = 67%.
                    if ($totalscore == 0) {
                        $data['gradedetails'] = 0;
                    } else {
                        $data['gradedetails'] = round(100 * $totalscore / $totalmaxscore, 0);
                    }
                    $data['totalscore'] = $totalscore;
                    $data['totalmaxscore'] = $totalmaxscore;
                }
            }
        }
        return $data;
    }
}
