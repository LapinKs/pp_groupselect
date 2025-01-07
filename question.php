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
        $this->correctresponse = json_decode($step->get_qt_var('_correctresponse'), true) ?? [];
        $this->currentresponse = json_decode($step->get_qt_var('_currentresponse'), true) ?? [];
    }
    

    public function apply_attempt_state(question_attempt_step $step) {
        $this->currentresponse = array_filter(explode(',', $step->get_qt_var('_currentresponse')));
        $this->correctresponse = array_filter(explode(',', $step->get_qt_var('_correctresponse')));
    }

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
                'item' => $this->answers[$answerid]->md5key,
                'group' => $groupid,
            ];
        }
        $name = $this->get_response_fieldname();
        return [$name => json_encode($response)];
    }
    

    public function summarise_response(array $response) {
        $name = $this->get_response_fieldname();
        $items = [];
        if (array_key_exists($name, $response)) {
            $items = explode(',', $response[$name]);
        }
        $answerids = [];
        foreach ($this->answers as $answer) {
            $answerids[$answer->md5key] = $answer->id;
        }
        foreach ($items as $i => $item) {
            if (array_key_exists($item, $answerids)) {
                $item = $this->answers[$answerids[$item]];
                $item = $this->html_to_text($item->answer, $item->answerformat);
                $item = shorten_text($item, 10, true); 
                $items[$i] = $item;
            } else {
                $items[$i] = ''; 
            }
        }
        return implode('; ', array_filter($items));
    }

    public function classify_response(array $response) {
        $this->update_current_response($response);
        $fraction = 1 / count($this->correctresponse);

        $classifiedresponse = [];
        foreach ($this->correctresponse as $position => $answerid) {
            if (in_array($answerid, $this->currentresponse)) {
                $currentposition = array_search($answerid, $this->currentresponse);
            }

            $answer = $this->answers[$answerid];
            $subqid = question_utils::to_plain_text($answer->answer, $answer->answerformat);


            $maxbytes = 100;
            if (strlen($subqid) > $maxbytes) {
 
                $subqid = substr($subqid, 0, $maxbytes);
                if (preg_match('/^(.|\n)*/u', '', $subqid, $match)) {
                    $subqid = $match[0];
                }
            }

            $classifiedresponse[$subqid] = new question_classified_response(
                $currentposition + 1,
                get_string('positionx', 'qtype_ddingroups', $currentposition + 1),
                ($currentposition == $position) * $fraction
            );
        }

        return $classifiedresponse;
    }

    public function is_complete_response(array $response) {
        return true;
    }

    public function is_gradable_response(array $response) {
        return true;
    }

    public function get_validation_error(array $response) {
        return '';
    }

    public function is_same_response(array $old, array $new) {
        $name = $this->get_response_fieldname();
        return (isset($old[$name]) && isset($new[$name]) && $old[$name] == $new[$name]);
    }

    public function grade_response(array $response) {
        $this->update_current_response($response);

        $countcorrect = 0;
        $countanswers = 0;
        $gradingtype = $this->gradingtype;
        switch ($gradingtype) {

            case self::GRADING_ABSOLUTE_POSITION:
                $correctresponse = $this->correctresponse;
                $currentresponse = $this->currentresponse;
                foreach ($correctresponse as $position => $answerid) {
                    if (array_key_exists($position, $currentresponse)) {
                        if ($currentresponse[$position] == $answerid) {
                            $countcorrect++;
                        }
                    }
                    $countanswers++;
                }

            case self::GRADING_RELATIVE_TO_CORRECT:
                $correctresponse = $this->correctresponse;
                $currentresponse = $this->currentresponse;
                $count = (count($correctresponse) - 1);
                foreach ($correctresponse as $position => $answerid) {
                    if (in_array($answerid, $currentresponse)) {
                        $currentposition = array_search($answerid, $currentresponse);
                        $currentscore = ($count - abs($position - $currentposition));
                        if ($currentscore > 0) {
                            $countcorrect += $currentscore;
                        }
                    }
                    $countanswers += $count;
                }
                break;
        }
        if ($countanswers == 0) {
            $fraction = 0;
        } else {
            $fraction = ($countcorrect / $countanswers);
        }
        return [
            $fraction,
            question_state::graded_state_for_fraction($fraction),
        ];
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
    
        if (array_key_exists($name, $response)) {
            // Разбиваем сохраненные данные по группам.
            $groupData = explode('|', $response[$name]); // Предположим, группы разделены символом `|`
            foreach ($groupData as $groupid => $groupItems) {
                $itemIds = explode(',', $groupItems); // Элементы в группе разделены запятой.
                foreach ($itemIds as $itemKey) {
                    // Сопоставляем MD5-ключи с ID ответов.
                    foreach ($this->answers as $answer) {
                        if ($itemKey == $answer->md5key) {
                            $this->currentresponse[$answer->id] = $groupid; // Привязываем элемент к группе.
                            break;
                        }
                    }
                }
            }
        }
    }
    

    public function get_next_answerids(array $answerids, bool $lastitem = false): array {
        $nextanswerids = [];
        $imax = count($answerids);
        $imax--;
        if ($lastitem) {
            $nextanswerid = 0;
        } else {
            $nextanswerid = $answerids[$imax];
            $imax--;
        }
        for ($i = $imax; $i >= 0; $i--) {
            $thisanswerid = $answerids[$i];
            $nextanswerids[$thisanswerid] = $nextanswerid;
            $nextanswerid = $thisanswerid;
        }
        return $nextanswerids;
    }


    public function get_previous_and_next_answerids(array $answerids, bool $all = false): array {
        $prevnextanswerids = [];
        $next = $answerids;
        $prev = [];
        while ($answerid = array_shift($next)) {
            if ($all) {
                $prevnextanswerids[$answerid] = (object) [
                    'prev' => $prev,
                    'next' => $next,
                ];
            } else {
                $prevnextanswerids[$answerid] = (object) [
                    'prev' => [empty($prev) ? 0 : $prev[0]],
                    'next' => [empty($next) ? 0 : $next[0]],
                ];
            }
            array_unshift($prev, $answerid);
        }
        return $prevnextanswerids;
    }


    public function get_ordered_subset(bool $contiguous): array {

        $positions = $this->get_ordered_positions($this->correctresponse, $this->currentresponse);
        $subsets = $this->get_ordered_subsets($positions, $contiguous);


        $bestsubset = [];

        $bestcount = 1;

        foreach ($subsets as $subset) {
            $count = count($subset);
            if ($count > $bestcount) {
                $bestcount = $count;
                $bestsubset = $subset;
            }
        }
        return $bestsubset;
    }


    public function get_ordered_positions(array $correctresponse, array $currentresponse): array {
        $positions = [];
        foreach ($currentresponse as $answerid) {
            $positions[] = array_search($answerid, $correctresponse);
        }
        return $positions;
    }

    public static function get_grading_types(?int $type = null): array|string {
        $plugin = 'qtype_ddingroups';
        $types = [
            self::GRADING_ABSOLUTE_POSITION => get_string('absoluteposition', $plugin),
            self::GRADING_RELATIVE_TO_CORRECT => get_string('relativetocorrect', $plugin),
        ];
        return self::get_types($types, $type);
    }

    public function get_ordered_subsets(array $positions, bool $contiguous): array {

 
        $subsets = [];

 
        foreach ($positions as $p => $value) {

            $isnew = true;

            $new = [];

            foreach ($subsets as $s => $subset) {

 
                $end = $positions[end($subset)];

                switch (true) {

                    case ($value == ($end + 1)):
                  
                        $isnew = false;
                        $subsets[$s][] = $p;
                        break;

                    case $contiguous:
               
                        break;

                    case ($value > $end):
                   
                        $isnew = false;
                        $new[] = $subset;
                        $subsets[$s][] = $p;
                        break;
                }
            }

   
            if ($isnew) {
                $new[] = [$p];
            }

            if (count($new)) {
                $subsets = array_merge($subsets, $new);
            }
        }

        return $subsets;
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
        $gradingtype = $this->gradingtype;

        $numright = 0;
        $numpartial = 0;
        $numincorrect = 0;
        list($correctresponse, $currentresponse) = $this->get_response_depend_on_grading_type($gradingtype);

        foreach ($this->currentresponse as $position => $answerid) {
            [$fraction, $score, $maxscore] =
                $this->get_fraction_maxscore_score_of_item($position, $answerid, $correctresponse, $currentresponse);
            if (is_null($fraction)) {
                continue;
            }

            if ($fraction > 0.999999) {
                $numright++;
            } else if ($fraction < 0.000001) {
                $numincorrect++;
            } else {
                $numpartial++;
            }
        }

        return [$numright, $numpartial, $numincorrect];
    }



    protected function get_fraction_maxscore_score_of_item(
        int $position,
        int $answerid,
        array $correctresponse,
        array $currentresponse
    ): array {
        $gradingtype = $this->gradingtype;

        $score    = 0;
        $maxscore = null;

        switch ($gradingtype) {
            case self::GRADING_ABSOLUTE_POSITION:
                if (isset($correctresponse[$position])) {
                    if ($correctresponse[$position] == $answerid) {
                        $score = 1;
                    }
                    $maxscore = 1;
                }
                break;
            case self::GRADING_RELATIVE_TO_CORRECT:
                if (isset($correctresponse[$position])) {
                    $maxscore = (count($correctresponse) - 1);
                    $answerid = $currentresponse[$position];
                    $correctposition = array_search($answerid, $correctresponse);
                    $score = ($maxscore - abs($correctposition - $position));
                    if ($score < 0) {
                        $score = 0;
                    }
                }
                break;
        }
        
        $fraction = $maxscore ? $score / $maxscore : $maxscore;

        return [$fraction, $score, $maxscore];
    }
    

    
    protected function get_response_depend_on_grading_type(string $gradingtype): array {

        $correctresponse = [];
        $currentresponse = [];
        switch ($gradingtype) {
            case self::GRADING_ABSOLUTE_POSITION:
            case self::GRADING_RELATIVE_TO_CORRECT:
                $correctresponse = $this->correctresponse;
                $currentresponse = $this->currentresponse;
                break;
        }
        return [$correctresponse, $currentresponse];
    }


  
    public function get_ddingroups_item_score(question_definition $question, int $position, int $answerid): array {

        if (!isset($this->itemscores[$position])) {

            [$correctresponse, $currentresponse] = $this->get_response_depend_on_grading_type($this->gradingtype);

            $percent  = 0;    // 100 * $fraction.
            [$fraction, $score, $maxscore] =
                $this->get_fraction_maxscore_score_of_item($position, $answerid, $correctresponse, $currentresponse);

            if ($maxscore === null) {

                $class = 'unscored';
            } else {
                if ($maxscore > 0) {
                    $percent = round(100 * $fraction, 0);
                }
                $class = match (true) {
                    $fraction > 0.999999 => 'correct',
                    $fraction < 0.000001 => 'incorrect',
                    $fraction >= 0.66 => 'partial66',
                    $fraction >= 0.33 => 'partial33',
                    default => 'partial00',
                };
            }

            $itemscores = [
                'score' => $score,
                'maxscore' => $maxscore,
                'fraction' => $fraction,
                'percent' => $percent,
                'class' => $class,
            ];
            $this->itemscores[$position] = $itemscores;
        }

        return $this->itemscores[$position];
    }

}
