<?php

class qtype_ddingroups_question extends question_graded_automatically {

    const LAYOUT_VERTICAL = 0;
    const LAYOUT_HORIZONTAL = 1;


    const GRADING_ABSOLUTE_POSITION = 0;

    const GRADING_RELATIVE_TO_CORRECT = 1;
    const MIN_SUBSET_ITEMS = 2;

    public $layouttype;


    public $gradingtype;

    public $groupcount;



    public $showgrading;


    public $correctfeedback;

    public $correctfeedbackformat;

    public $incorrectfeedback;

    public $incorrectfeedbackformat;

    public $partiallycorrectfeedback;

    public $partiallycorrectfeedbackformat;


    public $answers;

    public $correctresponse;


    public $currentresponse;


    protected $itemscores = [];

    public function start_attempt(question_attempt_step $step, $variant) {
        // Сохраняем корректный ответ с привязкой к группам.
        $this->correctresponse = [];
        foreach ($this->answers as $answerid => $answer) {
            $this->correctresponse[$answerid] = $answer->groupid ?? 0; // Привязываем к группе или к "непривязанной".
        }
        $step->set_qt_var('_correctresponse', json_encode($this->correctresponse));

        // Генерируем случайный начальный ответ.
        $shuffledanswers = array_keys($this->answers);
        shuffle($shuffledanswers);
        $this->currentresponse = [];
        foreach ($shuffledanswers as $answerid) {
            $this->currentresponse[$answerid] = 0; // Все ответы начинаются в непривязанной группе.
        }
        $step->set_qt_var('_currentresponse', json_encode($this->currentresponse));
    }

    public function apply_attempt_state(question_attempt_step $step) {
        $this->currentresponse = json_decode($step->get_qt_var('_currentresponse'), true) ?? [];
        $this->correctresponse = json_decode($step->get_qt_var('_correctresponse'), true) ?? [];

        // Привязываем группы к ответам, если они есть в БД.
        foreach ($this->answers as $answerid => $answer) {
            if (!empty($answer->groupid)) {
                $this->currentresponse[$answerid] = $answer->groupid;
            }
        }
    }



    // public function apply_attempt_state(question_attempt_step $step) {
    //     $this->currentresponse = array_filter(explode(',', $step->get_qt_var('_currentresponse')));
    //     $this->correctresponse = array_filter(explode(',', $step->get_qt_var('_correctresponse')));
    // }

    public function validate_can_regrade_with_other_version(question_definition $otherversion): ?string {
        $basemessage = parent::validate_can_regrade_with_other_version($otherversion);
        if ($basemessage) {
            return $basemessage;
        }

        if (count($this->answers) != count($otherversion->answers)) {
            return get_string('regradeissuenumitemschanged', 'qtype_ddingroups');
        }

        return null;
    }

    public function update_attempt_state_data_for_new_version(
            question_attempt_step $oldstep, question_definition $oldquestion) {
        parent::update_attempt_state_data_for_new_version($oldstep, $oldquestion);

        $mapping = array_combine(array_keys($oldquestion->answers), array_keys($this->answers));

        $oldorder = explode(',', $oldstep->get_qt_var('_currentresponse'));
        $neworder = [];
        foreach ($oldorder as $oldid) {
            $neworder[] = $mapping[$oldid] ?? $oldid;
        }

        $oldcorrect = explode(',', $oldstep->get_qt_var('_correctresponse'));
        $newcorrect = [];
        foreach ($oldcorrect as $oldid) {
            $newcorrect[] = $mapping[$oldid] ?? $oldid;
        }

        return [
            '_currentresponse' => implode(',', $neworder),
            '_correctresponse' => implode(',', $newcorrect),
        ];
    }
    public function get_ddingroups_layoutclass(): string {
        switch ($this->layouttype) {
            case self::LAYOUT_VERTICAL:
                return 'vertical';
            case self::LAYOUT_HORIZONTAL:
                return 'horizontal';
            default:
                return '';
        }
    }
    public static function get_layout_types(?int $type = null): array|string {
        $plugin = 'qtype_ddingroups';
        $types = [
            self::LAYOUT_VERTICAL   => get_string('vertical',   $plugin),
            self::LAYOUT_HORIZONTAL => get_string('horizontal', $plugin),
        ];
        return self::get_types($types, $type);
    }
    public function get_expected_data() {
        $name = $this->get_response_fieldname();
        return [$name => PARAM_TEXT];
    }

    public function get_correct_response() {
        $response = [];
        foreach ($this->correctresponse as $answerid => $groupid) {
            $response[] = [
                'item' => $this->answers[$answerid]->md5key, // Уникальный идентификатор элемента.
                'group' => $groupid, // Группа, к которой он принадлежит.
            ];
        }
        $name = $this->get_response_fieldname();
        return [$name => json_encode($response)];
    }



    public function summarise_response(array $response) {
        $this->update_current_response($response);

        $summary = [];
        foreach ($this->currentresponse as $answerid => $groupid) {
            $answer = $this->answers[$answerid];
            $summary[$groupid][] = $this->html_to_text($answer->answer, $answer->answerformat);
        }

        $result = [];
        foreach ($summary as $groupid => $items) {
            $groupname = $this->groups[$groupid]->name ?? 'Unassigned'; // Название группы.
            $result[] = $groupname . ': ' . implode(', ', $items);
        }

        return implode('; ', $result);
    }


    public function classify_response(array $response) {
        $this->update_current_response($response);

        $classifiedresponse = [];
        $fraction_per_item = 1 / count($this->correctresponse);

        foreach ($this->correctresponse as $answerid => $correctgroupid) {
            $currentgroupid = $this->currentresponse[$answerid] ?? null;
            $fraction = ($currentgroupid === $correctgroupid) ? $fraction_per_item : 0;

            $answer = $this->answers[$answerid];
            $subqid = question_utils::to_plain_text($answer->answer, $answer->answerformat);

            $classifiedresponse[$subqid] = new question_classified_response(
                $currentgroupid,
                $this->groups[$currentgroupid]->name ?? 'Unassigned',
                $fraction
            );
        }

        return $classifiedresponse;
    }


    public function is_complete_response(array $response) {
        $this->update_current_response($response);
        foreach ($this->answers as $answerid => $answer) {
            if (!isset($this->currentresponse[$answerid])) {
                return false; // Если хотя бы один элемент не помещён в группу.
            }
        }
        return true;
    }


    public function is_gradable_response(array $response) {
        $this->update_current_response($response);
        foreach ($this->currentresponse as $groupid) {
            if ($groupid !== null) {
                return true; // Если хотя бы один элемент помещён в группу.
            }
        }
        return false;
    }


    public function get_validation_error(array $response) {
        return '';
    }

    public function is_same_response(array $old, array $new) {
        $name = $this->get_response_fieldname();
        return (isset($old[$name]) && isset($new[$name]) && $old[$name] == $new[$name]);
    }

    public function grade_response(array $response) {
        // Логируем полученные данные от пользователя.
        error_log('Submitted response: ' . json_encode($response));

        // Обновляем текущий ответ.
        $this->update_current_response($response);

        // Логируем текущие и правильные ответы.
        error_log('Correct response: ' . json_encode($this->correctresponse));
        error_log('Current response: ' . json_encode($this->currentresponse));

        $correctresponse = $this->correctresponse;
        $currentresponse = $this->currentresponse;

        $countcorrect = 0;
        $totalitems = count($correctresponse);

        // Логируем используемый тип оценивания.
        error_log('Grading type: ' . $this->gradingtype);

        switch ($this->gradingtype) {
            case self::GRADING_ABSOLUTE_POSITION:
                foreach ($correctresponse as $answerid => $correctgroupid) {
                    $currentgroupid = $currentresponse[$answerid] ?? null;

                    // Логируем каждую проверку ответа.
                    error_log("Answer ID: $answerid, Correct Group: $correctgroupid, Current Group: $currentgroupid");

                    if ($currentgroupid !== $correctgroupid) {
                        error_log('Grading result: fraction=0');
                        return [0, question_state::graded_state_for_fraction(0)];
                    }
                }
                error_log('Grading result: fraction=1');
                return [1, question_state::graded_state_for_fraction(1)];

            case self::GRADING_RELATIVE_TO_CORRECT:
                foreach ($correctresponse as $answerid => $correctgroupid) {
                    $currentgroupid = $currentresponse[$answerid] ?? null;

                    // Логируем, если ответ совпадает с правильным.
                    if ($currentgroupid === $correctgroupid) {
                        $countcorrect++;
                        error_log("Correct match found for Answer ID: $answerid");
                    }
                }
                $fraction = $countcorrect / $totalitems;

                // Логируем итоговый результат оценки.
                error_log("Grading result: fraction=$fraction, countcorrect=$countcorrect, totalitems=$totalitems");
                return [$fraction, question_state::graded_state_for_fraction($fraction)];
        }

        // Возвращаем результат, если тип оценивания не указан или не поддерживается.
        error_log('Grading result: fraction=0 (unsupported grading type)');
        return [0, question_state::graded_state_for_fraction(0)];
    }



    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        if ($component == 'question') {
            if ($filearea == 'answer') {
                $answerid = reset($args); // Value of "itemid" is answer id.
                return array_key_exists($answerid, $this->answers);
            }
            if (in_array($filearea, ['correctfeedback', 'partiallycorrectfeedback', 'incorrectfeedback'])) {
                return $this->check_combined_feedback_file_access($qa, $options, $filearea, $args);
            }
            if ($filearea == 'hint') {
                return $this->check_hint_file_access($qa, $options, $args);
            }
        }
        return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
    }

    protected function check_combined_feedback_file_access($qa, $options, $filearea, $args = null) {
        $state = $qa->get_state();
        if (! $state->is_finished()) {
            $response = $qa->get_last_qt_data();
            if (! $this->is_gradable_response($response)) {
                return false;
            }
            list($fraction, $state) = $this->grade_response($response);
        }
        if ($state->get_feedback_class().'feedback' == $filearea) {
            return ($this->id == reset($args));
        } else {
            return false;
        }
    }

    public function get_response_fieldname(): string {
        return 'response_' . $this->id;
    }

    public function update_current_response(array $response) {
        $name = $this->get_response_fieldname();
        $this->currentresponse = []; // Сбрасываем текущий ответ.

        if (isset($response[$name]) && !empty($response[$name])) {
            // Разбиваем данные ответа на группы.
            $groupData = json_decode($response[$name], true); // Предполагается, что данные хранятся в JSON.
            foreach ($groupData as $itemid => $groupid) {
                // Проверяем, существует ли элемент среди ответов, и привязываем его к группе.
                if (isset($this->answers[$itemid])) {
                    $this->currentresponse[$itemid] = $groupid; // Привязка элемента к группе.
                }
            }
        }
    }




    public static function get_grading_types(?int $type = null): array|string {
        $plugin = 'qtype_ddingroups';
        $types = [
            self::GRADING_ABSOLUTE_POSITION => get_string('absoluteposition', $plugin),
            self::GRADING_RELATIVE_TO_CORRECT => get_string('relativetocorrect', $plugin),
        ];
        return self::get_types($types, $type);
    }




    public static function get_types(array $types, $type): array|string {
        if ($type === null) {
            return $types; // Return all $types.
        }
        if (array_key_exists($type, $types)) {
            return $types[$type]; // One $type.
        }

        throw new coding_exception('Invalid type: ' . $type);
    }


    public function get_num_parts_right(array $response): array {
        $this->update_current_response($response);

        $numright = 0;        // Количество элементов в правильных группах.
        $numpartial = 0;      // Количество элементов в неправильных группах.
        $numincorrect = 0;    // Количество элементов, которых нет в ответе.

        // Обрабатываем каждый ответ.
        foreach ($this->answers as $answerid => $answer) {
            $correctgroup = $this->correctresponse[$answerid] ?? null; // Правильная группа для элемента.
            $currentgroup = $this->currentresponse[$answerid] ?? null; // Группа, выбранная пользователем.

            // Элемент отсутствует в ответе.
            if ($currentgroup === null) {
                $numincorrect++;
                continue;
            }

            // Если элемент находится в правильной группе.
            if ($currentgroup === $correctgroup) {
                $numright++;
            } else {
                // Если элемент находится в неправильной группе.
                $numpartial++;
            }
        }

        // Дополнительная обработка: подсчёт пустых групп (если нужно).
        foreach ($this->groups as $groupid => $group) {
            if (empty($response[$groupid])) {
                $numincorrect++; // Пустая группа считается неправильной.
            }
        }

        return [$numright, $numpartial, $numincorrect];
    }





}
