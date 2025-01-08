<?php
namespace qtype_ddingroups\output;

use question_attempt;
use question_display_options;

class formulation_and_controls extends renderable_base {

    /**
     * Construct the rendarable as we also need to pass the question options.
     *
     * @param question_attempt $qa The question attempt object.
     * @param question_display_options $options The question options.
     */
    public function __construct(
        question_attempt $qa,
        /** @var question_display_options The question display options. */
        protected question_display_options $options
    ) {
        parent::__construct($qa);
    }

    public function export_for_template(\renderer_base $output): array {
        global $DB;
        $data = [];
        $question = $this->qa->get_question();
    
        $response = $this->qa->get_last_qt_data();
        $question->update_current_response($response);
    
        $currentresponse = $question->currentresponse ?? [];
        $correctresponse = $question->correctresponse ?? [];
    
        $data['questiontext'] = $question->format_questiontext($this->qa);
        $answers = $DB->get_records('qtype_ddingroups_items', ['questionid' => $question->id]);
    
        $data['layout'] = $question->layouttype == \qtype_ddingroups_question::LAYOUT_HORIZONTAL ? 'horizontal' : 'vertical';
        $groups = $DB->get_records('qtype_ddingroups_groups', ['questionid' => $question->id], 'groupnumber');
    
        $groupedItems = [];
        foreach ($answers as $answer) {
            $groupid = $answer->groupid;
            $answerid = $answer->id;
    
            if (isset($groups[$groupid])) {
                if (!isset($groupedItems[$groupid])) {
                    $groupedItems[$groupid] = [];
                }
                $groupedItems[$groupid][] = $answerid;
            } else {
                error_log("Warning: Answer with ID {$answerid} has invalid group ID {$groupid}.");
            }
        }
    
        error_log('data answers: ' . json_encode($answers));
        error_log('data groups: ' . json_encode($groups));
        error_log('Grouped items: ' . json_encode($groupedItems));
    
        // Формируем группы с их элементами.
        $data['groups'] = [];
        foreach ($groups as $groupid => $group) {
            $groupdata = [
                'groupname' => $group->content,
                'groupid' => "group-box-{$groupid}",
                'items' => [],
            ];
    
            if (isset($groupedItems[$groupid])) {
                foreach ($groupedItems[$groupid] as $itemid) {
                    if (isset($answers[$itemid])) {
                        $answer = $answers[$itemid];
                        $groupdata['items'][] = [
                            'answertext' => $question->format_text(
                                $answer->content,
                                $answer->contentformat,
                                $this->qa,
                                'question',
                                'answer',
                                $itemid
                            ),
                            'id' => "item-{$itemid}",
                        ];
                    }
                }
            }
    
            $data['groups'][] = $groupdata;
        }
    
        // Непривязанные элементы.
        $data['unassigned'] = [];
        foreach ($answers as $itemid => $answer) {
            if (!isset($groupedItems[$answer->groupid])) {
                $data['unassigned'][] = [
                    'answertext' => $question->format_text(
                        $answer->content,
                        $answer->contentformat,
                        $this->qa,
                        'question',
                        'answer',
                        $itemid
                    ),
                    'id' => "item-{$itemid}",
                ];
            }
        }
    
        $data['groupcount'] = count($groups);
        $data['readonly'] = $this->options->readonly;
        $data['active'] = $this->qa->get_state()->is_active();
        error_log('data: ' . json_encode($data));
    
        return $data;
    }
    
    
    
    
    
}
