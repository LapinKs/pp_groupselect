<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qtype_ddingroups\output;

use question_attempt;
use question_display_options;

/**
 * Create the question formulation, controls ready for output.
 *
 * @package    qtype_ddingroups
 * @copyright  2023 Ilya Tregubov <ilya.a.tregubov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
        $data = [];
        $question = $this->qa->get_question();
    
        // Получаем текущий ответ пользователя.
        $response = $this->qa->get_last_qt_data();
        
        $question->update_current_response($response);
    
        $currentresponse = $question->currentresponse ?? [];
        $correctresponse = $question->correctresponse ?? [];
    
        // Текст вопроса.
        $data['questiontext'] = $question->format_questiontext($this->qa);
    
        // Макет (горизонтальный или вертикальный).
        $data['horizontallayout'] = $question->layouttype == \qtype_ddingroups_question::LAYOUT_HORIZONTAL;
    
        // Группы.
        $data['groups'] = [];
        foreach ($question->groups as $groupid => $group) {
            $groupdata = [
                'groupname' => $group->content, // Название группы.
                'groupid' => "group-box-{$groupid}", // Уникальный ID бокса группы.
                'items' => [], // Элементы, привязанные к группе.
            ];
    
            // Добавляем элементы, принадлежащие этой группе.
            foreach ($currentresponse as $itemid => $selectedgroupid) {
                if ($selectedgroupid == $groupid && isset($question->answers[$itemid])) {
                    $answer = $question->answers[$itemid];
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
    
            $data['groups'][] = $groupdata;
        }
    
        // Непривязанные элементы (общий бокс).
        $data['unassigned'] = [];
        foreach ($question->answers as $itemid => $answer) {
            if (!isset($currentresponse[$itemid]) || $currentresponse[$itemid] === 0) {
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
    
        // Количество групп.
        $data['groupcount'] = count($question->groups);
    
        // Поля read-only и активность.
        $data['readonly'] = $this->options->readonly;
        $data['active'] = $this->qa->get_state()->is_active();
        error_log('Exported data: ' . json_encode($data));

        return $data;
    }
    
    
}
